<?php
// includes/db.php
// Database connection for Marketing Image Generator

$dbHost = getenv("DB_HOST") ?: "localhost";
$dbName = getenv("DB_NAME") ?: "marketing_ai_db";
$dbUser = getenv("DB_USER") ?: "root";
$dbPass = getenv("DB_PASS") ?: "";

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // If the database does not exist yet, we can attempt to connect without dbname and create it
    try {
        $rootPdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass);
        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        $pdo = new PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $innerException) {
        http_response_code(500);
        die(json_encode([
            "error" => "Database connection failed",
            "message" => $innerException->getMessage()
        ]));
    }
}
