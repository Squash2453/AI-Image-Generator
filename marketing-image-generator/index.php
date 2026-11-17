<?php
// index.php
// Main Studio Interface for Marketing Image Generator

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/env.php";

require_login();

$user = current_user();
$isAdmin = is_admin();
$csrfToken = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing Image Studio | OpenRouter AI</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js for Telemetry Visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#f5f3ff',
                            100: '#ede9fe',
                            400: '#a78bfa',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                            700: '#6d28d9',
                            800: '#5b21b6',
                            900: '#4c1d95',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            color-scheme: dark;
        }

        /* Custom scrollbars */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
        .tab-active { background-color: rgba(124, 58, 237, 0.15); color: #c4b5fd; border-color: #8b5cf6; }

        /* Ensure all select and option elements adhere to dark theme */
        select {
            color-scheme: dark;
            background-color: #0f172a;
            color: #f8fafc;
        }
        select option {
            background-color: #0f172a !important;
            color: #f8fafc !important;
            padding: 8px 12px;
        }
        select option:checked {
            background-color: #7c3aed !important;
            color: #ffffff !important;
        }
        select option:hover {
            background-color: #1e293b !important;
            color: #ffffff !important;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex overflow-hidden antialiased">

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

    <!-- Image Lightbox Modal -->
    <div id="lightbox-modal" class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md hidden flex items-center justify-center p-4">
        <div class="relative max-w-5xl max-h-[92vh] flex flex-col items-center">
            <button onclick="closeLightbox()" class="absolute -top-12 right-0 p-2 text-slate-400 hover:text-white transition">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <img id="lightbox-img" src="" alt="Full preview" class="max-h-[80vh] w-auto rounded-xl shadow-2xl object-contain border border-slate-800">
            <div class="mt-4 flex items-center gap-4">
                <a id="lightbox-download" href="" download class="px-4 py-2 bg-brand-600 hover:bg-brand-500 text-white rounded-lg text-sm font-semibold flex items-center gap-2 shadow-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Download Full Resolution
                </a>
                <button onclick="copyLightboxUrl()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm font-medium">Copy Link</button>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- LEFT SIDEBAR: App Brand, Chats List, History & Navigation -->
    <!-- ========================================================================= -->
    <aside class="w-80 bg-slate-900/95 border-r border-slate-800 flex flex-col h-screen flex-shrink-0 z-20">
        <!-- Brand Header -->
        <div class="p-4 border-b border-slate-800/80 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-brand-600 via-indigo-500 to-pink-500 flex items-center justify-center shadow-lg shadow-indigo-500/20 ring-1 ring-white/10">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white tracking-tight leading-none">Marketing Studio</h2>
                    <span class="text-[11px] text-slate-400 font-medium">OpenRouter Backend</span>
                </div>
            </div>
            <!-- Role Badge -->
            <span class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded-md <?= $isAdmin ? 'bg-amber-400/10 text-amber-300 border border-amber-400/20' : 'bg-indigo-400/10 text-indigo-300 border border-indigo-400/20' ?>">
                <?= htmlspecialchars($user["role"]) ?>
            </span>
        </div>

        <!-- New Campaign Chat Action -->
        <div class="p-3">
            <button onclick="startNewChat()" 
                class="w-full py-2.5 px-3 bg-brand-600 hover:bg-brand-500 text-white rounded-xl text-sm font-semibold flex items-center justify-center gap-2 shadow-lg shadow-brand-600/20 transition group">
                <svg class="w-4 h-4 transition-transform group-hover:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>New Campaign Chat</span>
            </button>
        </div>

        <!-- Chat History Threads (Requirement 5: Continue chats of previously generated images) -->
        <div class="px-3 pt-2 pb-1 flex items-center justify-between text-xs text-slate-400 font-semibold tracking-wider uppercase">
            <span>Previous Chats</span>
            <span id="chat-count-badge" class="px-1.5 py-0.5 rounded-full bg-slate-800 text-[10px] text-slate-400 font-normal">0</span>
        </div>

        <!-- Search filter for chats -->
        <div class="px-3 pb-2">
            <input type="text" id="chat-search" oninput="filterChats()" placeholder="Filter campaigns..."
                class="w-full px-3 py-1.5 bg-slate-950/70 border border-slate-800 rounded-lg text-xs text-slate-300 placeholder-slate-500 focus:outline-none focus:border-brand-500">
        </div>

        <!-- Chat List Container -->
        <div id="chats-list" class="flex-1 overflow-y-auto px-3 space-y-1.5">
            <div class="py-8 text-center text-xs text-slate-500">Loading campaign chats...</div>
        </div>

        <!-- Bottom Sidebar Navigation -->
        <div class="p-3 border-t border-slate-800/80 bg-slate-950/50 space-y-1">
            <button onclick="switchView('chat')" id="nav-chat"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800/60 transition tab-active">
                <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                <span>Chat Workspace</span>
            </button>

            <button onclick="switchView('telemetry')" id="nav-telemetry"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800/60 transition">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                <span>Telemetry & Analytics</span>
            </button>

            <?php if ($isAdmin): ?>
                <button onclick="switchView('acl')" id="nav-acl"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800/60 transition">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    <span>Model & User ACL</span>
                </button>

                <button onclick="switchView('settings')" id="nav-settings"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800/60 transition">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <span>OpenRouter .ENV Setup</span>
                </button>
            <?php endif; ?>

            <!-- User footer / Logout -->
            <div class="pt-2 mt-2 border-t border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2 overflow-hidden">
                    <div class="w-7 h-7 rounded-full bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-300">
                        <?= strtoupper(substr($user["name"], 0, 1)) ?>
                    </div>
                    <div class="truncate text-xs">
                        <div class="font-medium text-slate-200 truncate"><?= htmlspecialchars($user["name"]) ?></div>
                        <div class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($user["email"]) ?></div>
                    </div>
                </div>
                <a href="logout.php" title="Sign Out" class="p-1.5 text-slate-400 hover:text-red-400 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- ========================================================================= -->
    <!-- MAIN CONTENT VIEW AREA -->
    <!-- ========================================================================= -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-950 relative">

        <!-- --------------------------------------------------------------------- -->
        <!-- VIEW 1: CHAT WORKSPACE (GENERATION & CONTINUATION INTERFACE) -->
        <!-- --------------------------------------------------------------------- -->
        <section id="view-chat" class="flex-1 flex flex-col h-full overflow-hidden">
            <!-- Chat Header -->
            <header class="h-16 px-6 border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-md flex items-center justify-between flex-shrink-0 z-10">
                <div class="flex items-center gap-3">
                    <h1 id="active-chat-title" class="text-base font-bold text-white tracking-tight cursor-pointer hover:underline" title="Click to rename" onclick="promptRenameChat()">
                        New Marketing Campaign
                    </h1>
                    <span id="active-chat-badge" class="px-2 py-0.5 rounded-full text-[11px] bg-slate-800 text-slate-300 border border-slate-700/60">
                        Ready
                    </span>
                </div>

                <!-- Chat Controls: Model ACL selector, Session Telemetry Pill -->
                <div class="flex items-center gap-3">
                    <!-- Session Telemetry Pill (Requirement 2) -->
                    <div id="session-telemetry-badge" class="hidden sm:flex items-center gap-2 px-3 py-1 bg-slate-800/80 border border-slate-700/70 rounded-lg text-xs text-slate-300" title="Telemetry for current chat session">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span id="telemetry-summary-text">0 calls | $0.00</span>
                        <button onclick="openChatTelemetryDrawer()" class="ml-1 text-slate-400 hover:text-white underline text-[11px]">Details</button>
                    </div>

                    <!-- Model ACL Selector (Requirement 3: Model ACL) -->
                    <div class="flex items-center gap-1.5">
                        <label class="text-xs text-slate-400 hidden md:inline">Model:</label>
                        <select id="model-select" class="px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200 focus:outline-none focus:border-brand-500 font-medium cursor-pointer">
                            <option value="">Loading models...</option>
                        </select>
                    </div>
                </div>
            </header>

            <!-- Chat Messages Scroll Container -->
            <div id="messages-container" class="flex-1 overflow-y-auto p-6 space-y-6">
                <!-- Empty state shown when no messages yet -->
                <div id="empty-state" class="max-w-2xl mx-auto py-12 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-brand-600/10 border border-brand-500/20 text-brand-400 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Create High-Impact Marketing Visuals</h3>
                    <p class="text-sm text-slate-400 mb-8 max-w-md mx-auto">
                        Type a marketing description below. Every generation records full telemetry (latency, tokens, cost) and you can continue the chat to refine previously generated images!
                    </p>

                    <!-- Inspiration Starter Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-left">
                        <button onclick="useInspiration('Vibrant luxury perfume bottle on dark reflective obsidian counter, gold spotlight and floating rose petals, commercial studio photography')" 
                            class="p-4 bg-slate-900/80 hover:bg-slate-800/80 border border-slate-800 rounded-xl transition text-left group">
                            <div class="font-semibold text-xs text-brand-400 group-hover:text-brand-300">E-Commerce Product Spotlight</div>
                            <div class="text-xs text-slate-400 mt-1 line-clamp-2">Luxury perfume bottle on dark reflective obsidian counter, gold spotlight & floating petals</div>
                        </button>

                        <button onclick="useInspiration('Futuristic AI SaaS cloud software dashboard banner, isometric 3D glowing nodes, purple and cyan neon aesthetics')" 
                            class="p-4 bg-slate-900/80 hover:bg-slate-800/80 border border-slate-800 rounded-xl transition text-left group">
                            <div class="font-semibold text-xs text-indigo-400 group-hover:text-indigo-300">Website Hero Header</div>
                            <div class="text-xs text-slate-400 mt-1 line-clamp-2">Futuristic AI SaaS dashboard banner, isometric glowing nodes & neon aesthetic</div>
                        </button>

                        <button onclick="useInspiration('Summer blowout 50% discount retail banner, tropical palm leaves, sunglasses and splash water droplets on vibrant yellow backdrop')" 
                            class="p-4 bg-slate-900/80 hover:bg-slate-800/80 border border-slate-800 rounded-xl transition text-left group">
                            <div class="font-semibold text-xs text-amber-400 group-hover:text-amber-300">Social Promo & Discount Ad</div>
                            <div class="text-xs text-slate-400 mt-1 line-clamp-2">Summer 50% discount retail ad, tropical palm leaves, splash water droplets</div>
                        </button>

                        <button onclick="useInspiration('Artisanal craft coffee bag packaging mock-up on rustic wood table, roasted beans scattered, morning sunbeam')" 
                            class="p-4 bg-slate-900/80 hover:bg-slate-800/80 border border-slate-800 rounded-xl transition text-left group">
                            <div class="font-semibold text-xs text-emerald-400 group-hover:text-emerald-300">Brand Packaging Showcase</div>
                            <div class="text-xs text-slate-400 mt-1 line-clamp-2">Artisanal craft coffee packaging mock-up on rustic wood, scattered beans & morning sunbeam</div>
                        </button>
                    </div>
                </div>

                <!-- Messages will be injected here dynamically -->
                <div id="messages-list" class="space-y-6 max-w-4xl mx-auto"></div>

                <!-- Thinking / Generating Status Bubble -->
                <div id="generating-indicator" class="hidden max-w-4xl mx-auto flex items-start gap-4">
                    <div class="w-8 h-8 rounded-full bg-brand-600/30 border border-brand-500/40 text-brand-300 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-semibold text-slate-200">Generating marketing image via OpenRouter...</span>
                            <span id="generating-timer" class="text-xs font-mono text-brand-400 px-2 py-0.5 rounded bg-brand-500/10">0.0s</span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Processing prompt context and recording execution telemetry...</p>
                    </div>
                </div>
            </div>

            <!-- Chat Input Controls & Prompter -->
            <footer class="p-4 border-t border-slate-800/80 bg-slate-900/80 backdrop-blur-md flex-shrink-0">
                <div class="max-w-4xl mx-auto space-y-3">
                    <!-- Marketing Parameters Bar: Aspect Ratio, Style Preset, Marketing Channel -->
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Aspect Ratio -->
                            <div class="flex items-center gap-1.5 bg-slate-900 px-2.5 py-1.5 rounded-lg border border-slate-700/80 hover:border-slate-600 transition shadow-sm">
                                <span class="text-slate-400 text-[11px] font-medium">Aspect:</span>
                                <select id="aspect-ratio-select" class="bg-slate-900 text-slate-100 font-semibold focus:outline-none cursor-pointer text-xs">
                                    <option value="1:1" selected class="bg-slate-900 text-slate-100">1:1 Square (Instagram Feed)</option>
                                    <option value="16:9" class="bg-slate-900 text-slate-100">16:9 Landscape (Hero Banner)</option>
                                    <option value="9:16" class="bg-slate-900 text-slate-100">9:16 Portrait (Reels / Story)</option>
                                    <option value="4:5" class="bg-slate-900 text-slate-100">4:5 Portrait (Social Feed)</option>
                                    <option value="4:3" class="bg-slate-900 text-slate-100">4:3 Standard</option>
                                </select>
                            </div>

                            <!-- Marketing Visual Style -->
                            <div class="flex items-center gap-1.5 bg-slate-900 px-2.5 py-1.5 rounded-lg border border-slate-700/80 hover:border-slate-600 transition shadow-sm">
                                <span class="text-slate-400 text-[11px] font-medium">Style:</span>
                                <select id="style-preset-select" class="bg-slate-900 text-slate-100 font-semibold focus:outline-none cursor-pointer text-xs">
                                    <option value="general" selected class="bg-slate-900 text-slate-100">Commercial Marketing</option>
                                    <option value="studio_product" class="bg-slate-900 text-slate-100">Studio Product Shot</option>
                                    <option value="social_ad" class="bg-slate-900 text-slate-100">High-Converting Ad</option>
                                    <option value="hero_banner" class="bg-slate-900 text-slate-100">Website Hero Header</option>
                                    <option value="luxury_editorial" class="bg-slate-900 text-slate-100">Luxury Editorial</option>
                                    <option value="neon_tech" class="bg-slate-900 text-slate-100">Cyberpunk Neon Tech</option>
                                    <option value="isometric_3d" class="bg-slate-900 text-slate-100">3D Isometric Render</option>
                                    <option value="lifestyle_ugc" class="bg-slate-900 text-slate-100">Authentic Lifestyle / UGC</option>
                                </select>
                            </div>
                        </div>

                        <!-- Continuation Hint (Requirement 5) -->
                        <span id="continuation-pill" class="hidden sm:inline-flex items-center gap-1 text-[11px] text-slate-400">
                            <svg class="w-3.5 h-3.5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span>Chat continuation active</span>
                        </span>
                    </div>

                    <!-- Input Box Container -->
                    <div id="chat-input-container" class="relative flex flex-col bg-slate-950 border border-slate-800 rounded-2xl shadow-xl focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500 transition">
                        
                        <!-- Attached Image Preview Bar -->
                        <div id="attached-image-preview-bar" class="hidden px-3.5 py-2.5 flex items-center justify-between border-b border-slate-800/80 bg-slate-900/40 rounded-t-2xl">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="relative w-11 h-11 rounded-lg overflow-hidden border border-slate-700 bg-slate-900 flex-shrink-0">
                                    <img id="attached-img-thumb" src="" alt="Attached preview" class="w-full h-full object-cover">
                                </div>
                                <div class="text-xs min-w-0">
                                    <div class="font-semibold text-slate-200 truncate max-w-xs" id="attached-img-name">image.png</div>
                                    <div class="text-[10px] text-slate-400 flex items-center gap-2 mt-0.5">
                                        <span id="attached-img-size">0 KB</span>
                                        <span class="px-1.5 py-0.5 rounded bg-brand-500/20 text-brand-300 font-semibold text-[9px] border border-brand-500/30">Reference Visual Attached</span>
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="removeAttachedImage()" class="p-1.5 text-slate-400 hover:text-red-400 transition rounded-lg hover:bg-slate-800" title="Remove attached image">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <!-- Textarea & Action Controls -->
                        <div class="flex items-end">
                            <textarea id="prompt-input" rows="2" 
                                placeholder="Describe your marketing visual, or ask to refine the previous image (e.g., 'Make the background darker with golden studio lighting')..." 
                                class="w-full bg-transparent px-4 py-3 text-sm text-slate-100 placeholder-slate-500 focus:outline-none resize-none leading-relaxed"></textarea>
                            
                            <div class="p-2 flex items-center gap-1.5">
                                <!-- Hidden File Input for Image Upload -->
                                <input type="file" id="image-upload-input" accept="image/png, image/jpeg, image/webp, image/gif" class="hidden" onchange="handleImageFileSelect(event)">

                                <!-- Upload / Attach Image Button -->
                                <button type="button" onclick="document.getElementById('image-upload-input').click()" id="attach-img-btn"
                                    class="p-2.5 text-slate-400 hover:text-brand-300 hover:bg-slate-900 rounded-xl transition flex items-center justify-center group" title="Upload reference image / product photo">
                                    <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </button>

                                <!-- Send Button -->
                                <button id="send-btn" onclick="sendMessage()" 
                                    class="p-2.5 bg-brand-600 hover:bg-brand-500 text-white rounded-xl shadow-lg shadow-brand-600/30 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center" title="Send (Enter)">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-500 px-1">
                        <span>Press <kbd class="px-1.5 py-0.5 bg-slate-800 rounded text-slate-400">Enter</kbd> to send, <kbd class="px-1.5 py-0.5 bg-slate-800 rounded text-slate-400">Shift+Enter</kbd> for new line</span>
                        <span>Backend: OpenRouter Only</span>
                    </div>
                </div>
            </footer>
        </section>

        <!-- --------------------------------------------------------------------- -->
        <!-- VIEW 2: TELEMETRY & ANALYTICS DASHBOARD (REQUIREMENT 2) -->
        <!-- --------------------------------------------------------------------- -->
        <section id="view-telemetry" class="flex-1 flex flex-col h-full overflow-y-auto p-6 hidden">
            <div class="max-w-6xl mx-auto w-full space-y-6">
                <!-- Header -->
                <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-800">
                    <div>
                        <h2 class="text-xl font-bold text-white tracking-tight">Telemetry & Chat History Analytics</h2>
                        <p class="text-xs text-slate-400 mt-1">Full telemetry for every OpenRouter API call across chat sessions</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if ($isAdmin): ?>
                            <!-- Scope toggle for admins -->
                            <div class="bg-slate-900 border border-slate-800 rounded-lg p-0.5 flex text-xs">
                                <button onclick="setTelemetryScope('user')" id="scope-btn-user" class="px-3 py-1.5 rounded-md font-semibold bg-brand-600 text-white transition">My Usage</button>
                                <button onclick="setTelemetryScope('global')" id="scope-btn-global" class="px-3 py-1.5 rounded-md font-semibold text-slate-400 hover:text-white transition">Global Telemetry</button>
                            </div>
                        <?php endif; ?>
                        <a href="api/telemetry.php?action=export_csv" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-semibold flex items-center gap-1.5 border border-slate-700 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            Export CSV
                        </a>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 bg-slate-900/90 border border-slate-800 rounded-2xl shadow-lg">
                        <div class="text-xs text-slate-400 font-medium">Total Generations</div>
                        <div id="kpi-generations" class="text-2xl font-extrabold text-white mt-1">0</div>
                        <div class="text-[11px] text-emerald-400 mt-1 flex items-center gap-1">
                            <span id="kpi-success-rate">100%</span> success rate
                        </div>
                    </div>
                    <div class="p-4 bg-slate-900/90 border border-slate-800 rounded-2xl shadow-lg">
                        <div class="text-xs text-slate-400 font-medium">Total Spend (USD)</div>
                        <div id="kpi-cost" class="text-2xl font-extrabold text-amber-400 mt-1">$0.000</div>
                        <div class="text-[11px] text-slate-500 mt-1">OpenRouter API Cost</div>
                    </div>
                    <div class="p-4 bg-slate-900/90 border border-slate-800 rounded-2xl shadow-lg">
                        <div class="text-xs text-slate-400 font-medium">Total Tokens Consumed</div>
                        <div id="kpi-tokens" class="text-2xl font-extrabold text-indigo-400 mt-1">0</div>
                        <div class="text-[11px] text-slate-500 mt-1">Prompt + Completion</div>
                    </div>
                    <div class="p-4 bg-slate-900/90 border border-slate-800 rounded-2xl shadow-lg">
                        <div class="text-xs text-slate-400 font-medium">Average Latency</div>
                        <div id="kpi-latency" class="text-2xl font-extrabold text-cyan-400 mt-1">0 ms</div>
                        <div class="text-[11px] text-slate-500 mt-1">Roundtrip response time</div>
                    </div>
                </div>

                <!-- Visual Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="p-5 bg-slate-900/90 border border-slate-800 rounded-2xl shadow-lg">
                        <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-4">Daily Activity (Last 14 Days)</h4>
                        <div class="h-64">
                            <canvas id="chart-daily"></canvas>
                        </div>
                    </div>
                    <div class="p-5 bg-slate-900/90 border border-slate-800 rounded-2xl shadow-lg">
                        <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-4">Model Distribution</h4>
                        <div class="h-64">
                            <canvas id="chart-models"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Generation Audit Log Table -->
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Chat Telemetry Audit Log</h4>
                        <span class="text-xs text-slate-500">Latest 25 API transactions</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-slate-400 border-b border-slate-800 bg-slate-950/40 font-semibold">
                                <tr>
                                    <th class="p-3">Time</th>
                                    <th class="p-3">Campaign / Chat</th>
                                    <th class="p-3">Model</th>
                                    <th class="p-3">Latency</th>
                                    <th class="p-3">Tokens</th>
                                    <th class="p-3">Cost</th>
                                    <th class="p-3">Ratio</th>
                                    <th class="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody id="telemetry-table-body" class="divide-y divide-slate-800 text-slate-300">
                                <tr><td colspan="8" class="p-4 text-center text-slate-500">Loading audit log...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($isAdmin): ?>
            <!-- ----------------------------------------------------------------- -->
            <!-- VIEW 3: ADMIN ACCESS CONTROL LIST (ACL) MANAGEMENT (REQUIREMENT 3) -->
            <!-- ----------------------------------------------------------------- -->
            <section id="view-acl" class="flex-1 flex flex-col h-full overflow-y-auto p-6 hidden">
                <div class="max-w-6xl mx-auto w-full space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-800">
                        <div>
                            <h2 class="text-xl font-bold text-white tracking-tight">Access Control List (ACL) Management</h2>
                            <p class="text-xs text-slate-400 mt-1">Configure role permissions, user access, and OpenRouter model authorization</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="openAddModelModal()" class="px-3 py-2 bg-brand-600 hover:bg-brand-500 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Add OpenRouter Model
                            </button>
                        </div>
                    </div>

                    <!-- Subtabs: Models ACL vs User ACL -->
                    <div class="flex border-b border-slate-800 gap-4 text-xs font-semibold">
                        <button onclick="switchAclTab('models')" id="acl-tab-models" class="pb-2 border-b-2 border-brand-500 text-white">Models Access Control</button>
                        <button onclick="switchAclTab('users')" id="acl-tab-users" class="pb-2 border-b-2 border-transparent text-slate-400 hover:text-white">User Accounts & Roles</button>
                    </div>

                    <!-- TAB 1: MODELS ACL -->
                    <div id="acl-models-container" class="space-y-4">
                        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-lg">
                            <div class="p-4 bg-slate-950/60 border-b border-slate-800 flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Registered OpenRouter Models</span>
                                <span class="text-xs text-slate-400">Control who can access each image generation model</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="text-slate-400 border-b border-slate-800 bg-slate-950/30">
                                        <tr>
                                            <th class="p-3">Model</th>
                                            <th class="p-3">OpenRouter ID</th>
                                            <th class="p-3">Access Level (ACL)</th>
                                            <th class="p-3">Est. Cost / Img</th>
                                            <th class="p-3">Status</th>
                                            <th class="p-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="models-table-body" class="divide-y divide-slate-800 text-slate-300">
                                        <tr><td colspan="6" class="p-4 text-center text-slate-500">Loading models...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: USERS ACL -->
                    <div id="acl-users-container" class="space-y-4 hidden">
                        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-lg">
                            <div class="p-4 bg-slate-950/60 border-b border-slate-800 flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">User Directory & Role Permissions</span>
                                <span class="text-xs text-slate-400">Promote admins or disable accounts</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="text-slate-400 border-b border-slate-800 bg-slate-950/30">
                                        <tr>
                                            <th class="p-3">User</th>
                                            <th class="p-3">Email</th>
                                            <th class="p-3">Role</th>
                                            <th class="p-3">Status</th>
                                            <th class="p-3">Generations</th>
                                            <th class="p-3">Total Spent</th>
                                            <th class="p-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="users-table-body" class="divide-y divide-slate-800 text-slate-300">
                                        <tr><td colspan="7" class="p-4 text-center text-slate-500">Loading users...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ----------------------------------------------------------------- -->
            <!-- VIEW 4: OPENROUTER CONFIGURATION & .ENV MAPPING (REQUIREMENT 4) -->
            <!-- ----------------------------------------------------------------- -->
            <section id="view-settings" class="flex-1 flex flex-col h-full overflow-y-auto p-6 hidden">
                <div class="max-w-4xl mx-auto w-full space-y-6">
                    <div class="pb-4 border-b border-slate-800">
                        <h2 class="text-xl font-bold text-white tracking-tight">OpenRouter .ENV Configuration</h2>
                        <p class="text-xs text-slate-400 mt-1">Configure your backend OpenRouter settings directly from the UI to the environment file</p>
                    </div>

                    <!-- .ENV File Status Info Banner -->
                    <div id="env-status-banner" class="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex items-center justify-between text-xs">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-emerald-400 animate-pulse"></div>
                            <div>
                                <div class="font-bold text-slate-200">Active .env Mapping</div>
                                <div id="env-file-path" class="text-slate-500 font-mono text-[11px] truncate max-w-md">Loading path...</div>
                            </div>
                        </div>
                        <span id="env-writable-badge" class="px-2 py-1 rounded bg-slate-800 text-[11px] text-slate-400">Checking...</span>
                    </div>

                    <!-- Config Form -->
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5">
                        <div class="space-y-4">
                            <!-- API Key -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5 flex items-center justify-between">
                                    <span>OPENROUTER_API_KEY</span>
                                    <span class="text-[11px] text-slate-500 font-normal">Backed by .env</span>
                                </label>
                                <div class="relative flex items-center">
                                    <input type="password" id="cfg-api-key" placeholder="sk-or-v1-..."
                                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-xs font-mono placeholder-slate-600 focus:outline-none focus:border-brand-500">
                                    <button type="button" onclick="toggleKeyVisibility()" class="absolute right-3 text-slate-500 hover:text-slate-300 text-xs">
                                        <span id="key-vis-text">Reveal</span>
                                    </button>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1">Keep empty to retain existing key. Enter a new key to update.</p>
                            </div>

                            <!-- Base Endpoint -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5">OPENROUTER_BASE_URL (Endpoint)</label>
                                <input type="text" id="cfg-base-url" value="https://openrouter.ai/api/v1/images"
                                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-xs font-mono focus:outline-none focus:border-brand-500">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Default Model -->
                                <div>
                                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">OPENROUTER_DEFAULT_MODEL</label>
                                    <input type="text" id="cfg-default-model" value="recraft/recraft-v4.1-flash-v2:free"
                                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-xs font-mono focus:outline-none focus:border-brand-500">
                                </div>

                                <!-- Timeout -->
                                <div>
                                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">OPENROUTER_TIMEOUT (Seconds)</label>
                                    <input type="number" id="cfg-timeout" value="120"
                                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-xs focus:outline-none focus:border-brand-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Site Name -->
                                <div>
                                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">OPENROUTER_SITE_NAME</label>
                                    <input type="text" id="cfg-site-name" value="Marketing Image Studio"
                                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-xs focus:outline-none focus:border-brand-500">
                                </div>

                                <!-- Site URL -->
                                <div>
                                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">OPENROUTER_SITE_URL</label>
                                    <input type="text" id="cfg-site-url" value="http://localhost/AI-Image-Generator/marketing-image-generator"
                                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-xs focus:outline-none focus:border-brand-500">
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-4 border-t border-slate-800 flex flex-wrap items-center justify-between gap-3">
                            <button type="button" onclick="testOpenRouterConnection()" id="btn-test-conn"
                                class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold flex items-center gap-2 border border-slate-700 transition">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                <span>Test Connection Live</span>
                            </button>

                            <button type="button" onclick="saveEnvConfig()" id="btn-save-env"
                                class="px-5 py-2.5 bg-brand-600 hover:bg-brand-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-brand-600/20 flex items-center gap-2 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                                <span>Save & Sync to .env</span>
                            </button>
                        </div>

                        <!-- Live Test Result Output Box -->
                        <div id="test-result-box" class="hidden p-4 rounded-xl border text-xs"></div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <!-- Modal: Add New Model to ACL Catalog -->
    <div id="add-model-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-sm font-bold text-white">Register OpenRouter Model</h3>
                <button onclick="closeAddModelModal()" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-300 font-semibold mb-1">OpenRouter Model ID</label>
                    <input type="text" id="new-model-id" placeholder="e.g. black-forest-labs/flux-1-schnell" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200">
                </div>
                <div>
                    <label class="block text-slate-300 font-semibold mb-1">Display Name</label>
                    <input type="text" id="new-model-name" placeholder="e.g. FLUX.1 Schnell" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-300 font-semibold mb-1">Access Level (ACL)</label>
                        <select id="new-model-acl" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200">
                            <option value="all" class="bg-slate-900 text-slate-100">All Users</option>
                            <option value="admin" class="bg-slate-900 text-slate-100">Admin Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-300 font-semibold mb-1">Estimated Cost / Img ($)</label>
                        <input type="number" step="0.001" id="new-model-cost" value="0.003" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200">
                    </div>
                </div>
                <div>
                    <label class="block text-slate-300 font-semibold mb-1">Description</label>
                    <textarea id="new-model-desc" rows="2" placeholder="Brief marketing visual capabilities..." class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 resize-none"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                <button onclick="closeAddModelModal()" class="px-3 py-1.5 bg-slate-800 text-slate-300 rounded-lg text-xs">Cancel</button>
                <button onclick="submitNewModel()" class="px-4 py-1.5 bg-brand-600 hover:bg-brand-500 text-white rounded-lg text-xs font-semibold">Save to ACL Catalog</button>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- APPLICATION JAVASCRIPT LOGIC -->
    <!-- ========================================================================= -->
    <script>
        // Application State
        const AppState = {
            currentChatUuid: null,
            chats: [],
            models: [],
            currentUserRole: '<?= $user["role"] ?>',
            isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
            telemetryScope: 'user',
            charts: { daily: null, models: null },
            timerInterval: null,
            timerStart: 0,
            attachedImage: null // Holds uploaded reference visual { dataUrl, name, size }
        };

        // Initialize Application
        document.addEventListener('DOMContentLoaded', () => {
            loadPermittedModels();
            loadChatsList();
            setupPromptInputKeybindings();
        });

        // ---------------------------------------------------------------------
        // VIEW SWITCHING
        // ---------------------------------------------------------------------
        function switchView(viewName) {
            const views = ['chat', 'telemetry', 'acl', 'settings'];
            views.forEach(v => {
                const el = document.getElementById(`view-${v}`);
                const nav = document.getElementById(`nav-${v}`);
                if (el) el.classList.add('hidden');
                if (nav) nav.classList.remove('tab-active');
            });

            const activeView = document.getElementById(`view-${viewName}`);
            const activeNav = document.getElementById(`nav-${viewName}`);
            if (activeView) activeView.classList.remove('hidden');
            if (activeNav) activeNav.classList.add('tab-active');

            if (viewName === 'telemetry') {
                loadTelemetryDashboard();
            } else if (viewName === 'acl') {
                loadAllModelsACL();
                loadUsersACL();
            } else if (viewName === 'settings') {
                loadEnvSettings();
            }
        }

        // ---------------------------------------------------------------------
        // TOAST NOTIFICATIONS
        // ---------------------------------------------------------------------
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const bgClass = type === 'success' ? 'bg-emerald-500/90 border-emerald-400' : 'bg-red-500/90 border-red-400';
            toast.className = `p-3 px-4 rounded-xl border text-white text-xs font-semibold shadow-2xl backdrop-blur-md transform transition-all duration-300 translate-y-2 opacity-0 flex items-center gap-2 ${bgClass}`;
            toast.innerHTML = `<span>${escapeHtml(message)}</span>`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.remove('translate-y-2', 'opacity-0');
            }, 10);

            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        // ---------------------------------------------------------------------
        // MODELS & ACL LOADING
        // ---------------------------------------------------------------------
        async function loadPermittedModels() {
            try {
                const res = await fetch('api/acl.php?action=get_available_models');
                const data = await res.json();
                if (data.success) {
                    AppState.models = data.models;
                    renderModelSelectOptions();
                }
            } catch (err) {
                console.error("Failed to load permitted models:", err);
            }
        }

        function renderModelSelectOptions() {
            const select = document.getElementById('model-select');
            if (!select) return;
            select.innerHTML = '';

            if (AppState.models.length === 0) {
                select.innerHTML = '<option value="" class="bg-slate-900 text-slate-100">No models permitted</option>';
                return;
            }

            AppState.models.forEach(m => {
                const opt = document.createElement('option');
                opt.className = 'bg-slate-900 text-slate-100';
                opt.value = m.model_id;
                const costBadge = m.cost_per_image > 0 ? ` ($${parseFloat(m.cost_per_image).toFixed(3)})` : ' (Free)';
                const aclBadge = m.access_level === 'admin' ? ' [Admin ACL]' : '';
                opt.textContent = `${m.name}${costBadge}${aclBadge}`;
                if (m.is_default == 1) opt.selected = true;
                select.appendChild(opt);
            });
        }

        // ---------------------------------------------------------------------
        // CHATS MANAGEMENT (REQUIREMENT 5: Continuing chats)
        // ---------------------------------------------------------------------
        async function loadChatsList() {
            try {
                const res = await fetch('api/chat.php?action=list_chats');
                const data = await res.json();
                if (data.success) {
                    AppState.chats = data.chats;
                    renderChatsSidebar();
                }
            } catch (err) {
                console.error("Failed to load chats:", err);
            }
        }

        function renderChatsSidebar() {
            const container = document.getElementById('chats-list');
            const badge = document.getElementById('chat-count-badge');
            if (badge) badge.textContent = AppState.chats.length;
            if (!container) return;

            if (AppState.chats.length === 0) {
                container.innerHTML = '<div class="py-8 text-center text-xs text-slate-500">No campaigns yet.<br>Click "New Campaign Chat" above!</div>';
                return;
            }

            const searchVal = (document.getElementById('chat-search')?.value || '').toLowerCase();
            const filtered = AppState.chats.filter(c => c.title.toLowerCase().includes(searchVal));

            container.innerHTML = filtered.map(chat => {
                const isActive = chat.uuid === AppState.currentChatUuid;
                const activeClass = isActive ? 'bg-slate-800/90 text-white border-brand-500/50 shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 border-transparent';
                
                return `
                    <div onclick="selectChat('${chat.uuid}')" 
                        class="p-2.5 rounded-xl border ${activeClass} transition cursor-pointer flex items-center justify-between group">
                        <div class="flex items-center gap-2.5 min-w-0">
                            ${chat.last_image ? `
                                <img src="${escapeHtml(chat.last_image)}" class="w-8 h-8 rounded-lg object-cover border border-slate-700 flex-shrink-0" alt="thumb">
                            ` : `
                                <div class="w-8 h-8 rounded-lg bg-slate-950 border border-slate-800 flex items-center justify-center text-slate-500 flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                            `}
                            <div class="min-w-0">
                                <div class="text-xs font-semibold truncate leading-tight">${escapeHtml(chat.title)}</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">${chat.message_count || 0} messages</div>
                            </div>
                        </div>
                        <button onclick="event.stopPropagation(); deleteChat('${chat.uuid}')" title="Delete Campaign"
                            class="opacity-0 group-hover:opacity-100 p-1 hover:text-red-400 text-slate-500 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                `;
            }).join('');
        }

        function filterChats() {
            renderChatsSidebar();
        }

        function startNewChat() {
            AppState.currentChatUuid = null;
            document.getElementById('active-chat-title').textContent = "New Marketing Campaign";
            document.getElementById('messages-list').innerHTML = '';
            document.getElementById('empty-state').classList.remove('hidden');
            document.getElementById('continuation-pill').classList.add('hidden');
            document.getElementById('session-telemetry-badge').classList.add('hidden');
            renderChatsSidebar();
            switchView('chat');
        }

        async function selectChat(uuid) {
            AppState.currentChatUuid = uuid;
            renderChatsSidebar();
            switchView('chat');

            try {
                const res = await fetch(`api/chat.php?action=get_chat&uuid=${encodeURIComponent(uuid)}`);
                const data = await res.json();
                if (data.success) {
                    renderChatConversation(data.chat, data.messages, data.telemetry_summary);
                } else {
                    showToast(data.error, 'error');
                }
            } catch (err) {
                console.error("Error opening chat:", err);
            }
        }

        function renderChatConversation(chat, messages, summary) {
            document.getElementById('active-chat-title').textContent = chat.title;
            const messagesList = document.getElementById('messages-list');
            const emptyState = document.getElementById('empty-state');
            const continuationPill = document.getElementById('continuation-pill');
            const sessionBadge = document.getElementById('session-telemetry-badge');
            const summaryText = document.getElementById('telemetry-summary-text');

            if (messages.length > 0) {
                emptyState.classList.add('hidden');
                continuationPill.classList.remove('hidden');
            } else {
                emptyState.classList.remove('hidden');
                continuationPill.classList.add('hidden');
            }

            // Update session telemetry pill
            if (summary && summary.generations > 0) {
                sessionBadge.classList.remove('hidden');
                summaryText.textContent = `${summary.generations} calls | $${summary.total_cost.toFixed(3)} | ${summary.avg_latency_ms}ms avg`;
            } else {
                sessionBadge.classList.add('hidden');
            }

            messagesList.innerHTML = messages.map(msg => renderMessageBubble(msg)).join('');
            scrollToBottom();
        }

        function renderMessageBubble(msg) {
            if (msg.role === 'user') {
                return `
                    <div class="flex items-start justify-end gap-3">
                        <div class="max-w-xl bg-brand-600 text-white rounded-2xl rounded-tr-sm px-4 py-3 shadow-lg">
                            ${msg.image_path ? `
                                <div class="mb-2.5 rounded-xl overflow-hidden border border-white/20 bg-slate-950/60 shadow-inner">
                                    <div class="px-2.5 py-1 bg-black/40 text-[10px] text-brand-200 font-semibold flex items-center justify-between border-b border-white/10">
                                        <span class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <span>Reference Visual</span>
                                        </span>
                                        <button type="button" onclick="openLightbox('${escapeHtml(msg.image_path)}')" class="hover:underline text-[9px] text-slate-300">View Full</button>
                                    </div>
                                    <img src="${escapeHtml(msg.image_path)}" alt="User reference visual" class="max-h-56 w-auto max-w-full mx-auto object-contain cursor-pointer hover:opacity-95 transition" onclick="openLightbox('${escapeHtml(msg.image_path)}')">
                                </div>
                            ` : ''}
                            <p class="text-xs leading-relaxed whitespace-pre-wrap">${escapeHtml(msg.content)}</p>
                            <div class="mt-2 flex items-center justify-end gap-2 text-[10px] text-brand-200">
                                <span class="bg-brand-700/60 px-1.5 py-0.5 rounded">${msg.aspect_ratio || '1:1'}</span>
                                <span>${msg.created_at ? msg.created_at.substr(11, 5) : ''}</span>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // Assistant message with image & telemetry
                const latencySec = msg.latency_ms ? (msg.latency_ms / 1000).toFixed(2) + 's' : '';
                const costStr = msg.cost ? '$' + parseFloat(msg.cost).toFixed(4) : '$0.00';
                const tokensStr = msg.total_tokens ? msg.total_tokens.toLocaleString() + ' tok' : '';

                return `
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-brand-600 to-indigo-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0 shadow-md">
                            AI
                        </div>
                        <div class="max-w-xl bg-slate-900 border border-slate-800 rounded-2xl rounded-tl-sm p-4 shadow-xl space-y-3">
                            ${msg.image_path ? `
                                <div class="relative group rounded-xl overflow-hidden border border-slate-800 bg-slate-950">
                                    <img src="${escapeHtml(msg.image_path)}" alt="Generated visual" class="w-full h-auto object-contain max-h-96 rounded-xl cursor-pointer" onclick="openLightbox('${escapeHtml(msg.image_path)}')">
                                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3">
                                        <button onclick="openLightbox('${escapeHtml(msg.image_path)}')" class="p-2 bg-slate-900/90 hover:bg-brand-600 text-white rounded-lg text-xs font-semibold flex items-center gap-1 shadow-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            View
                                        </button>
                                        <a href="${escapeHtml(msg.image_path)}" download class="p-2 bg-slate-900/90 hover:bg-brand-600 text-white rounded-lg text-xs font-semibold flex items-center gap-1 shadow-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                            Download
                                        </a>
                                        <button onclick="seedContinuationPrompt()" class="p-2 bg-slate-900/90 hover:bg-indigo-600 text-white rounded-lg text-xs font-semibold flex items-center gap-1 shadow-lg transition" title="Refine this marketing visual">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                            Iterate
                                        </button>
                                    </div>
                                </div>
                            ` : ''}

                            <p class="text-xs text-slate-300 leading-relaxed">${escapeHtml(msg.content)}</p>

                            <!-- TELEMETRY PILL & STATS (Requirement 2: Telemetry of entire chat history) -->
                            <div class="pt-2 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-2 text-[10px] text-slate-400">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded bg-slate-800/90 text-cyan-400 font-mono" title="Execution Latency">⚡ ${latencySec || 'n/a'}</span>
                                    <span class="px-2 py-0.5 rounded bg-slate-800/90 text-indigo-300 font-mono" title="Tokens Consumed">🪙 ${tokensStr || '0 tok'}</span>
                                    <span class="px-2 py-0.5 rounded bg-slate-800/90 text-emerald-400 font-mono" title="Cost in USD">💵 ${costStr}</span>
                                </div>
                                <span class="text-slate-500 font-mono truncate max-w-[140px]" title="${escapeHtml(msg.model_used || '')}">${escapeHtml(msg.model_used || 'OpenRouter')}</span>
                            </div>
                        </div>
                    </div>
                `;
            }
        }

        // ---------------------------------------------------------------------
        // IMAGE ATTACHMENT & UPLOAD HANDLERS
        // ---------------------------------------------------------------------
        function handleImageFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            setAttachedImageFile(file);
        }

        function setAttachedImageFile(file) {
            if (!file.type.startsWith('image/')) {
                showToast("Please choose a valid image file (PNG, JPG, WebP, GIF)", "error");
                return;
            }
            if (file.size > 15 * 1024 * 1024) {
                showToast("Image file size exceeds the 15MB limit.", "error");
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const dataUrl = e.target.result;
                const sizeFormatted = file.size > 1024 * 1024 ? 
                    (file.size / (1024 * 1024)).toFixed(1) + ' MB' : 
                    Math.round(file.size / 1024) + ' KB';

                AppState.attachedImage = {
                    dataUrl: dataUrl,
                    name: file.name,
                    size: sizeFormatted
                };

                // Show preview bar above textarea
                document.getElementById('attached-img-thumb').src = dataUrl;
                document.getElementById('attached-img-name').textContent = file.name;
                document.getElementById('attached-img-size').textContent = sizeFormatted;
                document.getElementById('attached-image-preview-bar').classList.remove('hidden');

                // Visual button highlight
                document.getElementById('attach-img-btn').classList.add('text-brand-300', 'bg-brand-500/20');
                showToast("Reference visual attached!");
            };
            reader.readAsDataURL(file);
        }

        function removeAttachedImage() {
            AppState.attachedImage = null;
            const input = document.getElementById('image-upload-input');
            if (input) input.value = '';
            const previewBar = document.getElementById('attached-image-preview-bar');
            if (previewBar) previewBar.classList.add('hidden');
            const thumb = document.getElementById('attached-img-thumb');
            if (thumb) thumb.src = '';
            const attachBtn = document.getElementById('attach-img-btn');
            if (attachBtn) attachBtn.classList.remove('text-brand-300', 'bg-brand-500/20');
        }

        // ---------------------------------------------------------------------
        // SEND MESSAGE & IMAGE GENERATION
        // ---------------------------------------------------------------------
        async function sendMessage() {
            const promptInput = document.getElementById('prompt-input');
            const prompt = promptInput.value.trim();
            const attachedImg = AppState.attachedImage;

            if (!prompt && !attachedImg) return;
            const finalPrompt = prompt || "Generate a high-converting marketing creative based on this reference visual.";

            const model = document.getElementById('model-select').value;
            const aspectRatio = document.getElementById('aspect-ratio-select').value;
            const stylePreset = document.getElementById('style-preset-select').value;
            const sendBtn = document.getElementById('send-btn');
            const generatingIndicator = document.getElementById('generating-indicator');

            // Optimistically append user message with attached image preview
            document.getElementById('empty-state').classList.add('hidden');
            const messagesList = document.getElementById('messages-list');
            messagesList.innerHTML += renderMessageBubble({
                role: 'user',
                content: finalPrompt,
                image_path: attachedImg ? attachedImg.dataUrl : null,
                aspect_ratio: aspectRatio,
                created_at: new Date().toISOString()
            });

            // Capture attached image for payload and clear input state
            const uploadPayloadData = attachedImg ? attachedImg.dataUrl : null;
            promptInput.value = '';
            removeAttachedImage();

            sendBtn.disabled = true;
            generatingIndicator.classList.remove('hidden');
            startGeneratingTimer();
            scrollToBottom();

            try {
                const payload = {
                    chat_uuid: AppState.currentChatUuid,
                    prompt: finalPrompt,
                    model: model,
                    aspect_ratio: aspectRatio,
                    style_preset: stylePreset,
                    uploaded_image: uploadPayloadData
                };

                const res = await fetch('api/chat.php?action=send_message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                stopGeneratingTimer();
                generatingIndicator.classList.add('hidden');
                sendBtn.disabled = false;

                if (data.success) {
                    AppState.currentChatUuid = data.chat_uuid;
                    document.getElementById('active-chat-title').textContent = data.chat_title;
                    document.getElementById('continuation-pill').classList.remove('hidden');

                    // Append assistant message
                    messagesList.innerHTML += renderMessageBubble(Object.assign({}, data.assistant_message, data.telemetry));
                    scrollToBottom();

                    // Refresh sidebar list to show updated message count & thumbnail
                    loadChatsList();
                    showToast("Marketing image generated successfully!");
                } else {
                    showToast(data.error || "Generation failed", 'error');
                }
            } catch (err) {
                stopGeneratingTimer();
                generatingIndicator.classList.add('hidden');
                sendBtn.disabled = false;
                console.error("Error sending message:", err);
                showToast("Request failed: " + err.message, 'error');
            }
        }

        function startGeneratingTimer() {
            AppState.timerStart = Date.now();
            const timerEl = document.getElementById('generating-timer');
            clearInterval(AppState.timerInterval);
            AppState.timerInterval = setInterval(() => {
                const sec = ((Date.now() - AppState.timerStart) / 1000).toFixed(1);
                timerEl.textContent = `${sec}s`;
            }, 100);
        }

        function stopGeneratingTimer() {
            clearInterval(AppState.timerInterval);
        }

        function scrollToBottom() {
            const container = document.getElementById('messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        function useInspiration(prompt) {
            document.getElementById('prompt-input').value = prompt;
            document.getElementById('prompt-input').focus();
        }

        function seedContinuationPrompt() {
            const input = document.getElementById('prompt-input');
            input.value = "Refine the visual: change background to ";
            input.focus();
        }

        async function deleteChat(uuid) {
            if (!confirm("Are you sure you want to delete this marketing campaign chat?")) return;
            try {
                const res = await fetch('api/chat.php?action=delete_chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ uuid: uuid })
                });
                const data = await res.json();
                if (data.success) {
                    showToast("Campaign chat deleted");
                    if (AppState.currentChatUuid === uuid) {
                        startNewChat();
                    }
                    loadChatsList();
                } else {
                    showToast(data.error, 'error');
                }
            } catch (err) {
                console.error("Error deleting chat:", err);
            }
        }

        async function promptRenameChat() {
            if (!AppState.currentChatUuid) return;
            const currentTitle = document.getElementById('active-chat-title').textContent;
            const newTitle = prompt("Enter new campaign title:", currentTitle);
            if (!newTitle || newTitle.trim() === currentTitle) return;

            try {
                const res = await fetch('api/chat.php?action=rename_chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ uuid: AppState.currentChatUuid, title: newTitle.trim() })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('active-chat-title').textContent = data.title;
                    loadChatsList();
                    showToast("Campaign title updated");
                }
            } catch (err) {
                console.error("Error renaming chat:", err);
            }
        }

        function setupPromptInputKeybindings() {
            const input = document.getElementById('prompt-input');
            const container = document.getElementById('chat-input-container');
            if (!input) return;

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            // Clipboard paste image support (e.g. Ctrl+V screenshot)
            input.addEventListener('paste', (e) => {
                const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items;
                if (!items) return;
                for (let item of items) {
                    if (item.kind === 'file' && item.type.startsWith('image/')) {
                        const file = item.getAsFile();
                        if (file) {
                            setAttachedImageFile(file);
                            showToast("Image pasted from clipboard!");
                            break;
                        }
                    }
                }
            });

            // Drag and drop image support onto input area
            if (container) {
                container.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    container.classList.add('border-brand-500', 'ring-1', 'ring-brand-500');
                });
                container.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    container.classList.remove('border-brand-500', 'ring-1', 'ring-brand-500');
                });
                container.addEventListener('drop', (e) => {
                    e.preventDefault();
                    container.classList.remove('border-brand-500', 'ring-1', 'ring-brand-500');
                    if (e.dataTransfer.files && e.dataTransfer.files[0] && e.dataTransfer.files[0].type.startsWith('image/')) {
                        setAttachedImageFile(e.dataTransfer.files[0]);
                        showToast("Image attached via drag & drop!");
                    }
                });
            }
        }

        // ---------------------------------------------------------------------
        // LIGHTBOX
        // ---------------------------------------------------------------------
        function openLightbox(src) {
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox-download').href = src;
            document.getElementById('lightbox-modal').classList.remove('hidden');
        }

        function closeLightbox() {
            document.getElementById('lightbox-modal').classList.add('hidden');
        }

        function copyLightboxUrl() {
            const url = document.getElementById('lightbox-img').src;
            navigator.clipboard.writeText(url);
            showToast("Image URL copied to clipboard!");
        }

        // ---------------------------------------------------------------------
        // TELEMETRY & ANALYTICS DASHBOARD (REQUIREMENT 2)
        // ---------------------------------------------------------------------
        async function loadTelemetryDashboard() {
            try {
                const res = await fetch(`api/telemetry.php?action=overall_stats&scope=${AppState.telemetryScope}`);
                const data = await res.json();
                if (data.success) {
                    // Update KPI cards
                    document.getElementById('kpi-generations').textContent = data.kpis.total_generations.toLocaleString();
                    document.getElementById('kpi-cost').textContent = '$' + data.kpis.total_cost.toFixed(4);
                    document.getElementById('kpi-tokens').textContent = data.kpis.total_tokens.toLocaleString();
                    document.getElementById('kpi-latency').textContent = data.kpis.avg_latency_ms + ' ms';
                    document.getElementById('kpi-success-rate').textContent = data.kpis.success_rate + '%';

                    // Render charts
                    renderDailyChart(data.daily_activity);
                    renderModelsChart(data.model_breakdown);

                    // Render recent logs table
                    renderTelemetryAuditLog(data.recent_logs);
                }
            } catch (err) {
                console.error("Error loading telemetry dashboard:", err);
            }
        }

        function setTelemetryScope(scope) {
            AppState.telemetryScope = scope;
            const btnUser = document.getElementById('scope-btn-user');
            const btnGlobal = document.getElementById('scope-btn-global');
            if (scope === 'global') {
                btnGlobal.classList.add('bg-brand-600', 'text-white');
                btnGlobal.classList.remove('text-slate-400');
                btnUser.classList.remove('bg-brand-600', 'text-white');
                btnUser.classList.add('text-slate-400');
            } else {
                btnUser.classList.add('bg-brand-600', 'text-white');
                btnUser.classList.remove('text-slate-400');
                btnGlobal.classList.remove('bg-brand-600', 'text-white');
                btnGlobal.classList.add('text-slate-400');
            }
            loadTelemetryDashboard();
        }

        function renderDailyChart(dailyData) {
            const ctx = document.getElementById('chart-daily').getContext('2d');
            if (AppState.charts.daily) AppState.charts.daily.destroy();

            const labels = dailyData.map(d => d.log_date);
            const counts = dailyData.map(d => d.generations);

            AppState.charts.daily = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels.length ? labels : ['Today'],
                    datasets: [{
                        label: 'Generations',
                        data: counts.length ? counts : [0],
                        backgroundColor: '#8b5cf6',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: '#1e293b' }, ticks: { color: '#94a3b8' } },
                        y: { grid: { color: '#1e293b' }, ticks: { color: '#94a3b8', precision: 0 } }
                    }
                }
            });
        }

        function renderModelsChart(modelData) {
            const ctx = document.getElementById('chart-models').getContext('2d');
            if (AppState.charts.models) AppState.charts.models.destroy();

            const labels = modelData.map(m => m.model.split('/').pop());
            const counts = modelData.map(m => m.generations);

            AppState.charts.models = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels.length ? labels : ['No data'],
                    datasets: [{
                        data: counts.length ? counts : [1],
                        backgroundColor: ['#8b5cf6', '#38bdf8', '#34d399', '#f59e0b', '#ec4899', '#6366f1']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 12 } }
                    }
                }
            });
        }

        function renderTelemetryAuditLog(logs) {
            const tbody = document.getElementById('telemetry-table-body');
            if (!tbody) return;

            if (!logs || logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-500">No telemetry records recorded yet.</td></tr>';
                return;
            }

            tbody.innerHTML = logs.map(l => `
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="p-3 text-slate-400">${l.created_at ? l.created_at.substr(11, 8) : ''}</td>
                    <td class="p-3 font-medium text-slate-200 truncate max-w-[160px]">${escapeHtml(l.chat_title || 'Untitled')}</td>
                    <td class="p-3 text-indigo-400 font-mono text-[11px] truncate max-w-[140px]">${escapeHtml(l.model)}</td>
                    <td class="p-3 font-mono text-cyan-400">${l.latency_ms} ms</td>
                    <td class="p-3 font-mono text-slate-300">${l.total_tokens}</td>
                    <td class="p-3 font-mono text-emerald-400">$${parseFloat(l.cost).toFixed(4)}</td>
                    <td class="p-3"><span class="px-1.5 py-0.5 rounded bg-slate-800 text-[10px] text-slate-400">${l.aspect_ratio || '1:1'}</span></td>
                    <td class="p-3"><span class="px-2 py-0.5 rounded text-[10px] font-semibold ${l.status_code >= 200 && l.status_code < 300 ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300'}">${l.status_code}</span></td>
                </tr>
            `).join('');
        }

        // ---------------------------------------------------------------------
        // ADMIN ACL MANAGEMENT (REQUIREMENT 3: Admin & Models ACL)
        // ---------------------------------------------------------------------
        function switchAclTab(tab) {
            const modelsCont = document.getElementById('acl-models-container');
            const usersCont = document.getElementById('acl-users-container');
            const tabM = document.getElementById('acl-tab-models');
            const tabU = document.getElementById('acl-tab-users');

            if (tab === 'models') {
                modelsCont.classList.remove('hidden');
                usersCont.classList.add('hidden');
                tabM.classList.add('border-brand-500', 'text-white');
                tabM.classList.remove('border-transparent', 'text-slate-400');
                tabU.classList.remove('border-brand-500', 'text-white');
                tabU.classList.add('border-transparent', 'text-slate-400');
                loadAllModelsACL();
            } else {
                modelsCont.classList.add('hidden');
                usersCont.classList.remove('hidden');
                tabU.classList.add('border-brand-500', 'text-white');
                tabU.classList.remove('border-transparent', 'text-slate-400');
                tabM.classList.remove('border-brand-500', 'text-white');
                tabM.classList.add('border-transparent', 'text-slate-400');
                loadUsersACL();
            }
        }

        async function loadAllModelsACL() {
            try {
                const res = await fetch('api/acl.php?action=list_all_models');
                const data = await res.json();
                if (data.success) {
                    renderModelsACLTable(data.models);
                }
            } catch (err) {
                console.error("Error loading models ACL:", err);
            }
        }

        function renderModelsACLTable(models) {
            const tbody = document.getElementById('models-table-body');
            if (!tbody) return;

            tbody.innerHTML = models.map(m => `
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="p-3">
                        <div class="font-bold text-slate-200">${escapeHtml(m.name)}</div>
                        <div class="text-[10px] text-slate-500">${escapeHtml(m.description || '')}</div>
                    </td>
                    <td class="p-3 font-mono text-[11px] text-indigo-400">${escapeHtml(m.model_id)}</td>
                    <td class="p-3">
                        <select onchange="updateModelACL(${m.id}, this.value)" 
                            class="px-2 py-1 bg-slate-950 border border-slate-700 rounded text-xs font-semibold ${m.access_level === 'admin' ? 'text-amber-400 border-amber-500/40' : 'text-slate-200'}">
                            <option value="all" ${m.access_level === 'all' ? 'selected' : ''}>All Users</option>
                            <option value="admin" ${m.access_level === 'admin' ? 'selected' : ''}>Admins Only</option>
                        </select>
                    </td>
                    <td class="p-3 font-mono text-emerald-400">$${parseFloat(m.cost_per_image).toFixed(4)}</td>
                    <td class="p-3">
                        <button onclick="toggleModelActive(${m.id}, ${m.is_active ? 0 : 1})" 
                            class="px-2 py-0.5 rounded text-[10px] font-semibold ${m.is_active ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-800 text-slate-500'}">
                            ${m.is_active ? 'Active' : 'Disabled'}
                        </button>
                    </td>
                    <td class="p-3 text-right">
                        ${m.is_default == 1 ? `
                            <span class="px-2 py-0.5 rounded bg-brand-500/20 text-brand-300 text-[10px] font-semibold">Default</span>
                        ` : `
                            <button onclick="setDefaultModel(${m.id})" class="text-xs text-slate-400 hover:text-white underline">Set Default</button>
                        `}
                    </td>
                </tr>
            `).join('');
        }

        async function updateModelACL(modelId, accessLevel) {
            try {
                const res = await fetch('api/acl.php?action=update_model', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: modelId, access_level: accessLevel })
                });
                const data = await res.json();
                if (data.success) {
                    showToast("Model ACL updated: " + accessLevel);
                    loadPermittedModels();
                } else {
                    showToast(data.error, 'error');
                }
            } catch (err) {
                console.error("Error updating model ACL:", err);
            }
        }

        async function toggleModelActive(modelId, newActive) {
            try {
                const res = await fetch('api/acl.php?action=update_model', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: modelId, is_active: newActive })
                });
                const data = await res.json();
                if (data.success) {
                    loadAllModelsACL();
                    loadPermittedModels();
                    showToast("Model status updated");
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function setDefaultModel(modelId) {
            try {
                const res = await fetch('api/acl.php?action=update_model', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: modelId, is_default: 1 })
                });
                const data = await res.json();
                if (data.success) {
                    loadAllModelsACL();
                    loadPermittedModels();
                    showToast("Default model assigned");
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function loadUsersACL() {
            try {
                const res = await fetch('api/acl.php?action=list_users');
                const data = await res.json();
                if (data.success) {
                    renderUsersACLTable(data.users);
                }
            } catch (err) {
                console.error("Error loading users ACL:", err);
            }
        }

        function renderUsersACLTable(users) {
            const tbody = document.getElementById('users-table-body');
            if (!tbody) return;

            tbody.innerHTML = users.map(u => `
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="p-3 font-semibold text-slate-200">${escapeHtml(u.name)}</td>
                    <td class="p-3 text-slate-400 font-mono text-[11px]">${escapeHtml(u.email)}</td>
                    <td class="p-3">
                        <select onchange="updateUserRole(${u.id}, this.value)" 
                            class="px-2 py-1 bg-slate-950 border border-slate-700 rounded text-xs font-semibold ${u.role === 'admin' ? 'text-amber-400' : 'text-indigo-300'}">
                            <option value="user" ${u.role === 'user' ? 'selected' : ''}>User (Marketer)</option>
                            <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Admin (Full ACL)</option>
                        </select>
                    </td>
                    <td class="p-3">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold ${u.status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300'}">${u.status}</span>
                    </td>
                    <td class="p-3 font-mono text-slate-300">${u.total_generations}</td>
                    <td class="p-3 font-mono text-emerald-400">$${parseFloat(u.total_spent).toFixed(4)}</td>
                    <td class="p-3 text-right">
                        <button onclick="toggleUserStatus(${u.id}, '${u.status === 'active' ? 'disabled' : 'active'}')" 
                            class="text-xs ${u.status === 'active' ? 'text-red-400 hover:text-red-300' : 'text-emerald-400 hover:text-emerald-300'} underline">
                            ${u.status === 'active' ? 'Disable' : 'Activate'}
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        async function updateUserRole(userId, newRole) {
            try {
                const res = await fetch('api/acl.php?action=update_user', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, role: newRole })
                });
                const data = await res.json();
                if (data.success) {
                    showToast("User role updated to " + newRole);
                } else {
                    showToast(data.error, 'error');
                    loadUsersACL();
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function toggleUserStatus(userId, newStatus) {
            try {
                const res = await fetch('api/acl.php?action=update_user', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, status: newStatus })
                });
                const data = await res.json();
                if (data.success) {
                    showToast("User status updated to " + newStatus);
                    loadUsersACL();
                } else {
                    showToast(data.error, 'error');
                }
            } catch (err) {
                console.error(err);
            }
        }

        function openAddModelModal() {
            document.getElementById('add-model-modal').classList.remove('hidden');
        }

        function closeAddModelModal() {
            document.getElementById('add-model-modal').classList.add('hidden');
        }

        async function submitNewModel() {
            const modelId = document.getElementById('new-model-id').value.trim();
            const name = document.getElementById('new-model-name').value.trim();
            const accessLevel = document.getElementById('new-model-acl').value;
            const cost = document.getElementById('new-model-cost').value;
            const desc = document.getElementById('new-model-desc').value.trim();

            if (!modelId || !name) {
                alert("Model ID and Name are required.");
                return;
            }

            try {
                const res = await fetch('api/acl.php?action=add_model', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        model_id: modelId,
                        name: name,
                        access_level: accessLevel,
                        cost_per_image: cost,
                        description: desc
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast("Model registered to ACL catalog!");
                    closeAddModelModal();
                    loadAllModelsACL();
                    loadPermittedModels();
                } else {
                    showToast(data.error, 'error');
                }
            } catch (err) {
                console.error(err);
            }
        }

        // ---------------------------------------------------------------------
        // OPENROUTER & .ENV CONFIGURATION (REQUIREMENT 4: UI-TO-ENV MAPPING)
        // ---------------------------------------------------------------------
        async function loadEnvSettings() {
            try {
                const res = await fetch('api/settings.php?action=get_config');
                const data = await res.json();
                if (data.success) {
                    const cfg = data.config;
                    const envInfo = data.env_file;

                    // Set field values
                    document.getElementById('cfg-api-key').placeholder = cfg.api_key_masked || "sk-or-v1-...";
                    document.getElementById('cfg-base-url').value = cfg.base_url;
                    document.getElementById('cfg-default-model').value = cfg.default_model;
                    document.getElementById('cfg-timeout').value = cfg.timeout;
                    document.getElementById('cfg-site-name').value = cfg.site_name;
                    document.getElementById('cfg-site-url').value = cfg.site_url;

                    // File info
                    document.getElementById('env-file-path').textContent = envInfo.path;
                    document.getElementById('env-writable-badge').textContent = envInfo.is_writable ? "Writable & Synced" : "Read-only";
                    document.getElementById('env-writable-badge').className = envInfo.is_writable ? 
                        "px-2 py-1 rounded bg-emerald-500/20 text-emerald-300 text-[11px] font-semibold" : 
                        "px-2 py-1 rounded bg-amber-500/20 text-amber-300 text-[11px] font-semibold";
                }
            } catch (err) {
                console.error("Error loading env settings:", err);
            }
        }

        function toggleKeyVisibility() {
            const input = document.getElementById('cfg-api-key');
            const label = document.getElementById('key-vis-text');
            if (input.type === 'password') {
                input.type = 'text';
                label.textContent = 'Hide';
            } else {
                input.type = 'password';
                label.textContent = 'Reveal';
            }
        }

        async function testOpenRouterConnection() {
            const btn = document.getElementById('btn-test-conn');
            const resBox = document.getElementById('test-result-box');
            const keyVal = document.getElementById('cfg-api-key').value.trim();

            btn.disabled = true;
            btn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Testing connection...`;

            try {
                const res = await fetch('api/settings.php?action=test_connection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ api_key: keyVal })
                });
                const data = await res.json();
                btn.disabled = false;
                btn.innerHTML = `<svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg> Test Connection Live`;

                resBox.classList.remove('hidden');
                if (data.success) {
                    resBox.className = 'p-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 text-xs space-y-1';
                    resBox.innerHTML = `
                        <div class="font-bold flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span>${escapeHtml(data.message)}</span>
                        </div>
                        <div class="text-[11px] text-emerald-400/80">Roundtrip Latency: ${data.latency_ms} ms | Status: ${data.http_code} OK</div>
                    `;
                } else {
                    resBox.className = 'p-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-300 text-xs space-y-1';
                    resBox.innerHTML = `
                        <div class="font-bold flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-red-400"></span>
                            <span>Connection Failed</span>
                        </div>
                        <div class="text-[11px] text-red-400/80">${escapeHtml(data.message)}</div>
                    `;
                }
            } catch (err) {
                btn.disabled = false;
                btn.innerHTML = 'Test Connection Live';
                console.error(err);
            }
        }

        async function saveEnvConfig() {
            const btn = document.getElementById('btn-save-env');
            const apiKey = document.getElementById('cfg-api-key').value.trim();
            const baseUrl = document.getElementById('cfg-base-url').value.trim();
            const defaultModel = document.getElementById('cfg-default-model').value.trim();
            const timeout = document.getElementById('cfg-timeout').value;
            const siteName = document.getElementById('cfg-site-name').value.trim();
            const siteUrl = document.getElementById('cfg-site-url').value.trim();

            btn.disabled = true;
            btn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Syncing to .env...`;

            try {
                const res = await fetch('api/settings.php?action=save_config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        api_key: apiKey,
                        base_url: baseUrl,
                        default_model: defaultModel,
                        timeout: timeout,
                        site_name: siteName,
                        site_url: siteUrl
                    })
                });

                const data = await res.json();
                btn.disabled = false;
                btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg> Save & Sync to .env`;

                if (data.success) {
                    showToast("Saved directly to .env and synced to environment!");
                    loadEnvSettings();
                    loadPermittedModels();
                } else {
                    showToast(data.error, 'error');
                }
            } catch (err) {
                btn.disabled = false;
                console.error(err);
                showToast("Failed to save .env configuration", 'error');
            }
        }

        // Utility: escape HTML
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</body>
</html>
