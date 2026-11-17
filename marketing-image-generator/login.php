<?php
// login.php
// Modern authentication page for Marketing Image Studio

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password_hash"])) {
            if ($user["status"] === "disabled") {
                $error = "Your account has been deactivated. Please contact an administrator.";
            } else {
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_email"] = $user["email"];
                $_SESSION["user_role"] = $user["role"];

                header("Location: index.php");
                exit;
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Marketing Image Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f5f3ff',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                            700: '#6d28d9',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 antialiased">
    <div class="w-full max-w-md">
        <!-- Logo / Brand Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-brand-600 via-indigo-500 to-pink-500 shadow-xl shadow-indigo-500/20 mb-4 ring-1 ring-white/20">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Marketing Image Studio</h1>
            <p class="text-sm text-slate-400 mt-1">AI-Powered Campaign Visuals & Marketing Telemetry</p>
        </div>

        <!-- Login Card -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-7 shadow-2xl backdrop-blur-xl">
            <?php if (!empty($error)): ?>
                <div class="mb-5 p-3.5 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Email Address</label>
                    <input type="email" name="email" required autocomplete="email" placeholder="name@example.com"
                        class="w-full px-4 py-2.5 bg-slate-950/60 border border-slate-700/80 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition text-sm">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Password</label>
                    </div>
                    <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"
                        class="w-full px-4 py-2.5 bg-slate-950/60 border border-slate-700/80 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition text-sm">
                </div>

                <button type="submit" 
                    class="w-full py-2.5 px-4 bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-semibold rounded-xl shadow-lg shadow-brand-600/25 transition duration-150 flex items-center justify-center gap-2 text-sm">
                    <span>Sign In to Studio</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                </button>
            </form>

            <!-- Quick Demo Credentials Box -->
            <div class="mt-6 pt-5 border-t border-slate-800/80">
                <div class="text-xs font-medium text-slate-400 mb-2 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Click to autofill sample accounts:</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <button type="button" onclick="fillCreds('admin@marketing.local', 'Admin@12345')"
                        class="p-2 bg-slate-950 hover:bg-slate-800/80 border border-slate-800 rounded-lg text-left transition group">
                        <div class="font-bold text-amber-400 flex items-center justify-between">
                            <span>Admin ACL</span>
                            <span class="text-[10px] px-1 bg-amber-400/20 text-amber-300 rounded">Full</span>
                        </div>
                        <div class="text-slate-400 truncate mt-0.5">admin@marketing.local</div>
                    </button>
                    <button type="button" onclick="fillCreds('marketer@marketing.local', 'User@12345')"
                        class="p-2 bg-slate-950 hover:bg-slate-800/80 border border-slate-800 rounded-lg text-left transition group">
                        <div class="font-bold text-indigo-400 flex items-center justify-between">
                            <span>Marketer</span>
                            <span class="text-[10px] px-1 bg-indigo-400/20 text-indigo-300 rounded">User</span>
                        </div>
                        <div class="text-slate-400 truncate mt-0.5">marketer@marketing.local</div>
                    </button>
                </div>
            </div>

            <div class="mt-5 text-center text-xs text-slate-400">
                Don't have an account? 
                <a href="register.php" class="text-brand-400 hover:text-brand-300 font-semibold underline underline-offset-2">Create one</a>
            </div>
        </div>
    </div>

    <script>
        function fillCreds(email, pass) {
            document.querySelector('input[name="email"]').value = email;
            document.querySelector('input[name="password"]').value = pass;
        }
    </script>
</body>
</html>
