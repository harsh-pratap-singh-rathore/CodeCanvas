<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/app/services/VercelDeployService.php';

$s = new VercelDeployService();
try {
    $s->createProject('harsh6');
    echo "Success!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
