<?php
$file = 'C:/xampp/htdocs/CodeCanvas/app/dashboard.php';
$content = file_get_contents($file);

$brokenTarget = <<<JS
            clearTimeout(_slugCheckTimeout);
                return; // Stop deployment
            }
JS;

$restored = <<<JS
            clearTimeout(_slugCheckTimeout);
            _slugCheckTimeout = setTimeout(() => {
                validateSlug(slug);
            }, 600);
        });

        async function validateSlug(slug) {
            const btn = document.getElementById('publish-submit-btn');
            const feedback = document.getElementById('slug-feedback');
            const projectId = document.getElementById('publish-project-id').value;
            
            try {
                const fd = new FormData();
                fd.append('slug', slug);
                fd.append('project_id', projectId);
                
                const response = await fetch(BASE_URL + '/check-slug.php', { method: 'POST', body: fd });
                const data = await response.json();
                
                if (data.available) {
                    feedback.innerHTML = '<span style="color:#22c55e;">✔ Available</span>';
                    btn.disabled = false;
                } else {
                    feedback.innerHTML = `<span style="color:#eab308;">❌ \${data.error || 'Taken on Vercel.'}</span>`;
                    btn.disabled = true;
                }
            } catch (e) {
                feedback.innerHTML = '<span style="color:#ef4444;">Error checking availability.</span>';
                btn.disabled = true;
            }
        }

        document.getElementById('publish-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const id   = document.getElementById('publish-project-id').value;
            const slug = document.getElementById('publish-slug').value;
            const btn = document.getElementById('publish-submit-btn');
            
            // Final verify before deployment
            btn.disabled = true;
            document.getElementById('slug-feedback').innerHTML = '<span style="color:#888;">Double checking availability...</span>';
            
            const fd = new FormData();
            fd.append('slug', slug);
            fd.append('project_id', id);
            const response = await fetch(BASE_URL + '/check-slug.php', { method: 'POST', body: fd });
            const data = await response.json();
            
            if (!data.available) {
                document.getElementById('slug-feedback').innerHTML = `<span style="color:#ef4444; font-weight:600;">❌ Taken on Vercel. Try another.</span>`;
                return; // Stop deployment
            }
JS;

// Convert CRLF to LF in content and strings just to be safe for replacing
$content = str_replace("\r\n", "\n", $content);
$brokenTarget = str_replace("\r\n", "\n", $brokenTarget);
$restored = str_replace("\r\n", "\n", $restored);

if (strpos($content, $brokenTarget) !== false) {
    $content = str_replace($brokenTarget, $restored, $content);
    file_put_contents($file, $content);
    echo "Restored dashboard.php successfully.";
} else {
    echo "Could not find broken target.";
}
