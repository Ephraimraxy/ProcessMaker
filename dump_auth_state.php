<?php

use ProcessMaker\Models\User;
use ProcessMaker\Models\Permission;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = User::find(1);

if (!$user) {
    echo "User 1 not found\n";
    exit;
}

echo "User ID: " . $user->id . "\n";
echo "Username: " . $user->username . "\n";
echo "Is Admin Flag (raw): " . (isset($user->attributes['is_administrator']) ? $user->attributes['is_administrator'] : 'NOT SET') . "\n";
echo "Is Admin Cast: " . ($user->is_administrator ? 'TRUE' : 'FALSE') . "\n";

echo "\nDirect Permissions:\n";
foreach ($user->permissions as $p) {
    echo "- " . $p->name . "\n";
}

echo "\nGroups:\n";
foreach ($user->groupMembersFromMemberable as $gm) {
    $group = $gm->group;
    echo "- " . $group->name . " (ID: " . $group->id . ")\n";
    foreach ($group->permissions as $p) {
        echo "  - Permission: " . $p->name . "\n";
    }
}

echo "\nTotal Permissions in System: " . Permission::count() . "\n";
echo "First 5 permissions:\n";
foreach (Permission::limit(5)->get() as $p) {
    echo "- " . $p->name . "\n";
}
