<?php
require_once __DIR__ . '/app/services/VercelDeployService.php';

$s = new VercelDeployService();
$slugs = ['rathore5', 'this-is-some-obscure-slug-1234981', 'google', 'test-deploy-slug-1'];

foreach ($slugs as $slug) {
    echo "$slug: " . ($s->checkSlugAvailability($slug) ? "Available" : "Taken") . "\n";
}
