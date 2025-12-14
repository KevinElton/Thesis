<?php
/**
 * Database Structure Analyzer
 */
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->connect();

echo "=== DATABASE STRUCTURE ANALYSIS ===\n\n";

// Get all tables
$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "TABLE: $table\n";
    echo str_repeat("-", 50) . "\n";
    
    // Get columns
    $cols = $conn->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        $pk = $col['Key'] == 'PRI' ? ' [PK]' : '';
        $fk = $col['Key'] == 'MUL' ? ' [FK?]' : '';
        echo "  - {$col['Field']} ({$col['Type']}){$pk}{$fk}\n";
    }
    
    // Get foreign keys
    $fks = $conn->query("
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'thesisscheduling' 
        AND TABLE_NAME = '$table'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($fks)) {
        echo "  FOREIGN KEYS:\n";
        foreach ($fks as $fk) {
            echo "    - {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
        }
    }
    
    echo "\n";
}

// Check for potential missing FKs
echo "\n=== POTENTIAL MISSING FOREIGN KEYS ===\n";
$potential_fks = [
    ['assignment', 'panelist_id', 'panelist', 'panelist_id'],
    ['assignment', 'group_id', 'thesis_group', 'group_id'],
    ['assignment', 'schedule_id', 'schedule', 'schedule_id'],
    ['schedule', 'group_id', 'thesis_group', 'group_id'],
    ['availability', 'panelist_id', 'panelist', 'panelist_id'],
    ['thesis', 'group_id', 'thesis_group', 'group_id'],
    ['thesis', 'adviser_id', 'panelist', 'panelist_id'],
    ['evaluation', 'panelist_id', 'panelist', 'panelist_id'],
    ['evaluation', 'group_id', 'thesis_group', 'group_id'],
    ['notifications', 'user_id', 'panelist', 'panelist_id'],
];

foreach ($potential_fks as [$table, $col, $ref_table, $ref_col]) {
    // Check if column exists
    $exists = $conn->query("
        SELECT COUNT(*) FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = 'thesisscheduling' 
        AND TABLE_NAME = '$table' 
        AND COLUMN_NAME = '$col'
    ")->fetchColumn();
    
    if ($exists) {
        // Check if FK exists
        $fk_exists = $conn->query("
            SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = 'thesisscheduling' 
            AND TABLE_NAME = '$table'
            AND COLUMN_NAME = '$col'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetchColumn();
        
        if (!$fk_exists) {
            echo "MISSING: $table.$col -> $ref_table.$ref_col\n";
        }
    }
}

echo "\n=== DONE ===\n";
