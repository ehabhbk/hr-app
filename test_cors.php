<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Test OPTIONS preflight
$req = Illuminate\Http\Request::create(
    'http://server/hr-app/public/api/pdf/income-tax-report?month=6&year=2026',
    'OPTIONS'
);
$req->headers->set('Origin', 'http://server:2050');
$req->headers->set('Access-Control-Request-Method', 'GET');
$req->headers->set('Access-Control-Request-Headers', 'authorization, content-type');

$resp = $kernel->handle($req);

echo "=== Preflight (OPTIONS) ===\n";
echo 'Status: ' . $resp->getStatusCode() . "\n";
echo 'ACAO: ' . ($resp->headers->get('Access-Control-Allow-Origin') ?? 'MISSING') . "\n";
echo 'ACAM: ' . ($resp->headers->get('Access-Control-Allow-Methods') ?? 'MISSING') . "\n";
echo 'ACAH: ' . ($resp->headers->get('Access-Control-Allow-Headers') ?? 'MISSING') . "\n";
echo 'Body: ' . $resp->getContent() . "\n\n";

// Test GET request
$req2 = Illuminate\Http\Request::create(
    'http://server/hr-app/public/api/pdf/income-tax-report?month=6&year=2026',
    'GET'
);
$req2->headers->set('Origin', 'http://server:2050');

$resp2 = $kernel->handle($req2);

echo "=== Actual request (GET) ===\n";
echo 'Status: ' . $resp2->getStatusCode() . "\n";
echo 'ACAO: ' . ($resp2->headers->get('Access-Control-Allow-Origin') ?? 'MISSING') . "\n";
echo 'Content-Type: ' . ($resp2->headers->get('Content-Type') ?? 'MISSING') . "\n";
$body = $resp2->getContent();
echo 'Body length: ' . strlen($body) . "\n";
echo 'Body first 100 chars: ' . substr($body, 0, 100) . "\n";
