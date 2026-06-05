<?php
/**
 * Database Configuration
 * Update credentials for your environment
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'govexam_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

define('ROOT', dirname(__DIR__));

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Please check configuration.');
}
