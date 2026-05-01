<?php
/**
 * Login Page - PHP Version
 * Replicates React Login.tsx
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Connect IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--sn-dark);
            padding: 1rem;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }
        .login-header {
            background-color: var(--sn-sidebar);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        .login-logo {
            width: 4rem;
            height: 4rem;
            background-color: var(--sn-green);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--sn-dark);
            margin: 0 auto 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .login-form {
            padding: 2rem;
            space-y: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted-foreground);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--sn-green);
            box-shadow: 0 0 0 2px rgba(98, 216, 78, 0.2);
        }
        .btn-login {
            width: 100%;
            padding: 1rem;
            background-color: var(--sn-green);
            color: var(--sn-dark);
            font-weight: 700;
            font-size: 1rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .btn-login:hover {
            background-color: rgba(98, 216, 78, 0.9);
        }
        .btn-login:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: var(--muted-foreground);
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-top: 1px solid var(--border);
        }
        .divider span {
            padding: 0 0.75rem;
        }
        .btn-google {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border);
            background: white;
            border-radius: 0.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-google:hover {
            background-color: var(--muted);
        }
        .demo-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px dashed var(--border);
        }
        .demo-title {
            text-align: center;
            font-size: 0.625rem;
            color: var(--muted-foreground);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.75rem;
        }
        .demo-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }
        .btn-demo {
            padding: 0.75rem 0.5rem;
            border: 1px solid;
            border-radius: 0.375rem;
            font-size: 0.625rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-demo-admin {
            background-color: #faf5ff;
            border-color: #e9d5ff;
            color: #7c3aed;
        }
        .btn-demo-admin:hover {
            background-color: #f3e8ff;
        }
        .btn-demo-agent {
            background-color: #eff6ff;
            border-color: #dbeafe;
            color: #2563eb;
        }
        .btn-demo-agent:hover {
            background-color: #dbeafe;
        }
        .btn-demo-user {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
            color: #16a34a;
        }
        .btn-demo-user:hover {
            background-color: #dcfce7;
        }
        .error-message {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        .register-link {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.875rem;
            color: var(--muted-foreground);
        }
        .register-link a {
            color: var(--sn-green);
            font-weight: 700;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="login-logo">C</div>
                <h1 class="text-2xl font-bold">Connect IT</h1>
                <p class="text-sm mt-2" style="color: var(--text-dim);">Sign in to your service portal</p>
            </div>

            <!-- Form -->
            <form method="POST" class="login-form">
                <?php if ($error): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="name@company.com"
                        required
                        autocomplete="email"
                    />
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    />
                </div>

                <button type="submit" class="btn-login">
                    Login
                </button>

                <div class="divider">
                    <span>Or continue with</span>
                </div>

                <button type="button" class="btn-google" onclick="alert('Google Sign-in requires Firebase Auth setup')">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Sign in with Google
                </button>

                <div class="register-link">
                    Don't have an account? <a href="<?= BASE_URL ?>/?page=register">Register</a>
                </div>

                <!-- Demo Login Buttons -->
                <div class="demo-section">
                    <p class="demo-title">Demo Access — No Password Required</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                        <button type="submit" name="demo_login" value="user"
                            style="padding:0.6rem;border:1px solid #d1d5db;border-radius:0.5rem;background:#f9fafb;color:#374151;font-size:0.7rem;font-weight:700;cursor:pointer;">
                            👤 User
                        </button>
                        <button type="submit" name="demo_login" value="agent"
                            style="padding:0.6rem;border:1px solid #bfdbfe;border-radius:0.5rem;background:#eff6ff;color:#1d4ed8;font-size:0.7rem;font-weight:700;cursor:pointer;">
                            🎧 Agent
                        </button>
                        <button type="submit" name="demo_login" value="sub_admin"
                            style="padding:0.6rem;border:1px solid #e9d5ff;border-radius:0.5rem;background:#faf5ff;color:#7c3aed;font-size:0.7rem;font-weight:700;cursor:pointer;">
                            👁 Sub Admin
                        </button>
                        <button type="submit" name="demo_login" value="admin"
                            style="padding:0.6rem;border:1px solid #fed7aa;border-radius:0.5rem;background:#fff7ed;color:#c2410c;font-size:0.7rem;font-weight:700;cursor:pointer;">
                            🛡 Admin
                        </button>
                        <button type="submit" name="demo_login" value="super_admin"
                            style="padding:0.6rem;border:1px solid #fecaca;border-radius:0.5rem;background:#fef2f2;color:#b91c1c;font-size:0.7rem;font-weight:700;cursor:pointer;">
                            👑 Super Admin
                        </button>
                        <button type="submit" name="demo_login" value="ultra_super_admin"
                            style="padding:0.6rem;border:1px solid #fde68a;border-radius:0.5rem;background:linear-gradient(135deg,#fffbeb,#fff7ed);color:#92400e;font-size:0.7rem;font-weight:700;cursor:pointer;">
                            ⚡ Ultra Super
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
