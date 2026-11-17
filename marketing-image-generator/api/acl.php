<?php
// api/acl.php
// Access Control List (ACL) API for Models and Users

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../includes/auth.php";

require_login(true);

$user = current_user();
$action = $_GET["action"] ?? "get_available_models";

try {
    switch ($action) {
        // Models permitted for the current user (available to both users and admins)
        case "get_available_models":
            $models = get_permitted_models($user, $pdo);
            echo json_encode(["success" => true, "models" => $models, "user_role" => $user["role"]]);
            break;

        // Admin-only: List all models with full ACL metadata
        case "list_all_models":
            require_admin(true);
            $stmt = $pdo->query("SELECT * FROM models ORDER BY is_default DESC, name ASC");
            $models = $stmt->fetchAll();
            echo json_encode(["success" => true, "models" => $models]);
            break;

        // Admin-only: Update model ACL policy
        case "update_model":
            require_admin(true);
            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            $modelId = (int) ($input["id"] ?? 0);
            $accessLevel = $input["access_level"] ?? "all";
            $isActive = isset($input["is_active"]) ? (int)$input["is_active"] : 1;
            $isDefault = isset($input["is_default"]) ? (int)$input["is_default"] : 0;
            $costPerImage = (float) ($input["cost_per_image"] ?? 0);
            $description = trim($input["description"] ?? "");
            $name = trim($input["name"] ?? "");

            if ($modelId <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Invalid model ID."]);
                exit;
            }

            if (!in_array($accessLevel, ["all", "admin"])) {
                $accessLevel = "all";
            }

            // If setting this model as default, clear default on other models
            if ($isDefault === 1) {
                $pdo->exec("UPDATE models SET is_default = 0");
            }

            $stmt = $pdo->prepare("
                UPDATE models 
                SET access_level = ?, is_active = ?, is_default = ?, cost_per_image = ?, description = ?, name = ?
                WHERE id = ?
            ");
            $stmt->execute([$accessLevel, $isActive, $isDefault, $costPerImage, $description, $name, $modelId]);

            echo json_encode(["success" => true, "message" => "Model ACL settings updated successfully."]);
            break;

        // Admin-only: Register a new OpenRouter model into ACL catalog
        case "add_model":
            require_admin(true);
            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            $modelIdentifier = trim($input["model_id"] ?? "");
            $name = trim($input["name"] ?? "");
            $provider = trim($input["provider"] ?? "OpenRouter");
            $description = trim($input["description"] ?? "");
            $accessLevel = $input["access_level"] ?? "all";
            $costPerImage = (float) ($input["cost_per_image"] ?? 0);

            if (empty($modelIdentifier) || empty($name)) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Model ID and display name are required."]);
                exit;
            }

            if (!in_array($accessLevel, ["all", "admin"])) {
                $accessLevel = "all";
            }

            $stmt = $pdo->prepare("
                INSERT INTO models (model_id, name, provider, description, access_level, is_active, cost_per_image)
                VALUES (?, ?, ?, ?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name),
                    provider = VALUES(provider),
                    description = VALUES(description),
                    access_level = VALUES(access_level),
                    cost_per_image = VALUES(cost_per_image),
                    is_active = 1
            ");
            $stmt->execute([$modelIdentifier, $name, $provider, $description, $accessLevel, $costPerImage]);

            echo json_encode(["success" => true, "message" => "Model added to ACL catalog successfully."]);
            break;

        // Admin-only: Delete or disable a model
        case "delete_model":
            require_admin(true);
            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            $modelId = (int) ($input["id"] ?? 0);

            if ($modelId <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Invalid model ID."]);
                exit;
            }

            // Check if model is default
            $stmtCheck = $pdo->prepare("SELECT is_default FROM models WHERE id = ?");
            $stmtCheck->execute([$modelId]);
            if ($stmtCheck->fetchColumn() == 1) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Cannot delete the default model. Please assign another default first."]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM models WHERE id = ?");
            $stmt->execute([$modelId]);

            echo json_encode(["success" => true, "message" => "Model removed from catalog."]);
            break;

        // Admin-only: List users with generation telemetry summary
        case "list_users":
            require_admin(true);
            $stmt = $pdo->query("
                SELECT 
                    u.id, u.name, u.email, u.role, u.status, u.created_at,
                    COUNT(DISTINCT c.id) as total_chats,
                    COUNT(t.id) as total_generations,
                    COALESCE(SUM(t.cost), 0) as total_spent,
                    COALESCE(SUM(t.total_tokens), 0) as total_tokens
                FROM users u
                LEFT JOIN chats c ON u.id = c.user_id
                LEFT JOIN telemetry t ON u.id = t.user_id
                GROUP BY u.id
                ORDER BY u.id ASC
            ");
            $usersList = $stmt->fetchAll();
            echo json_encode(["success" => true, "users" => $usersList]);
            break;

        // Admin-only: Update user role / status
        case "update_user":
            require_admin(true);
            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            $targetUserId = (int) ($input["user_id"] ?? 0);
            $newRole = $input["role"] ?? null;
            $newStatus = $input["status"] ?? null;

            if ($targetUserId <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Invalid user ID."]);
                exit;
            }

            // Prevent admin from locking out themselves
            if ($targetUserId === (int)$user["id"] && ($newRole === "user" || $newStatus === "disabled")) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "You cannot demote or disable your own active admin account."]);
                exit;
            }

            $updates = [];
            $params = [];
            if ($newRole && in_array($newRole, ["admin", "user"])) {
                $updates[] = "role = ?";
                $params[] = $newRole;
            }
            if ($newStatus && in_array($newStatus, ["active", "disabled"])) {
                $updates[] = "status = ?";
                $params[] = $newStatus;
            }

            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "No valid changes specified."]);
                exit;
            }

            $params[] = $targetUserId;
            $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?");
            $stmt->execute($params);

            echo json_encode(["success" => true, "message" => "User access updated successfully."]);
            break;

        default:
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid ACL action."]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
