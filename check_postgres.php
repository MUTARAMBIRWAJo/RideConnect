<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    $db = DB::select("SELECT current_database() as db")[0]->db;
    $schema = DB::select("SELECT current_schema() as schema")[0]->schema;
    
    echo "Current Database: " . $db . "\n";
    echo "Current Schema: " . $schema . "\n";
    
    $tables = DB::select("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        ORDER BY table_name
    ");
    
    echo "Total tables in public schema: " . count($tables) . "\n";
    
    $auditExists = Schema::hasTable('audit_marker');
    echo "audit_marker table exists: " . ($auditExists ? 'YES' : 'NO') . "\n";
    if ($auditExists) {
        $count = DB::table('audit_marker')->count();
        echo "audit_marker row count: " . $count . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
