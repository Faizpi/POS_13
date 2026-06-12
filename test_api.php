<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
$plainToken = Illuminate\Support\Str::random(40);
App\Models\PersonalAccessToken::create([
    'user_id' => $user->id,
    'name' => 'test_cli',
    'token' => hash('sha256', $plainToken),
]);

$req = Illuminate\Http\Request::create('/api/v1/dashboard', 'GET');
$req->headers->set('Authorization', 'Bearer ' . $plainToken);
$req->headers->set('Accept', 'application/json');

$response = app()->make(Illuminate\Contracts\Http\Kernel::class)->handle($req);
echo "Status: " . $response->getStatusCode() . "\n";
// echo "Body: " . $response->getContent() . "\n";
