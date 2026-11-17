<?php
// api/settings.php
// OpenRouter Configuration API with two-way UI-to-.env mapping

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/env.php";
require_once __DIR__ . "/../includes/openrouter.php";

require_admin(true); // Admin only!

$action = $_GET["action"] ?? "get_config";
$client = new OpenRouterClient($pdo);

try {
    switch ($action) {
        // Retrieve current OpenRouter configuration and .env status
        case "get_config":
            global $envFilePath;
            $rawKey = get_env_setting("OPENROUTER_API_KEY", "", $pdo);
            
            // Mask API key for security preview
            $maskedKey = "";
            if (!empty($rawKey)) {
                $len = strlen($rawKey);
                if ($len > 12) {
                    $maskedKey = substr($rawKey, 0, 8) . "••••••••" . substr($rawKey, -4);
                } else {
                    $maskedKey = "••••••••";
                }
            }

            echo json_encode([
                "success" => true,
                "config" => [
                    "api_key_masked" => $maskedKey,
                    "has_api_key" => !empty($rawKey),
                    "base_url" => get_env_setting("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1/images", $pdo),
                    "default_model" => get_env_setting("OPENROUTER_DEFAULT_MODEL", "recraft/recraft-v4.1-flash-v2:free", $pdo),
                    "site_name" => get_env_setting("OPENROUTER_SITE_NAME", "Marketing Image Studio", $pdo),
                    "site_url" => get_env_setting("OPENROUTER_SITE_URL", "http://localhost/AI-Image-Generator/marketing-image-generator", $pdo),
                    "timeout" => (int) get_env_setting("OPENROUTER_TIMEOUT", 120, $pdo)
                ],
                "env_file" => [
                    "path" => realpath($envFilePath) ?: $envFilePath,
                    "exists" => file_exists($envFilePath),
                    "is_writable" => is_writable(dirname($envFilePath)) || (file_exists($envFilePath) && is_writable($envFilePath)),
                    "last_modified" => file_exists($envFilePath) ? date("Y-m-d H:i:s", filemtime($envFilePath)) : null
                ]
            ]);
            break;

        // Save updated OpenRouter configuration directly to .env file and environment
        case "save_config":
            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            
            $updates = [];
            
            // Only update API key if non-empty and not masked
            if (!empty($input["api_key"]) && strpos($input["api_key"], "••••") === false) {
                $updates["OPENROUTER_API_KEY"] = trim($input["api_key"]);
            }
            if (isset($input["base_url"])) {
                $updates["OPENROUTER_BASE_URL"] = trim($input["base_url"]);
            }
            if (isset($input["default_model"])) {
                $updates["OPENROUTER_DEFAULT_MODEL"] = trim($input["default_model"]);
            }
            if (isset($input["site_name"])) {
                $updates["OPENROUTER_SITE_NAME"] = trim($input["site_name"]);
            }
            if (isset($input["site_url"])) {
                $updates["OPENROUTER_SITE_URL"] = trim($input["site_url"]);
            }
            if (isset($input["timeout"])) {
                $updates["OPENROUTER_TIMEOUT"] = (string) (int)$input["timeout"];
            }

            if (empty($updates)) {
                echo json_encode(["success" => true, "message" => "No changes detected."]);
                exit;
            }

            $saved = update_env_file($updates, $pdo);

            if (!$saved) {
                http_response_code(500);
                echo json_encode(["success" => false, "error" => "Failed to write updates to .env file. Check folder permissions."]);
                exit;
            }

            // Also update default model in models table if specified
            if (!empty($updates["OPENROUTER_DEFAULT_MODEL"])) {
                $pdo->exec("UPDATE models SET is_default = 0");
                $stmtDef = $pdo->prepare("UPDATE models SET is_default = 1 WHERE model_id = ?");
                $stmtDef->execute([$updates["OPENROUTER_DEFAULT_MODEL"]]);
            }

            echo json_encode([
                "success" => true,
                "message" => "OpenRouter settings successfully saved to .env and mapped to active environment!",
                "synced_keys" => array_keys($updates)
            ]);
            break;

        // Test OpenRouter connection with given key or saved key
        case "test_connection":
            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            $testKey = null;
            if (!empty($input["api_key"]) && strpos($input["api_key"], "••••") === false) {
                $testKey = trim($input["api_key"]);
            }

            $res = $client->testConnection($testKey);
            echo json_encode($res);
            break;

        // Fetch live image models from OpenRouter to import into ACL catalog
        case "fetch_openrouter_models":
            $apiKey = get_env_setting("OPENROUTER_API_KEY", "", $pdo);
            if (empty($apiKey)) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "OpenRouter API Key is missing."]);
                exit;
            }

            $ch = curl_init("https://openrouter.ai/api/v1/models");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer " . $apiKey],
                CURLOPT_TIMEOUT => 25,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($raw, true);
            if (!isset($data["data"])) {
                http_response_code(500);
                echo json_encode(["success" => false, "error" => "Failed to load models list from OpenRouter."]);
                exit;
            }

            // Filter models relevant to image generation / creative visuals
            $imageModels = [];
            foreach ($data["data"] as $m) {
                $id = $m["id"];
                if (preg_match('/(flux|recraft|image|dall-e|seedream|sdxl|diffusion|banana)/i', $id)) {
                    $imageModels[] = [
                        "id" => $id,
                        "name" => $m["name"] ?? $id,
                        "description" => $m["description"] ?? "",
                        "pricing" => $m["pricing"] ?? []
                    ];
                }
            }

            echo json_encode(["success" => true, "models" => $imageModels]);
            break;

        default:
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid settings action."]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
