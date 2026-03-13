<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$sessions = DB::table('sessions')->orderBy('last_activity', 'desc')->limit(5)->get();

echo "Count: " . count($sessions) . "\n";
foreach ($sessions as $s) {
    echo "ID: " . $s->id . " | User: " . ($s->user_id ?? 'NULL') . " | IP: " . $s->ip_address . "\n";
    $payload = unserialize(base64_decode($s->payload));
    echo "Keys: " . implode(', ', array_keys($payload)) . "\n";
    if (isset($payload['login_verified'])) {
        echo "  [OK] login_verified = " . ($payload['login_verified'] ? 'TRUE' : 'FALSE') . "\n";
    }
    foreach (array_keys($payload) as $key) {
        if (strpos($key, 'login_web_') === 0) {
            echo "  [AUTH] Found auth key: $key\n";
        }
    }
    echo "---------------------------------\n";
}
