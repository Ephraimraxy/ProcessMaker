<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$columns = DB::select('DESCRIBE sessions');
foreach ($columns as $column) {
    echo $column->Field . " | " . $column->Type . " | " . ($column->Null === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}
