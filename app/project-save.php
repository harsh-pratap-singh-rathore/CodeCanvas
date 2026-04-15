<?php
/**
 * PROJECT SAVER - Saves form data to database
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Read JSON Input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate
    if (!isset($input['project_id']) || !isset($input['data'])) {
        throw new Exception("Missing project ID or data");
    }
    
    $projectId = $input['project_id'];
    $formData = $input['data']; // This is the JSON object of fields
    
    // Convert to JSON string
    $jsonContent = json_encode($formData);
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception("Unauthorized access to project");
    }
    
    // Map schema fields to dedicated DB columns for indexing/searching
    // Priority: developer schema field names → generic fallbacks
    $brandName   = $formData['full_name']   ?? $formData['brand_name'] ?? $formData['name'] ?? null;
    $description = $formData['about']       ?? $formData['description'] ?? null;
    $contact     = $formData['email']       ?? $formData['contact'] ?? null;

    // Skills: may be an array of {name, icon} objects — serialize to JSON string
    $skillsRaw = $formData['skills'] ?? null;
    $skills = null;
    if (!empty($skillsRaw)) {
        $skills = is_array($skillsRaw) ? json_encode($skillsRaw) : (string)$skillsRaw;
    }

    // Build SQL — content_json is always updated
    $sql = "UPDATE projects SET
            content_json = :json,
            updated_at   = NOW()";

    if ($brandName)   $sql .= ", brand_name = :brand";
    if ($description) $sql .= ", description = :desc";
    if ($skills)      $sql .= ", skills = :skills";
    if ($contact)     $sql .= ", contact_email = :contact";

    $sql .= " WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    $params = [
        ':json' => $jsonContent,
        ':id'   => $projectId
    ];

    if ($brandName)   $params[':brand']   = $brandName;
    if ($description) $params[':desc']    = $description;
    if ($skills)      $params[':skills']  = $skills;
    if ($contact)     $params[':contact'] = $contact;
    
    $stmt->execute($params);
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
