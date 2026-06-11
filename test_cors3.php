<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Test GET request with auth (using a token)
// First, let's just test without auth to see if PDF is generated
$req = Illuminate\Http\Request::create(
    '/api/pdf/income-tax-report?month=6&year=2026',
    'GET'
);
$req->headers->set('Origin', 'http://server:2050');

echo "URI: " . $req->getUri() . "\n";
echo "Path: " . $req->path() . "\n";
echo "Root: " . $req->getBaseUrl() . "\n";

// Capture all output
ob_start();
$resp = $kernel->handle($req);
$extra_output = ob_get_clean();

echo "Status: " . $resp->getStatusCode() . "\n";
echo "ACAO: " . ($resp->headers->get('Access-Control-Allow-Origin') ?? 'MISSING') . "\n";
echo "Content-Type: " . ($resp->headers->get('Content-Type') ?? 'MISSING') . "\n";
echo "Extra output: " . ($extra_output ? 'YES (' . strlen($extra_output) . ' bytes)' : 'NONE') . "\n";
if ($extra_output) {
    echo "Extra content: " . substr($extra_output, 0, 200) . "\n";
}
$body = $resp->getContent();
echo 'Body length: ' . strlen($body) . "\n";
if (strpos($body, '%PDF') === 0) {
    echo "Body starts with PDF header: YES\n";
} else {
    echo 'Body first 100 chars: ' . substr($body, 0, 100) . "\n";
}
