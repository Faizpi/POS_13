<?php

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = User::first();
$plainToken = Str::random(40);
PersonalAccessToken::create([
    'user_id' => $user->id,
    'name' => 'test_cli',
    'token' => hash('sha256', $plainToken),
]);

$req = Request::create('/api/v1/dashboard', 'GET');
$req->headers->set('Authorization', 'Bearer '.$plainToken);
$req->headers->set('Accept', 'application/json');

$response = app()->make(Illuminate\Contracts\Http\Kernel::class)->handle($req);
echo 'Status: '.$response->getStatusCode()."\n";
// echo "Body: " . $response->getContent() . "\n";
