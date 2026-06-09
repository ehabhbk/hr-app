<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create a test request
$request = \Illuminate\Http\Request::create('/api/pdf/salary-report?month=4&year=2026', 'GET');

// Handle the request
try {
    $response = $app->handle($request);
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content-Type: " . $response->headers->get('Content-Type') . "\n";
    echo "Content Length: " . strlen($response->getContent()) . "\n";
    
    if (strlen($response->getContent()) < 500) {
        echo "Content: " . $response->getContent() . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
