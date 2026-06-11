<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Test OPTIONS preflight
$req = Illuminate\Http\Request::create(
    '/api/pdf/income-tax-report?month=6&year=2026',
    'GET'
);
$req->headers->set('Origin', 'http://server:2050');

echo "URI: " . $req->getUri() . "\n";
echo "Path: " . $req->path() . "\n";
echo "Root: " . $req->getBaseUrl() . "\n";

$resp = $kernel->handle($req);

echo "Status: " . $resp->getStatusCode() . "\n";
echo "ACAO: " . ($resp->headers->get('Access-Control-Allow-Origin') ?? 'MISSING') . "\n";
echo "Content-Type: " . ($resp->headers->get('Content-Type') ?? 'MISSING') . "\n";
$body = $resp->getContent();
echo 'Body length: ' . strlen($body) . "\n";
if (strlen($body) < 500) {
    echo 'Body: ' . $body . "\n";
}
