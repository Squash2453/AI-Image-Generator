<?php
// includes/auth.php
// Authentication, Session management, and Role/Model Access Control (ACL)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/db.php";

/**
 * Check if a user is currently logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION["user_id"]) && !empty($_SESSION["user_id"]);
}

/**
 * Get the currently logged-in user details
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        "id" => $_SESSION["user_id"],
        "name" => $_SESSION["user_name"] ?? "User",
        "email" => $_SESSION["user_email"] ?? "",
        "role" => $_SESSION["user_role"] ?? "user"
    ];
}

/**
 * Check if the logged-in user is an admin
 */
function is_admin(): bool {
    return is_logged_in() && ($_SESSION["user_role"] ?? "") === "admin";
}

/**
 * Enforce authentication for pages or JSON APIs
 */
function require_login(bool $isJson = false) {
    if (!is_logged_in()) {
        if ($isJson) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Unauthorized. Please log in."]);
            exit;
        }
        header("Location: login.php");
        exit;
    }
}

/**
 * Enforce admin privileges
 */
function require_admin(bool $isJson = false) {
    require_login($isJson);
    if (!is_admin()) {
        if ($isJson) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Forbidden. Admin access required."]);
            exit;
        }
        die("Access Denied: Administrator privileges required.");
    }
}

/**
 * Model Access Control List (ACL) check:
 * Validates whether the given user/role is authorized to use a specific model.
 */
function can_user_access_model(string $modelId, ?array $user, PDO $pdo): array {
    if (!$user) {
        return ["allowed" => false, "reason" => "User is not authenticated."];
    }

    $stmt = $pdo->prepare("SELECT * FROM models WHERE model_id = ? LIMIT 1");
    $stmt->execute([$modelId]);
    $model = $stmt->fetch();

    if (!$model) {
        return ["allowed" => false, "reason" => "Requested model '{$modelId}' is not registered in the system."];
    }

    if (!$model["is_active"]) {
        return ["allowed" => false, "reason" => "Model '{$model['name']}' is currently disabled by administrator."];
    }

    // Role-based ACL check
    if ($model["access_level"] === "admin" && $user["role"] !== "admin") {
        return [
            "allowed" => false, 
            "reason" => "Model '{$model['name']}' is restricted to Administrators only (ACL Policy)."
        ];
    }

    return ["allowed" => true, "model" => $model];
}

/**
 * Get all models permitted for the current user's role
 */
function get_permitted_models(?array $user, PDO $pdo): array {
    if (!$user) {
        return [];
    }
    
    if ($user["role"] === "admin") {
        $stmt = $pdo->query("SELECT * FROM models WHERE is_active = 1 ORDER BY is_default DESC, name ASC");
        return $stmt->fetchAll();
    }
    
    // Regular users only see 'all' access_level models
    $stmt = $pdo->query("SELECT * FROM models WHERE is_active = 1 AND access_level = 'all' ORDER BY is_default DESC, name ASC");
    return $stmt->fetchAll();
}

/**
 * CSRF Protection Helpers
 */
function get_csrf_token(): string {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION["csrf_token"])) {
        return false;
    }
    return hash_equals($_SESSION["csrf_token"], $token);
}
