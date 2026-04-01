<?php
require_once __DIR__ . '/../../config/bootstrap.php';
/**
 * Vercel Deployment Service
 *
 * Handles zip-free API-based deployments securely straight to Vercel V13 API base64 deployments.
 */

class VercelDeployService
{
    private $token;
    private $teamId;
    
    public function __construct() {
        $apiKeys      = include APP_ROOT . '/config/api_keys.php';
        $this->token  = $_ENV['VERCEL_TOKEN'] ?? $apiKeys['vercel']['token'] ?? '';
        $this->teamId = $_ENV['VERCEL_TEAM_ID'] ?? $apiKeys['vercel']['team_id'] ?? '';
        
        if (empty($this->token)) {
            throw new Exception("Vercel token is not configured.");
        }
    }
    
    /**
     * Deploys a directory to Vercel.
     * 
     * @param string $buildDir The absolute path to the directory to deploy
     * @param string $slug The unique slug / project name
     * @return string The deployment URL
     */
    public function deploy(string $buildDir, string $slug): string
    {
        $filesPayload = $this->prepareFilesPayload($buildDir);
        
        $payload = [
            'name' => $slug,
            'project' => $slug,
            'target' => 'production',
            'files' => $filesPayload,
            'projectSettings' => [
                'framework' => null
            ]
        ];
        
        $url = "https://api.vercel.com/v13/deployments";
        if (!empty($this->teamId)) {
            $url .= "?teamId=" . urlencode($this->teamId);
        }
        
        $response = $this->makeRequest('POST', $url, $payload);
        
        if (!isset($response['url'])) {
            throw new Exception("Deployment failed. Vercel didn't return a URL. Response: " . json_encode($response));
        }
        
        // With the project specifically created and targeted, 
        // Vercel guarantees the slug domain for production.
        return 'https://' . $slug . '.vercel.app';
    }

    /**
     * Creates a new project on Vercel.
     * 
     * @param string $slug The unique slug / project name
     * @return void
     */
    public function createProject(string $slug): void
    {
        $url = "https://api.vercel.com/v9/projects";
        if (!empty($this->teamId)) {
            $url .= "?teamId=" . urlencode($this->teamId);
        }
        
        $payload = [
            'name' => $slug
        ];
        
        try {
            $this->makeRequest('POST', $url, $payload);
        } catch (Exception $e) {
            // Ignore creation errors if project already exists, 
            // but log or throw other severe API errors.
            $msg = strtolower($e->getMessage());
            if (strpos($msg, '400') === false && strpos($msg, '409') === false && strpos($msg, 'name_already_in_use') === false && strpos($msg, 'already exists') === false) {
                throw new Exception("Vercel Project Creation Error: " . $e->getMessage());
            }
        }
    }

    /**
     * TIER 1 VALIDATION: Edge Node HTTP Proxy Check
     * Fast, unauthenticated check to see if a .vercel.app subdomain is globally available.
     * 
     * @param string $slug The unique slug / project name
     * @return bool True if available, False if globally taken
     */
    public function checkSlugAvailability(string $slug): bool
    {
        $url = "https://{$slug}.vercel.app/";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Get headers to verify Vercel error exact match
        curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request is sufficient and faster
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // If it's a 404 AND it explicitly carries the Vercel deployment not found error header,
        // we can safely assume this domain is unclaimed network-wide.
        if ($httpCode === 404 && strpos(strtolower($response), 'deployment_not_found') !== false) {
            return true;
        }
        
        // If it returns 200, 301, 308, or even a 404 without the Vercel error header, 
        // someone owns it or it's reserved somehow. Play it safe.
        return false;
    }

    /**
     * TIER 2 VALIDATION: Hard Domain Verification
     * Verifies if Vercel successfully auto-assigned the exact domain to the project we just created.
     * 
     * @param string $projectId The exact Vercel project ID or slug
     * @param string $domain The exact domain to verify (e.g. harsh.vercel.app)
     * @return bool True if the domain is attached to this project
     */
    public function verifyProjectHasDomain(string $projectId, string $domain): bool
    {
        $url = "https://api.vercel.com/v9/projects/" . urlencode($projectId) . "/domains";
        if (!empty($this->teamId)) {
            $url .= "?teamId=" . urlencode($this->teamId);
        }
        
        try {
            $response = $this->makeRequest('GET', $url);
            
            if (isset($response['domains']) && is_array($response['domains'])) {
                foreach ($response['domains'] as $d) {
                    if (isset($d['name']) && $d['name'] === $domain) {
                        return true;
                    }
                }
            }
            return false;
        } catch (Exception $e) {
            // If we can't fetch domains, assume failure
            return false;
        }
    }
    
    /**
     * Deletes a Vercel project by slug.
     * This will completely remove the project and all its deployments from Vercel.
     * 
     * @param string $slug The unique slug / project name
     * @return bool True on success
     */
    public function deleteProject(string $slug): bool
    {
        $url = "https://api.vercel.com/v9/projects/" . urlencode($slug);
        if (!empty($this->teamId)) {
            $url .= "?teamId=" . urlencode($this->teamId);
        }
        
        try {
            $this->makeRequest('DELETE', $url);
            return true;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                // Project already doesn't exist.
                return true;
            }
            throw $e;
        }
    }
    
    /**
     * Recursively reads the build directory and formats files for Vercel.
     */
    private function prepareFilesPayload(string $buildDir): array
    {
        $payload = [];
        $buildDir = rtrim($buildDir, '/') . '/';
        
        if (!is_dir($buildDir)) {
            throw new Exception("Build directory not found: " . $buildDir);
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isFile()) continue;
            
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($buildDir));
            $relativePath = str_replace('\\', '/', $relativePath); // Windows compat
            
            // Skip unnecessary files
            if ($relativePath === 'netlify.toml') {
                continue;
            }
            // Strictly Base64 encode all file content
            $content = file_get_contents($filePath);
            $payload[] = [
                'file' => $relativePath,
                'data' => base64_encode($content),
                'encoding' => 'base64'
            ];
        }
        
        return $payload;
    }
    
    /**
     * Makes an authenticated cURL request to Vercel API.
     */
    private function makeRequest(string $method, string $url, array $body = []): array
    {
        $ch = curl_init($url);

        $caBundle = 'C:/xampp/php/extras/ssl/cacert.pem';
        $sslOpts  = file_exists($caBundle)
            ? [CURLOPT_SSL_VERIFYPEER => true,  CURLOPT_CAINFO => $caBundle]
            : [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0];

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];

        curl_setopt_array($ch, $sslOpts + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_TIMEOUT        => 180,
        ]);

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) throw new Exception('Vercel API cURL error: ' . $err);
        
        $decoded = json_decode($resp, true);
        
        if ($code >= 400) {
           $errorMsg = $decoded['error']['message'] ?? 'Unknown Error';
           throw new Exception("Vercel API Error (HTTP {$code}): " . $errorMsg);
        }
        
        if ($decoded === null) throw new Exception("Vercel API bad response (HTTP {$code}): " . substr($resp, 0, 200));
        
        return $decoded;
    }
}
