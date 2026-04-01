<?php
$file = __DIR__ . '/app/project-settings.php';
$content = file_get_contents($file);

// The problem starts at "} else {" around line 63
// Let's find "} else {" and remove from it until just before "$user = ["
$badStart = strpos($content, "} else {");
$userStart = strpos($content, "\$user = [");

if ($badStart !== false && $userStart !== false) {
    // We want to keep the closing brace for the main IF.
    // The previous valid code ended with:
    //         } catch (PDOException $e) {
    //             $error = "Database error: " . $e->getMessage();
    //         }
    //     }
    //
    // So if we just replace everything from "} else {" to "$user =" with "\n}\n\n$user ="
    
    $clean = substr($content, 0, $badStart) . "}\n\n" . substr($content, $userStart);
    file_put_contents($file, $clean);
    echo "Fixed syntax error!";
} else {
    echo "Could not find tokens.";
}
