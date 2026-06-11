<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Need to create a valid token first
$user = App\Models\User::find(1);
$token = $user->createToken('test')->plainTextToken;

echo "Token: " . substr($token, 0, 20) . "...\n\n";

// Test GET with valid token
$req = Illuminate\Http\Request::create(
    '/api/pdf/income-tax-report?month=6&year=2026',
    'GET'
);
$req->headers->set('Origin', 'http://server:2050');
$req->headers->set('Authorization', 'Bearer ' . $token);

ob_start();
$resp = $kernel->handle($req);
$extra_output = ob_get_clean();

echo "Status: " . $resp->getStatusCode() . "\n";
echo "ACAO: " . ($resp->headers->get('Access-Control-Allow-Origin') ?? 'MISSING') . "\n";
echo "Content-Type: " . ($resp->headers->get('Content-Type') ?? 'MISSING') . "\n";
echo "Extra output: " . ($extra_output ? 'YES (' . strlen($extra_output) . ' bytes)' : 'NONE') . "\n";
$body = $resp->getContent();
echo 'Body length: ' . strlen($body) . "\n";
if (strpos($body, '%PDF') === 0) {
    echo "Body starts with PDF header: YES\n";
} else {
    echo 'Body first 500 chars: ' . substr($body, 0, 500) . "\n";
}
