<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

$user = App\Models\User::where('username', 'admin')->first();
$token = $user->createToken('test')->plainTextToken;

echo "Token: " . $token . "\n\n";

$request = Illuminate\Http\Request::create('/api/me', 'GET');
$request->headers->set('Authorization', 'Bearer ' . $token);

$response = $kernel->handle($request);
$content = $response->getContent();
$data = json_decode($content, true);

echo "API Response:\n";
print_r($data);