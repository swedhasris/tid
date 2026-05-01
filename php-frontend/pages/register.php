<?php
/**
 * Register Page - PHP Version
 * Replicates React Register.tsx
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Connect IT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--sn-dark);
            padding: 1rem;
        }
        .register-card {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }
        .register-header {
            background-color: var(--sn-sidebar);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        .register-logo {
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
        }
        .register-form {
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--muted-foreground);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }
        .form-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 0.375rem;
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s;
        }
        .form-input:focus {
            box-shadow: 0 0 0 1px var(--sn-green);
        }
        .btn-register {
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
            margin-top: 1rem;
        }
        .btn-register:hover {
            opacity: 0.9;
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
        .login-link {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.875rem;
            color: var(--muted-foreground);
        }
        .login-link a {
            color: var(--sn-green);
            font-weight: 700;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <!-- Header -->
            <div class="register-header">
                <div class="register-logo">C</div>
                <h1 class="text-2xl font-bold">Create Account</h1>
                <p class="text-sm mt-2" style="color: var(--text-dim);">Join the Connect IT Portal</p>
            </div>

            <!-- Form -->
            <form method="POST" class="register-form">
                <?php if ($error): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input 
                        type="text" 
                        name="name" 
                        class="form-input" 
                        placeholder="John Doe"
                        required
                    />
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="name@company.com"
                        required
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
                        minlength="6"
                    />
                </div>

                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-input">
                        <option value="user">End User</option>
                        <option value="agent">Support Agent</option>
                        <option value="sub_admin">Sub Admin</option>
                        <option value="admin">Administrator</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="ultra_super_admin">Ultra Super Admin</option>
                    </select>
                </div>

                <button type="submit" class="btn-register">
                    Register
                </button>

                <div class="login-link">
                    Already have an account? <a href="<?= BASE_URL ?>/?page=login">Login</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
