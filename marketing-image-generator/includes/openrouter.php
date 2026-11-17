<?php
// includes/openrouter.php
// Pure OpenRouter backend engine with telemetry logging and conversational chat continuation

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/env.php";

class OpenRouterClient {
    private PDO $pdo;
    private string $apiKey;
    private string $baseUrl;
    private string $siteName;
    private string $siteUrl;
    private int $timeout;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->refreshConfig();
    }

    /**
     * Refresh configuration dynamically from environment / .env / system_settings
     */
    public function refreshConfig() {
        $this->apiKey = (string) get_env_setting("OPENROUTER_API_KEY", "", $this->pdo);
        $this->baseUrl = (string) get_env_setting("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1/images", $this->pdo);
        $this->siteName = (string) get_env_setting("OPENROUTER_SITE_NAME", "Marketing Image Studio", $this->pdo);
        $this->siteUrl = (string) get_env_setting("OPENROUTER_SITE_URL", "http://localhost/AI-Image-Generator/marketing-image-generator", $this->pdo);
        $this->timeout = (int) get_env_setting("OPENROUTER_TIMEOUT", 120, $this->pdo);
    }

    /**
     * Test API connection with OpenRouter
     */
    public function testConnection(?string $testKey = null): array {
        $keyToUse = !empty($testKey) ? $testKey : $this->apiKey;
        if (empty($keyToUse)) {
            return [
                "success" => false,
                "message" => "OpenRouter API Key is missing. Please enter and save an API key."
            ];
        }

        $startTime = microtime(true);
        $ch = curl_init("https://openrouter.ai/api/v1/models");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $keyToUse,
                "HTTP-Referer: " . $this->siteUrl,
                "X-Title: " . $this->siteName
            ],
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                "success" => false,
                "latency_ms" => $latencyMs,
                "http_code" => $httpCode,
                "message" => "Connection failed: " . $curlError
            ];
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            $errMsg = $data["error"]["message"] ?? ("HTTP " . $httpCode . ": " . substr($response, 0, 200));
            return [
                "success" => false,
                "latency_ms" => $latencyMs,
                "http_code" => $httpCode,
                "message" => "OpenRouter rejected request: " . $errMsg
            ];
        }

        $totalModels = isset($data["data"]) ? count($data["data"]) : 0;
        return [
            "success" => true,
            "latency_ms" => $latencyMs,
            "http_code" => $httpCode,
            "total_models" => $totalModels,
            "message" => "Connection successful! Verified access to OpenRouter with {$totalModels} models."
        ];
    }

    /**
     * Synthesizes conversational context when continuing a chat on previously generated images
     */
    public function buildConversationalPrompt(string $userPrompt, array $previousMessages, string $marketingGoal = "", string $stylePreset = "", bool $hasReference = false): string {
        // Collect past user prompts and image context to maintain continuity
        $historyPrompts = [];
        foreach ($previousMessages as $msg) {
            if ($msg["role"] === "user" && !empty($msg["content"])) {
                $historyPrompts[] = trim($msg["content"]);
            }
        }

        // Style modifiers
        $styleModifiers = [
            "studio_product" => "Commercial studio product photography, clean minimalist lighting, high-end catalog presentation, 8k sharp focus",
            "social_ad" => "High-converting social media ad visual, bold eye-catching composition, vibrant modern color grading, trendy aesthetic",
            "hero_banner" => "Ultra-wide hero website header graphic, cinematic lighting, negative space for headline typography, professional brand design",
            "luxury_editorial" => "Editorial luxury fashion magazine aesthetic, soft diffused lighting, refined textures, sophisticated mood",
            "neon_tech" => "Futuristic cyberpunk neon glow, dark reflective background, high tech aesthetic, luminescent highlights",
            "isometric_3d" => "Playful 3D isometric render, smooth matte textures, ambient occlusion lighting, modern SaaS illustration style",
            "lifestyle_ugc" => "Authentic lifestyle user-generated content vibe, natural daylight, candid angle, relatable marketing visual",
            "general" => "Professional marketing graphic, polished commercial photography, vibrant advertising composition"
        ];

        $styleText = $styleModifiers[$stylePreset] ?? $styleModifiers["general"];
        $refInstruction = $hasReference ? " [Reference visual provided by user: Incorporate the uploaded product/brand subject into the composition]." : "";

        // If this is continuing an existing chat thread with previous prompts
        if (count($historyPrompts) > 0) {
            $baseContext = implode(" -> ", array_slice($historyPrompts, -3));
            $continuationPrompt = "Iterative marketing visual update. Context and prior design direction: [{$baseContext}].{$refInstruction} Refinement instruction: {$userPrompt}. Maintain brand visual consistency while applying the modifications. Style guidelines: {$styleText}.";
            return $continuationPrompt;
        }

        // First prompt in conversation
        $goalPrefix = !empty($marketingGoal) ? "[{$marketingGoal}] " : "";
        return "{$goalPrefix}{$userPrompt}.{$refInstruction} {$styleText}. High commercial resolution, advertising aesthetic, no unwanted artifacts.";
    }

    /**
     * Generate an image using OpenRouter and record full telemetry
     */
    public function generateMarketingImage(array $params): array {
        $this->refreshConfig();

        if (empty($this->apiKey)) {
            return [
                "success" => false,
                "error" => "OpenRouter API Key is not configured. Please set it in Admin Settings."
            ];
        }

        $userId = (int) $params["user_id"];
        $chatId = (int) $params["chat_id"];
        $model = (string) $params["model"];
        $prompt = (string) $params["prompt"];
        $aspectRatio = (string) ($params["aspect_ratio"] ?? "1:1");
        $stylePreset = (string) ($params["style_preset"] ?? "general");
        $marketingGoal = (string) ($params["marketing_goal"] ?? "Marketing Visual");
        $history = $params["history"] ?? [];
        $referenceImage = $params["reference_image"] ?? null;
        $hasReference = !empty($referenceImage);

        // Synthesize full prompt with conversation history context
        $finalPrompt = $this->buildConversationalPrompt($prompt, $history, $marketingGoal, $stylePreset, $hasReference);

        $payload = [
            "model" => $model,
            "prompt" => $finalPrompt,
            "aspect_ratio" => $aspectRatio,
            "n" => 1
        ];

        // Attach reference image if provided
        if (!empty($referenceImage)) {
            $payload["image"] = $referenceImage;
        }

        $startTime = microtime(true);
        $ch = curl_init($this->baseUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->apiKey,
                "Content-Type: application/json",
                "HTTP-Referer: " . $this->siteUrl,
                "X-Title: " . $this->siteName
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Handle network/curl failure
        if ($response === false) {
            $this->logTelemetry([
                "chat_id" => $chatId,
                "user_id" => $userId,
                "model" => $model,
                "latency_ms" => $latencyMs,
                "prompt_tokens" => 0,
                "completion_tokens" => 0,
                "total_tokens" => 0,
                "image_tokens" => 0,
                "cost" => 0,
                "status_code" => $httpCode ?: 500,
                "aspect_ratio" => $aspectRatio,
                "meta_json" => json_encode(["curl_error" => $curlError, "prompt" => $finalPrompt])
            ]);

            return [
                "success" => false,
                "error" => "Network error connecting to OpenRouter: " . $curlError,
                "latency_ms" => $latencyMs
            ];
        }

        $result = json_decode($response, true);

        // Handle API level error
        if ($httpCode < 200 || $httpCode >= 300) {
            $errMsg = $result["error"]["message"] ?? ("OpenRouter API error (HTTP {$httpCode}): " . substr($response, 0, 300));
            
            $this->logTelemetry([
                "chat_id" => $chatId,
                "user_id" => $userId,
                "model" => $model,
                "latency_ms" => $latencyMs,
                "prompt_tokens" => 0,
                "completion_tokens" => 0,
                "total_tokens" => 0,
                "image_tokens" => 0,
                "cost" => 0,
                "status_code" => $httpCode,
                "aspect_ratio" => $aspectRatio,
                "meta_json" => json_encode(["error" => $errMsg, "raw" => substr($response, 0, 500)])
            ]);

            return [
                "success" => false,
                "error" => $errMsg,
                "latency_ms" => $latencyMs,
                "http_code" => $httpCode
            ];
        }

        // Extract image data
        $b64Data = null;
        $imageUrl = null;
        if (!empty($result["data"][0]["b64_json"])) {
            $b64Data = $result["data"][0]["b64_json"];
        } elseif (!empty($result["data"][0]["url"])) {
            $imageUrl = $result["data"][0]["url"];
        }

        if (empty($b64Data) && empty($imageUrl)) {
            $this->logTelemetry([
                "chat_id" => $chatId,
                "user_id" => $userId,
                "model" => $model,
                "latency_ms" => $latencyMs,
                "prompt_tokens" => 0,
                "completion_tokens" => 0,
                "total_tokens" => 0,
                "image_tokens" => 0,
                "cost" => 0,
                "status_code" => 204,
                "aspect_ratio" => $aspectRatio,
                "meta_json" => json_encode(["error" => "No image data in OpenRouter response", "raw" => substr($response, 0, 300)])
            ]);

            return [
                "success" => false,
                "error" => "OpenRouter did not return an image. Response: " . substr($response, 0, 300),
                "latency_ms" => $latencyMs
            ];
        }

        // Save image to local uploads folder
        $uploadsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . "uploads";
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $extension = "webp";
        if (!empty($result["data"][0]["media_type"])) {
            if (strpos($result["data"][0]["media_type"], "png") !== false) {
                $extension = "png";
            } elseif (strpos($result["data"][0]["media_type"], "jpeg") !== false || strpos($result["data"][0]["media_type"], "jpg") !== false) {
                $extension = "jpg";
            }
        }

        $fileName = "mkt_" . date("Ymd_His") . "_" . bin2hex(random_bytes(6)) . "." . $extension;
        $fullPath = $uploadsDir . DIRECTORY_SEPARATOR . $fileName;
        $savedPath = "uploads/" . $fileName;

        if ($b64Data !== null) {
            $binaryImage = base64_decode($b64Data);
            if ($binaryImage === false || file_put_contents($fullPath, $binaryImage) === false) {
                return [
                    "success" => false,
                    "error" => "Failed to save generated image to disk."
                ];
            }
        } elseif ($imageUrl !== null) {
            $downloadImg = @file_get_contents($imageUrl);
            if ($downloadImg !== false) {
                file_put_contents($fullPath, $downloadImg);
            } else {
                // Use remote URL directly if local download fails
                $savedPath = $imageUrl;
            }
        }

        // Extract usage and telemetry info
        $usage = $result["usage"] ?? [];
        $promptTokens = (int) ($usage["prompt_tokens"] ?? 0);
        $completionTokens = (int) ($usage["completion_tokens"] ?? 0);
        $totalTokens = (int) ($usage["total_tokens"] ?? ($promptTokens + $completionTokens));
        $imageTokens = (int) ($usage["completion_tokens_details"]["image_tokens"] ?? 0);
        $cost = (float) ($usage["cost"] ?? 0.0);

        // If cost is 0 and model has defined cost in DB, lookup cost
        if ($cost <= 0.0) {
            $stmtM = $this->pdo->prepare("SELECT cost_per_image FROM models WHERE model_id = ? LIMIT 1");
            $stmtM->execute([$model]);
            $defCost = (float) $stmtM->fetchColumn();
            if ($defCost > 0.0) {
                $cost = $defCost;
            }
        }

        $telemetryData = [
            "chat_id" => $chatId,
            "user_id" => $userId,
            "model" => $model,
            "latency_ms" => $latencyMs,
            "prompt_tokens" => $promptTokens,
            "completion_tokens" => $completionTokens,
            "total_tokens" => $totalTokens,
            "image_tokens" => $imageTokens,
            "cost" => $cost,
            "status_code" => $httpCode,
            "aspect_ratio" => $aspectRatio,
            "meta_json" => json_encode([
                "prompt_used" => $finalPrompt,
                "style_preset" => $stylePreset,
                "marketing_goal" => $marketingGoal,
                "is_continuation" => count($history) > 0
            ])
        ];

        $telemetryId = $this->logTelemetry($telemetryData);

        return [
            "success" => true,
            "image_path" => $savedPath,
            "synthesized_prompt" => $finalPrompt,
            "telemetry" => array_merge($telemetryData, ["id" => $telemetryId]),
            "marketing_summary" => "Generated {$aspectRatio} marketing visual using {$model} in " . round($latencyMs / 1000, 2) . "s."
        ];
    }

    /**
     * Persist telemetry record to database
     */
    public function logTelemetry(array $data): int {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO telemetry (
                    message_id, chat_id, user_id, model, latency_ms,
                    prompt_tokens, completion_tokens, total_tokens, image_tokens,
                    cost, status_code, aspect_ratio, meta_json
                ) VALUES (
                    :message_id, :chat_id, :user_id, :model, :latency_ms,
                    :prompt_tokens, :completion_tokens, :total_tokens, :image_tokens,
                    :cost, :status_code, :aspect_ratio, :meta_json
                )
            ");

            $stmt->execute([
                ":message_id" => $data["message_id"] ?? null,
                ":chat_id" => $data["chat_id"],
                ":user_id" => $data["user_id"],
                ":model" => $data["model"],
                ":latency_ms" => $data["latency_ms"],
                ":prompt_tokens" => $data["prompt_tokens"] ?? 0,
                ":completion_tokens" => $data["completion_tokens"] ?? 0,
                ":total_tokens" => $data["total_tokens"] ?? 0,
                ":image_tokens" => $data["image_tokens"] ?? 0,
                ":cost" => $data["cost"] ?? 0.0,
                ":status_code" => $data["status_code"] ?? 200,
                ":aspect_ratio" => $data["aspect_ratio"] ?? "1:1",
                ":meta_json" => $data["meta_json"] ?? null
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to log telemetry: " . $e->getMessage());
            return 0;
        }
    }
}
