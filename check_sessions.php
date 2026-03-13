<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$sessions = DB::table('sessions')->orderBy('last_activity', 'desc')->limit(10)->get();

echo "ID | User ID | IP | Last Activity\n";
echo str_repeat("-", 60) . "\n";

foreach ($sessions as $session) {
    try {
        $data = unserialize(base64_decode($session->payload));
        $auth_key = "";
        foreach ($data as $k => $v) {
            if (strpos($k, 'login_web_') === 0) {
                $auth_key = $k;
                break;
            }
        }
        
        echo sprintf("%s | %s | %s | %s\n", 
            substr($session->id, 0, 8) . "...", 
            $session->user_id ?: 'NULL', 
            $session->ip_address, 
            date('Y-m-d H:i:s', $session->last_activity)
        );
        if ($auth_key) {
            echo "  [AUTH FOUND] Key: $auth_key | Value (User ID): " . $data[$auth_key] . "\n";
        } else {
            echo "  [NO AUTH] keys found: " . implode(", ", array_keys($data)) . "\n";
        }
    } catch (Exception $e) {
        echo "Error decoding session " . $session->id . ": " . $e->getMessage() . "\n";
    }
    echo str_repeat("-", 60) . "\n";
}
