<?php
// includes/env.php
// Environment variables loader and two-way UI-to-ENV writer

$envFilePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . ".env";

/**
 * Load environment variables from the .env file if it exists
 */
function load_env_file() {
    global $envFilePath;
    
    if (!file_exists($envFilePath)) {
        return;
    }
    
    $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === "" || str_starts_with($line, "#")) {
            continue;
        }
        
        $parts = explode("=", $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            
            // Remove enclosing quotes if present
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

// Automatically load on include
load_env_file();

/**
 * Retrieve an environment variable with fallbacks
 */
function get_env_setting($key, $default = null, $pdo = null) {
    $val = getenv($key);
    if ($val !== false && $val !== "") {
        return $val;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== "") {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== "") {
        return $_SERVER[$key];
    }
    
    // Check database system_settings table if available
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $dbVal = $stmt->fetchColumn();
            if ($dbVal !== false && $dbVal !== null && $dbVal !== "") {
                return $dbVal;
            }
        } catch (Exception $e) {
            // Ignore DB errors during bootstrap
        }
    }
    
    return $default;
}

/**
 * Write updated key-values to .env file and sync to current process & database
 */
function update_env_file(array $keyValues, $pdo = null) {
    global $envFilePath;
    
    // Read existing file lines or start fresh
    $lines = [];
    if (file_exists($envFilePath)) {
        $lines = file($envFilePath, FILE_IGNORE_NEW_LINES);
    }
    
    $existingKeys = [];
    $newLines = [];
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === "" || str_starts_with($trimmed, "#")) {
            $newLines[] = $line;
            continue;
        }
        
        $parts = explode("=", $trimmed, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            if (array_key_exists($k, $keyValues)) {
                $v = $keyValues[$k];
                // Quote if contains spaces or special characters
                $formattedVal = (strpos($v, " ") !== false || strpos($v, "#") !== false) ? '"' . addcslashes($v, '"\\') . '"' : $v;
                $newLines[] = "{$k}={$formattedVal}";
                $existingKeys[$k] = true;
                
                // Update live environment
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            } else {
                $newLines[] = $line;
            }
        } else {
            $newLines[] = $line;
        }
    }
    
    // Append any new keys that were not in the file
    foreach ($keyValues as $k => $v) {
        if (!isset($existingKeys[$k])) {
            $formattedVal = (strpos($v, " ") !== false || strpos($v, "#") !== false) ? '"' . addcslashes($v, '"\\') . '"' : $v;
            $newLines[] = "{$k}={$formattedVal}";
            
            // Update live environment
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
    
    // Write out safely to .env
    $content = implode("\n", $newLines) . "\n";
    $writeOk = file_put_contents($envFilePath, $content);
    
    // Also sync to MySQL database system_settings table if PDO is provided
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
            ");
            foreach ($keyValues as $k => $v) {
                $stmt->execute([$k, (string)$v]);
            }
        } catch (Exception $e) {
            // Ignore DB sync error if table does not exist yet
        }
    }
    
    return $writeOk !== false;
}
