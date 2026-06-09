<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get a valid token for testing
$user = \App\Models\User::first();
$token = $user->createToken('test')->plainTextToken;

$request = \Illuminate\Http\Request::create('/api/pdf/salary-report?month=4&year=2026', 'GET');
$request->headers->set('Authorization', 'Bearer ' . explode('|', $token)[1]);

try {
    $response = $app->handle($request);
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content-Type: " . $response->headers->get('Content-Type') . "\n";
    echo "Content Length: " . strlen($response->getContent()) . "\n";
    
    if (strlen($response->getContent()) < 500) {
        echo "Content: " . substr($response->getContent(), 0, 200) . "\n";
    } else {
        echo "Content preview: " . substr($response->getContent(), 0, 100) . "...\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
