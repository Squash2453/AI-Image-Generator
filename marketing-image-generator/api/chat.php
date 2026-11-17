<?php
// api/chat.php
// Chat session management, message sending, and conversational image generation

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/openrouter.php";

require_login(true);

$user = current_user();
$client = new OpenRouterClient($pdo);

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

try {
    switch ($action) {
        // List all chats for current user
        case "list_chats":
            $stmt = $pdo->prepare("
                SELECT 
                    c.id, c.uuid, c.title, c.marketing_goal, c.default_model, c.created_at, c.updated_at,
                    COUNT(m.id) as message_count,
                    (SELECT image_path FROM messages WHERE chat_id = c.id AND image_path IS NOT NULL ORDER BY id DESC LIMIT 1) as last_image
                FROM chats c
                LEFT JOIN messages m ON c.id = m.chat_id
                WHERE c.user_id = ?
                GROUP BY c.id
                ORDER BY c.updated_at DESC
            ");
            $stmt->execute([$user["id"]]);
            $chats = $stmt->fetchAll();

            echo json_encode(["success" => true, "chats" => $chats]);
            break;

        // Create a new chat session
        case "create_chat":
            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            $title = trim($input["title"] ?? "New Marketing Campaign");
            $marketingGoal = trim($input["marketing_goal"] ?? "Social Media Ad");
            $defaultModel = trim($input["default_model"] ?? "recraft/recraft-v4.1-flash-v2:free");
            $uuid = bin2hex(random_bytes(16));

            $stmt = $pdo->prepare("
                INSERT INTO chats (uuid, user_id, title, marketing_goal, default_model)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$uuid, $user["id"], $title, $marketingGoal, $defaultModel]);
            $chatId = $pdo->lastInsertId();

            echo json_encode([
                "success" => true,
                "chat" => [
                    "id" => $chatId,
                    "uuid" => $uuid,
                    "title" => $title,
                    "marketing_goal" => $marketingGoal,
                    "default_model" => $defaultModel
                ]
            ]);
            break;

        // Get full chat thread with all messages, images, and telemetry
        case "get_chat":
            $uuid = $_GET["uuid"] ?? "";
            if (empty($uuid)) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Chat UUID is required."]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM chats WHERE uuid = ? LIMIT 1");
            $stmt->execute([$uuid]);
            $chat = $stmt->fetch();

            if (!$chat) {
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "Chat thread not found."]);
                exit;
            }

            // Access check: only chat owner or admin can view
            if ($chat["user_id"] != $user["id"] && $user["role"] !== "admin") {
                http_response_code(403);
                echo json_encode(["success" => false, "error" => "Unauthorized access to this chat."]);
                exit;
            }

            // Fetch messages with their associated telemetry data
            $stmtMsg = $pdo->prepare("
                SELECT 
                    m.id, m.chat_id, m.user_id, m.role, m.content, m.image_path,
                    m.aspect_ratio, m.style_preset, m.model_used, m.created_at,
                    t.latency_ms, t.prompt_tokens, t.completion_tokens, t.total_tokens,
                    t.cost, t.status_code, t.meta_json
                FROM messages m
                LEFT JOIN telemetry t ON m.id = t.message_id
                WHERE m.chat_id = ?
                ORDER BY m.id ASC
            ");
            $stmtMsg->execute([$chat["id"]]);
            $messages = $stmtMsg->fetchAll();

            // Calculate overall chat telemetry summary
            $stmtSum = $pdo->prepare("
                SELECT 
                    COUNT(id) as total_generations,
                    COALESCE(SUM(cost), 0) as total_cost,
                    COALESCE(SUM(total_tokens), 0) as total_tokens,
                    COALESCE(AVG(latency_ms), 0) as avg_latency_ms
                FROM telemetry
                WHERE chat_id = ?
            ");
            $stmtSum->execute([$chat["id"]]);
            $summary = $stmtSum->fetch();

            echo json_encode([
                "success" => true,
                "chat" => $chat,
                "messages" => $messages,
                "telemetry_summary" => [
                    "generations" => (int) $summary["total_generations"],
                    "total_cost" => (float) $summary["total_cost"],
                    "total_tokens" => (int) $summary["total_tokens"],
                    "avg_latency_ms" => (int) round($summary["avg_latency_ms"])
                ]
            ]);
            break;

        // Send a message and generate a marketing image (with continuous conversation context)
        case "send_message":
            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            $chatUuid = trim($input["chat_uuid"] ?? "");
            $prompt = trim($input["prompt"] ?? "");
            $model = trim($input["model"] ?? "");
            $aspectRatio = trim($input["aspect_ratio"] ?? "1:1");
            $stylePreset = trim($input["style_preset"] ?? "general");

            if (empty($prompt)) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Prompt cannot be empty."]);
                exit;
            }

            // If no chat_uuid provided, auto-create a new chat
            if (empty($chatUuid)) {
                $chatUuid = bin2hex(random_bytes(16));
                $autoTitle = mb_strimwidth($prompt, 0, 45, "...");
                $stmt = $pdo->prepare("
                    INSERT INTO chats (uuid, user_id, title, marketing_goal, default_model)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$chatUuid, $user["id"], $autoTitle, "Marketing Visual", $model]);
                $chatId = (int) $pdo->lastInsertId();
                $chat = [
                    "id" => $chatId,
                    "uuid" => $chatUuid,
                    "user_id" => $user["id"],
                    "title" => $autoTitle,
                    "marketing_goal" => "Marketing Visual",
                    "default_model" => $model
                ];
            } else {
                $stmt = $pdo->prepare("SELECT * FROM chats WHERE uuid = ? LIMIT 1");
                $stmt->execute([$chatUuid]);
                $chat = $stmt->fetch();
                if (!$chat) {
                    http_response_code(404);
                    echo json_encode(["success" => false, "error" => "Chat session not found."]);
                    exit;
                }
                $chatId = (int) $chat["id"];
            }

            // Determine model to use
            if (empty($model)) {
                $model = !empty($chat["default_model"]) ? $chat["default_model"] : "recraft/recraft-v4.1-flash-v2:free";
            }

            // ACL CHECK: Verify if current user is allowed to access this model
            $aclCheck = can_user_access_model($model, $user, $pdo);
            if (!$aclCheck["allowed"]) {
                http_response_code(403);
                echo json_encode([
                    "success" => false,
                    "error" => "Access Denied: " . $aclCheck["reason"]
                ]);
                exit;
            }

            // Retrieve previous messages for conversation continuation (Requirement 5)
            $stmtHist = $pdo->prepare("
                SELECT role, content, image_path, aspect_ratio, style_preset
                FROM messages
                WHERE chat_id = ?
                ORDER BY id ASC
            ");
            $stmtHist->execute([$chatId]);
            $history = $stmtHist->fetchAll();

            // 1. Record the user message
            $stmtUserMsg = $pdo->prepare("
                INSERT INTO messages (chat_id, user_id, role, content, aspect_ratio, style_preset, model_used)
                VALUES (?, ?, 'user', ?, ?, ?, ?)
            ");
            $stmtUserMsg->execute([$chatId, $user["id"], $prompt, $aspectRatio, $stylePreset, $model]);
            $userMsgId = (int) $pdo->lastInsertId();

            // If this is the first message in the chat, update the chat title to a concise marketing headline
            if (count($history) === 0 && ($chat["title"] === "New Marketing Campaign" || empty($chat["title"]))) {
                $cleanedTitle = mb_strimwidth($prompt, 0, 42, "...");
                $stmtUpdateTitle = $pdo->prepare("UPDATE chats SET title = ?, default_model = ? WHERE id = ?");
                $stmtUpdateTitle->execute([$cleanedTitle, $model, $chatId]);
                $chat["title"] = $cleanedTitle;
            }

            // 2. Call OpenRouter to generate the marketing image
            $genResult = $client->generateMarketingImage([
                "user_id" => $user["id"],
                "chat_id" => $chatId,
                "model" => $model,
                "prompt" => $prompt,
                "aspect_ratio" => $aspectRatio,
                "style_preset" => $stylePreset,
                "marketing_goal" => $chat["marketing_goal"] ?? "Marketing Visual",
                "history" => $history
            ]);

            if (!$genResult["success"]) {
                // OpenRouter returned an error or network problem
                echo json_encode([
                    "success" => false,
                    "error" => $genResult["error"],
                    "latency_ms" => $genResult["latency_ms"] ?? 0,
                    "chat_uuid" => $chatUuid
                ]);
                exit;
            }

            // 3. Record the assistant message with generated image
            $assistantContent = $genResult["marketing_summary"] ?? "Generated image based on your marketing prompt.";
            $stmtAssist = $pdo->prepare("
                INSERT INTO messages (chat_id, user_id, role, content, image_path, aspect_ratio, style_preset, model_used, parent_message_id)
                VALUES (?, ?, 'assistant', ?, ?, ?, ?, ?, ?)
            ");
            $stmtAssist->execute([
                $chatId,
                $user["id"],
                $assistantContent,
                $genResult["image_path"],
                $aspectRatio,
                $stylePreset,
                $model,
                $userMsgId
            ]);
            $assistantMsgId = (int) $pdo->lastInsertId();

            // 4. Update the telemetry record with message_id link
            if (!empty($genResult["telemetry"]["id"])) {
                $stmtTeleUpdate = $pdo->prepare("UPDATE telemetry SET message_id = ? WHERE id = ?");
                $stmtTeleUpdate->execute([$assistantMsgId, $genResult["telemetry"]["id"]]);
            }

            // Update chat's updated_at timestamp
            $pdo->prepare("UPDATE chats SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$chatId]);

            echo json_encode([
                "success" => true,
                "chat_uuid" => $chatUuid,
                "chat_title" => $chat["title"],
                "user_message" => [
                    "id" => $userMsgId,
                    "role" => "user",
                    "content" => $prompt,
                    "aspect_ratio" => $aspectRatio,
                    "style_preset" => $stylePreset,
                    "created_at" => date("Y-m-d H:i:s")
                ],
                "assistant_message" => [
                    "id" => $assistantMsgId,
                    "role" => "assistant",
                    "content" => $assistantContent,
                    "image_path" => $genResult["image_path"],
                    "aspect_ratio" => $aspectRatio,
                    "style_preset" => $stylePreset,
                    "model_used" => $model,
                    "created_at" => date("Y-m-d H:i:s")
                ],
                "telemetry" => $genResult["telemetry"]
            ]);
            break;

        // Delete a chat session
        case "delete_chat":
            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            $uuid = $input["uuid"] ?? "";

            $stmt = $pdo->prepare("SELECT id, user_id FROM chats WHERE uuid = ? LIMIT 1");
            $stmt->execute([$uuid]);
            $chat = $stmt->fetch();

            if (!$chat) {
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "Chat not found."]);
                exit;
            }

            if ($chat["user_id"] != $user["id"] && $user["role"] !== "admin") {
                http_response_code(403);
                echo json_encode(["success" => false, "error" => "Forbidden."]);
                exit;
            }

            // Delete associated records
            $pdo->prepare("DELETE FROM telemetry WHERE chat_id = ?")->execute([$chat["id"]]);
            $pdo->prepare("DELETE FROM messages WHERE chat_id = ?")->execute([$chat["id"]]);
            $pdo->prepare("DELETE FROM chats WHERE id = ?")->execute([$chat["id"]]);

            echo json_encode(["success" => true, "message" => "Chat deleted successfully."]);
            break;

        // Rename a chat session
        case "rename_chat":
            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            $uuid = $input["uuid"] ?? "";
            $newTitle = trim($input["title"] ?? "");

            if (empty($newTitle)) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Title cannot be empty."]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id, user_id FROM chats WHERE uuid = ? LIMIT 1");
            $stmt->execute([$uuid]);
            $chat = $stmt->fetch();

            if (!$chat || ($chat["user_id"] != $user["id"] && $user["role"] !== "admin")) {
                http_response_code(403);
                echo json_encode(["success" => false, "error" => "Unauthorized."]);
                exit;
            }

            $pdo->prepare("UPDATE chats SET title = ? WHERE id = ?")->execute([$newTitle, $chat["id"]]);
            echo json_encode(["success" => true, "title" => $newTitle]);
            break;

        default:
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid action."]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
