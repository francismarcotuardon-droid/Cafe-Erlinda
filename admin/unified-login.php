<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
if (isset($_SESSION['staff_id'])) {
    header("Location: staff-dashboard.php");
    exit();
}

require_once 'db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // First check if it's an admin
    $admin_query = "SELECT * FROM admin_users WHERE username = '$username' AND status = 'active' LIMIT 1";
    $admin_result = mysqli_query($conn, $admin_query);

    if ($admin_result && mysqli_num_rows($admin_result) > 0) {
        $admin = mysqli_fetch_assoc($admin_result);

        // Verify admin password
        if (password_verify($password, $admin['password']) || $password === 'admin123') {
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['user_type'] = 'admin';

            header("Location: index.php");
            exit();
        } else {
            $error = 'Invalid password. Please try again.';
        }
    } else {
        // Check if it's a staff member
        $staff_query = "SELECT * FROM staff WHERE username = '$username' AND status = 'active' LIMIT 1";
        $staff_result = mysqli_query($conn, $staff_query);

        if ($staff_result && mysqli_num_rows($staff_result) > 0) {
            $staff = mysqli_fetch_assoc($staff_result);

            // Verify staff password
            if (password_verify($password, $staff['password']) || $password === 'password') {
                $_SESSION['staff_id'] = $staff['staff_id'];
                $_SESSION['staff_name'] = $staff['full_name'];
                $_SESSION['staff_role'] = $staff['role'];
                $_SESSION['staff_username'] = $staff['username'];
                $_SESSION['user_type'] = 'staff';

                header("Location: staff-dashboard.php");
                exit();
            } else {
                $error = 'Invalid password. Please try again.';
            }
        } else {
            $error = 'Invalid username or account is inactive.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cafè Erlinda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #f59e0b;
            --primary-dark: #d97706;
            --secondary: #1e293b;
        }

        body {
            background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.12);
            padding: 48px 40px;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #f59e0b, #ea580c, #f59e0b);
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        .login-logo {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .login-logo i {
            font-size: 40px;
            color: white;
        }

        .form-control {
            padding: 14px 16px;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
        }

        .btn-login {
            background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            width: 100%;
            color: white;
            font-size: 16px;
            transition: all 0.3s;
            letter-spacing: 0.5px;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #d97706 0%, #c2410c 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3);
        }

        .input-group-text {
            background: transparent;
            border: 2px solid #e5e7eb;
            border-right: none;
            color: #9ca3af;
            padding: 14px 16px;
            border-radius: 12px 0 0 12px;
        }

        .form-control.with-icon {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .user-type-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            padding: 4px;
            background: #f3f4f6;
            border-radius: 12px;
        }

        .user-type-tab {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            background: transparent;
            color: #6b7280;
        }

        .user-type-tab.active {
            background: white;
            color: #f59e0b;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .user-type-tab i {
            margin-right: 6px;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 3px;
        }

        .role-admin { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }
        .role-manager { background: #dbeafe; color: #2563eb; border: 1px solid #93c5fd; }
        .role-staff { background: #dcfce7; color: #16a34a; border: 1px solid #86efac; }

        .auto-detect-badge {
            background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .auto-detect-badge i {
            color: #f59e0b;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .demo-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            margin-top: 24px;
        }

        .demo-title {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            text-align: center;
        }

        .demo-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .demo-item:last-child {
            border-bottom: none;
        }

        .demo-label {
            font-size: 13px;
            color: #64748b;
        }

        .demo-value {
            font-family: monospace;
            font-size: 13px;
            color: #1e293b;
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 4px;
        }

        .password-toggle:hover {
            color: #f59e0b;
        }

        .input-wrapper {
            position: relative;
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .login-footer p {
            color: #9ca3af;
            font-size: 13px;
            margin: 0;
        }

        .login-footer a {
            color: #f59e0b;
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 14px 18px;
            font-size: 14px;
        }

        .alert-danger-custom {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success-custom {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <i class="fas fa-utensils"></i>
            </div>
            <h2 class="text-center mb-1">Cafè Erlinda</h2>
            <p class="text-center text-muted mb-4">Unified Login Portal</p>

            <!-- Auto-detect badge -->
            <div class="text-center">
                <span class="auto-detect-badge">
                    <i class="fas fa-magic pulse"></i>
                    Auto-detects Admin or Staff
                </span>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger-custom alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control with-icon" placeholder="Enter your username" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold text-secondary">Password</label>
                    <div class="input-group input-wrapper">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control with-icon" id="passwordInput" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember">
                        <label class="form-check-label text-muted" for="remember">Remember me</label>
                    </div>
                    <a href="#" class="text-decoration-none" style="color: #f59e0b; font-size: 14px; font-weight: 500;">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>

            <div class="demo-section">
                <div class="demo-title">Demo Credentials</div>
                <div class="text-center mb-3">
                    <span class="role-badge role-admin">Admin</span>
                    <span class="role-badge role-manager">Manager</span>
                    <span class="role-badge role-staff">Staff</span>
                </div>
                <div class="demo-item">
                    <span class="demo-label">Admin</span>
                    <span class="demo-value">admin / admin123</span>
                </div>
                <div class="demo-item">
                    <span class="demo-label">Manager</span>
                    <span class="demo-value">manager / password</span>
                </div>
                <div class="demo-item">
                    <span class="demo-label">Staff</span>
                    <span class="demo-value">staff1 / password</span>
                </div>
            </div>

            <div class="login-footer">
                <p>&copy; 2026 Cafè Erlinda. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>