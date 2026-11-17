<?php
// api/telemetry.php
// Telemetry and analytics metrics for chat history and system usage

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../includes/auth.php";

require_login(true);

$user = current_user();
$action = $_GET["action"] ?? "overall_stats";

try {
    switch ($action) {
        // Overall telemetry metrics and breakdown
        case "overall_stats":
            $scope = $_GET["scope"] ?? "user"; // "user" or "global" (global requires admin)
            if ($scope === "global" && !is_admin()) {
                $scope = "user";
            }

            $userFilter = ($scope === "global") ? "" : "WHERE t.user_id = " . (int)$user["id"];

            // 1. KPI Aggregates
            $sqlKpi = "
                SELECT 
                    COUNT(t.id) as total_generations,
                    COALESCE(SUM(t.cost), 0) as total_cost,
                    COALESCE(SUM(t.total_tokens), 0) as total_tokens,
                    COALESCE(SUM(t.prompt_tokens), 0) as total_prompt_tokens,
                    COALESCE(SUM(t.completion_tokens), 0) as total_completion_tokens,
                    COALESCE(AVG(t.latency_ms), 0) as avg_latency_ms,
                    COALESCE(SUM(CASE WHEN t.status_code >= 200 AND t.status_code < 300 THEN 1 ELSE 0 END) / COUNT(t.id) * 100, 100) as success_rate
                FROM telemetry t
                {$userFilter}
            ";
            $kpi = $pdo->query($sqlKpi)->fetch();

            // 2. Model breakdown
            $sqlModels = "
                SELECT 
                    t.model,
                    COUNT(t.id) as generations,
                    COALESCE(SUM(t.cost), 0) as total_cost,
                    COALESCE(SUM(t.total_tokens), 0) as total_tokens,
                    COALESCE(AVG(t.latency_ms), 0) as avg_latency_ms
                FROM telemetry t
                {$userFilter}
                GROUP BY t.model
                ORDER BY generations DESC
            ";
            $modelsBreakdown = $pdo->query($sqlModels)->fetchAll();

            // 3. Daily activity (Last 14 days)
            $sqlDaily = "
                SELECT 
                    DATE(t.created_at) as log_date,
                    COUNT(t.id) as generations,
                    COALESCE(SUM(t.cost), 0) as daily_cost,
                    COALESCE(SUM(t.total_tokens), 0) as daily_tokens
                FROM telemetry t
                " . ($userFilter ? $userFilter . " AND " : "WHERE ") . " t.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                GROUP BY DATE(t.created_at)
                ORDER BY log_date ASC
            ";
            $dailyActivity = $pdo->query($sqlDaily)->fetchAll();

            // 4. Recent generation logs (Latest 25)
            $sqlRecent = "
                SELECT 
                    t.id, t.chat_id, t.user_id, t.model, t.latency_ms,
                    t.prompt_tokens, t.completion_tokens, t.total_tokens, t.image_tokens,
                    t.cost, t.status_code, t.aspect_ratio, t.meta_json, t.created_at,
                    c.title as chat_title,
                    u.name as user_name
                FROM telemetry t
                LEFT JOIN chats c ON t.chat_id = c.id
                LEFT JOIN users u ON t.user_id = u.id
                {$userFilter}
                ORDER BY t.id DESC
                LIMIT 25
            ";
            $recentLogs = $pdo->query($sqlRecent)->fetchAll();

            echo json_encode([
                "success" => true,
                "scope" => $scope,
                "kpis" => [
                    "total_generations" => (int) $kpi["total_generations"],
                    "total_cost" => (float) $kpi["total_cost"],
                    "total_tokens" => (int) $kpi["total_tokens"],
                    "total_prompt_tokens" => (int) $kpi["total_prompt_tokens"],
                    "total_completion_tokens" => (int) $kpi["total_completion_tokens"],
                    "avg_latency_ms" => (int) round($kpi["avg_latency_ms"]),
                    "success_rate" => round((float) $kpi["success_rate"], 1)
                ],
                "model_breakdown" => $modelsBreakdown,
                "daily_activity" => $dailyActivity,
                "recent_logs" => $recentLogs
            ]);
            break;

        // Telemetry breakdown for a specific chat
        case "chat_telemetry":
            $chatUuid = $_GET["chat_uuid"] ?? "";
            if (empty($chatUuid)) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Chat UUID required."]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id, user_id, title FROM chats WHERE uuid = ? LIMIT 1");
            $stmt->execute([$chatUuid]);
            $chat = $stmt->fetch();

            if (!$chat || ($chat["user_id"] != $user["id"] && !is_admin())) {
                http_response_code(403);
                echo json_encode(["success" => false, "error" => "Unauthorized access."]);
                exit;
            }

            $stmtLogs = $pdo->prepare("
                SELECT 
                    t.*, m.content as prompt_content, m.image_path
                FROM telemetry t
                LEFT JOIN messages m ON t.message_id = m.id
                WHERE t.chat_id = ?
                ORDER BY t.id ASC
            ");
            $stmtLogs->execute([$chat["id"]]);
            $logs = $stmtLogs->fetchAll();

            $totalCost = 0;
            $totalTokens = 0;
            $totalLatency = 0;
            foreach ($logs as $l) {
                $totalCost += (float)$l["cost"];
                $totalTokens += (int)$l["total_tokens"];
                $totalLatency += (int)$l["latency_ms"];
            }
            $count = count($logs);

            echo json_encode([
                "success" => true,
                "chat_title" => $chat["title"],
                "summary" => [
                    "count" => $count,
                    "total_cost" => $totalCost,
                    "total_tokens" => $totalTokens,
                    "avg_latency_ms" => $count > 0 ? round($totalLatency / $count) : 0
                ],
                "logs" => $logs
            ]);
            break;

        // Export telemetry data as CSV
        case "export_csv":
            $scope = $_GET["scope"] ?? "user";
            if ($scope === "global" && !is_admin()) {
                $scope = "user";
            }
            $userFilter = ($scope === "global") ? "" : "WHERE t.user_id = " . (int)$user["id"];

            $stmt = $pdo->query("
                SELECT 
                    t.id, t.created_at, u.email as user_email, t.model, t.latency_ms,
                    t.prompt_tokens, t.completion_tokens, t.total_tokens, t.cost,
                    t.aspect_ratio, t.status_code, c.title as chat_title
                FROM telemetry t
                LEFT JOIN chats c ON t.chat_id = c.id
                LEFT JOIN users u ON t.user_id = u.id
                {$userFilter}
                ORDER BY t.id DESC
                LIMIT 1000
            ");
            $rows = $stmt->fetchAll();

            header("Content-Type: text/csv");
            header("Content-Disposition: attachment; filename=\"telemetry_export_" . date("Ymd_His") . ".csv\"");
            
            $out = fopen("php://output", "w");
            fputcsv($out, ["ID", "Timestamp", "User Email", "Model", "Latency (ms)", "Prompt Tokens", "Completion Tokens", "Total Tokens", "Cost (USD)", "Aspect Ratio", "Status", "Chat Title"]);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
            exit;

        default:
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid telemetry action."]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
