<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';

if (isset($db_connection_error) && $db_connection_error) {
    echo "DB connection failed: " . htmlspecialchars($conn->connect_error ?? 'unknown');
} else {
    echo "DB ok (host={$host}, db={$db_name})";
}