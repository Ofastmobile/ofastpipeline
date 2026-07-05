<?php
require_once 'c:\Users\bodma\Local Sites\ofast-pipeline\app\public\wp-load.php';

global $wpdb;
$p = $wpdb->prefix;

$wpdb->query( "ALTER TABLE {$p}ofp_clients ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL AFTER business_category" );

echo "Column added.";
