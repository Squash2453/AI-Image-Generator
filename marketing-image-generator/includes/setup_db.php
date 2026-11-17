<?php
// includes/setup_db.php
// Database migration & initial seeder for Marketing Image Generator

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/env.php";

echo "Starting database setup for marketing_ai_db...\n";

// 1. Users table
$pdo->exec("
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✓ Users table ready.\n";

// 2. Models table (with Access Control List)
$pdo->exec("
CREATE TABLE IF NOT EXISTS `models` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `model_id` VARCHAR(150) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `provider` VARCHAR(100) DEFAULT 'OpenRouter',
    `description` TEXT NULL,
    `access_level` ENUM('all', 'admin') NOT NULL DEFAULT 'all',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `cost_per_image` DECIMAL(10, 6) DEFAULT 0.000000,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✓ Models table ready.\n";

// 3. Chats table (Thread conversations)
$pdo->exec("
CREATE TABLE IF NOT EXISTS `chats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `uuid` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL DEFAULT 'New Marketing Campaign',
    `marketing_goal` VARCHAR(100) DEFAULT 'Social Media Ad',
    `default_model` VARCHAR(150) DEFAULT 'recraft/recraft-v4.1-flash-v2:free',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✓ Chats table ready.\n";

// 4. Messages table (prompts, assistant replies, images, continuing history)
$pdo->exec("
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `chat_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` ENUM('user', 'assistant', 'system') NOT NULL,
    `content` TEXT NULL,
    `image_path` VARCHAR(255) NULL,
    `aspect_ratio` VARCHAR(20) DEFAULT '1:1',
    `style_preset` VARCHAR(50) DEFAULT 'general',
    `model_used` VARCHAR(150) NULL,
    `parent_message_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_chat_id` (`chat_id`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✓ Messages table ready.\n";

// 5. Telemetry table (comprehensive metrics for every generation and interaction)
$pdo->exec("
CREATE TABLE IF NOT EXISTS `telemetry` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message_id` INT NULL,
    `chat_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `model` VARCHAR(150) NOT NULL,
    `latency_ms` INT NOT NULL,
    `prompt_tokens` INT DEFAULT 0,
    `completion_tokens` INT DEFAULT 0,
    `total_tokens` INT DEFAULT 0,
    `image_tokens` INT DEFAULT 0,
    `cost` DECIMAL(12, 8) DEFAULT 0.00000000,
    `status_code` INT DEFAULT 200,
    `aspect_ratio` VARCHAR(20) DEFAULT '1:1',
    `meta_json` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_chat` (`chat_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_model` (`model`),
    INDEX `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✓ Telemetry table ready.\n";

// 6. System settings table
$pdo->exec("
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✓ System settings table ready.\n";

// Seed default Admin & Demo User
$adminEmail = "admin@marketing.local";
$adminPass = password_hash("Admin@12345", PASSWORD_DEFAULT);
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$adminEmail]);
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')");
    $stmt->execute(["Administrator", $adminEmail, $adminPass]);
    echo "✓ Seeded default admin account ($adminEmail / Admin@12345).\n";
}

$userEmail = "marketer@marketing.local";
$userPass = password_hash("User@12345", PASSWORD_DEFAULT);
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$userEmail]);
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, 'user', 'active')");
    $stmt->execute(["Marketing Specialist", $userEmail, $userPass]);
    echo "✓ Seeded demo user account ($userEmail / User@12345).\n";
}

// Seed default OpenRouter Models with ACL
$initialModels = [
    [
        "model_id" => "recraft/recraft-v4.1-flash-v2:free",
        "name" => "Recraft v4.1 Flash v2 (Free)",
        "provider" => "Recraft / OpenRouter",
        "description" => "Fast vector and marketing raster generator with high-quality graphic layout.",
        "access_level" => "all",
        "is_active" => 1,
        "is_default" => 1,
        "cost_per_image" => 0.000000
    ],
    [
        "model_id" => "google/gemini-2.5-flash-image",
        "name" => "Google Nano Banana (Gemini 2.5 Flash Image)",
        "provider" => "Google / OpenRouter",
        "description" => "Google multimodal image generator, fast and crisp detail rendering.",
        "access_level" => "all",
        "is_active" => 1,
        "is_default" => 0,
        "cost_per_image" => 0.002000
    ],
    [
        "model_id" => "google/gemini-3.1-flash-image",
        "name" => "Google Nano Banana 2 (Gemini 3.1 Flash Image)",
        "provider" => "Google / OpenRouter",
        "description" => "Next-generation Gemini image engine with rich typography and brand aesthetics.",
        "access_level" => "all",
        "is_active" => 1,
        "is_default" => 0,
        "cost_per_image" => 0.004000
    ],
    [
        "model_id" => "google/gemini-3-pro-image",
        "name" => "Google Nano Banana Pro (Gemini 3 Pro Image)",
        "provider" => "Google / OpenRouter",
        "description" => "Studio-grade pro generation for high-end campaigns and hero graphics.",
        "access_level" => "admin", // Admin-only ACL!
        "is_active" => 1,
        "is_default" => 0,
        "cost_per_image" => 0.015000
    ],
    [
        "model_id" => "openai/gpt-5-image-mini",
        "name" => "OpenAI GPT-5 Image Mini",
        "provider" => "OpenAI / OpenRouter",
        "description" => "Lightweight and versatile marketing imagery from OpenAI.",
        "access_level" => "all",
        "is_active" => 1,
        "is_default" => 0,
        "cost_per_image" => 0.005000
    ],
    [
        "model_id" => "openai/gpt-5-image",
        "name" => "OpenAI GPT-5 Image",
        "provider" => "OpenAI / OpenRouter",
        "description" => "Flagship creative image synthesis for premium advertising artwork.",
        "access_level" => "admin", // Admin-only ACL!
        "is_active" => 1,
        "is_default" => 0,
        "cost_per_image" => 0.020000
    ],
    [
        "model_id" => "black-forest-labs/flux-1-schnell",
        "name" => "FLUX.1 Schnell",
        "provider" => "Black Forest Labs / OpenRouter",
        "description" => "Ultra-fast open-weight photorealistic generation with sharp lighting.",
        "access_level" => "all",
        "is_active" => 1,
        "is_default" => 0,
        "cost_per_image" => 0.003000
    ],
    [
        "model_id" => "black-forest-labs/flux-1-dev",
        "name" => "FLUX.1 Dev",
        "provider" => "Black Forest Labs / OpenRouter",
        "description" => "High-fidelity photorealism, detailed composition, and complex marketing scenes.",
        "access_level" => "admin", // Admin-only ACL!
        "is_active" => 1,
        "is_default" => 0,
        "cost_per_image" => 0.012000
    ]
];

$stmtModel = $pdo->prepare("
    INSERT INTO models (model_id, name, provider, description, access_level, is_active, is_default, cost_per_image)
    VALUES (:model_id, :name, :provider, :description, :access_level, :is_active, :is_default, :cost_per_image)
    ON DUPLICATE KEY UPDATE 
        name = VALUES(name),
        description = VALUES(description),
        provider = VALUES(provider)
");

foreach ($initialModels as $m) {
    $stmtModel->execute($m);
}
echo "✓ Seeded initial OpenRouter models with ACL permissions.\n";

// Seed initial environment & settings
$currentApiKey = getenv("OPENROUTER_API_KEY") ?: "";
$initialSettings = [
    "OPENROUTER_API_KEY" => $currentApiKey,
    "OPENROUTER_BASE_URL" => "https://openrouter.ai/api/v1/images",
    "OPENROUTER_DEFAULT_MODEL" => "recraft/recraft-v4.1-flash-v2:free",
    "OPENROUTER_SITE_NAME" => "Marketing Image Studio",
    "OPENROUTER_SITE_URL" => "http://localhost/AI-Image-Generator/marketing-image-generator",
    "OPENROUTER_TIMEOUT" => "120"
];

// Write to .env and sync with system_settings
update_env_file($initialSettings, $pdo);
echo "✓ Initialized .env and system_settings mapping.\n";

// Ensure uploads folder exists
$uploadsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . "uploads";
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
    file_put_contents($uploadsDir . DIRECTORY_SEPARATOR . ".htaccess", "Options -Indexes\n");
}
echo "✓ Uploads directory ready at: {$uploadsDir}\n";

echo "Setup completed successfully!\n";
