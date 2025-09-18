<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure'   => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);
require_once 'db_connection.php';

$errors = [];
$success = false;
$validToken = false;
$showForm = false;

// Check if token exists in URL
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    // Validate token format (64-character hex string)
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $errors[] = "Invalid token format";
    } else {
        // Check if token exists and is not expired in users table
        $currentDateTime = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("SELECT id, email, 'user' as type FROM users WHERE reset_token = ? AND reset_token_expires > ? 
                               UNION 
                               SELECT id, email, 'employee' as type FROM employees WHERE reset_token = ? AND reset_token_expires > ?");
        $stmt->bind_param("ssss", $token, $currentDateTime, $token, $currentDateTime);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $userId = $user['id'];
            $userType = $user['type'];
            $validToken = true;
            $showForm = true;
            
            // Process password reset form
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'];
                $confirmPassword = $_POST['confirm_password'];
                
                // Validate inputs
                if (empty($password)) {
                    $errors[] = "Password is required";
                } elseif (strlen($password) < 8) {
                    $errors[] = "Password must be at least 8 characters";
                } elseif (!preg_match('/[A-Z]/', $password)) {
                    $errors[] = "Password must contain at least one uppercase letter";
                } elseif (!preg_match('/[a-z]/', $password)) {
                    $errors[] = "Password must contain at least one lowercase letter";
                } elseif (!preg_match('/[0-9]/', $password)) {
                    $errors[] = "Password must contain at least one number";
                }
                
                if ($password !== $confirmPassword) {
                    $errors[] = "Passwords do not match";
                }
                
                if (empty($errors)) {
                    // Update password and clear reset token
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $table = ($userType === 'user') ? 'users' : 'employees';
                    $updateStmt = $conn->prepare("UPDATE $table SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
                    $updateStmt->bind_param("si", $hashedPassword, $userId);
                    
                    if ($updateStmt->execute()) {
                        $success = true;
                        $showForm = false;
                        
                        // Invalidate all existing sessions for this user
                        session_destroy();
                    } else {
                        $errors[] = "Failed to reset password. Please try again.";
                    }
                    $updateStmt->close();
                }
            }
        } else {
            $errors[] = "Invalid or expired reset token";
        }
        $stmt->close();
    }
} else {
    $errors[] = "No reset token provided";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Fire and Rescue Service Management</title>
    <link rel="icon" type="image/png" href="assets/img/frsm-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-red: #DC143C;
            --secondary-red: #B91C3C;
            --accent-red: #EF4444;
            --dark-red: #991B1B;
            --light-red: #FEE2E2;
            --gradient-primary: linear-gradient(135deg, #DC143C 0%, #B91C3C 50%, #991B1B 100%);
            --gradient-secondary: linear-gradient(45deg, #EF4444 0%, #DC143C 100%);
            --dark: #1F2937;
            --light: #F9FAFB;
            --white: #FFFFFF;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gradient-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            position: relative;
        }
        
        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .auth-wrapper {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1.1fr;
            width: 100%;
            max-width: 1000px;
            min-height: 600px;
            backdrop-filter: blur(20px);
            position: relative;
        }
        
        .auth-brand {
            background: var(--gradient-primary);
            padding: 40px 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .auth-brand::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="20" cy="80" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .brand-logo {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            position: relative;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }
        
        .brand-logo i {
            font-size: 56px;
            color: var(--white);
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }
        
        .brand-title {
            font-size: 36px;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 12px;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .brand-subtitle {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.5;
            max-width: 280px;
            font-weight: 400;
        }
        
        .brand-features {
            margin-top: 32px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
        }
        
        .feature-item i {
            width: 18px;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .auth-form-section {
            padding: 40px 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--white);
            position: relative;
        }
        
        .form-header {
            margin-bottom: 32px;
        }
        
        .form-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }
        
        .form-subtitle {
            font-size: 15px;
            color: var(--gray-500);
            font-weight: 400;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 15px;
            font-weight: 400;
            background: var(--gray-50);
            transition: all 0.2s ease;
            color: var(--gray-900);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-red);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1);
        }
        
        .form-input::placeholder {
            color: var(--gray-400);
        }
        
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 38px;
            cursor: pointer;
            color: var(--gray-400);
            font-size: 16px;
            transition: color 0.2s ease;
        }
        
        .password-toggle:hover {
            color: var(--gray-600);
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px 20px;
            background: var(--gradient-secondary);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(220, 20, 60, 0.3);
            position: relative;
            overflow: hidden;
            margin-top: 6px;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(220, 20, 60, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .auth-switch {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-200);
        }
        
        .auth-switch-text {
            color: var(--gray-500);
            font-size: 13px;
        }
        
        .auth-switch-link {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 600;
            margin-left: 4px;
            transition: color 0.2s ease;
        }
        
        .auth-switch-link:hover {
            color: var(--secondary-red);
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
            border-left: 4px solid;
        }
        
        .alert-error {
            background: #FEF2F2;
            color: #991B1B;
            border-left-color: #DC2626;
        }
        
        .alert-success {
            background: #F0FDF4;
            color: #166534;
            border-left-color: #16A34A;
            text-align: center;
        }
        
        .alert-success h3 {
            margin-bottom: 6px;
            font-size: 15px;
        }
        
        .alert-info {
            background: #F0F9FF;
            color: #0C4A6E;
            border-left-color: #0EA5E9;
            text-align: center;
        }
        
        .alert-info h3 {
            margin-bottom: 6px;
            font-size: 15px;
        }
        
        .alert-info a, .alert-success a {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 600;
        }
        
        .alert-info a:hover, .alert-success a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 1024px) {
            .auth-wrapper {
                grid-template-columns: 1fr;
                max-width: 450px;
            }
            
            .auth-brand {
                padding: 32px 24px;
            }
            
            .brand-logo {
                width: 100px;
                height: 100px;
                margin-bottom: 20px;
            }
            
            .brand-logo i {
                font-size: 48px;
            }
            
            .brand-title {
                font-size: 28px;
            }
            
            .brand-features {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 12px;
            }
            
            .auth-wrapper {
                border-radius: 16px;
                min-height: auto;
            }
            
            .auth-brand {
                padding: 24px 20px;
            }
            
            .auth-form-section {
                padding: 32px 20px;
            }
            
            .form-title {
                font-size: 24px;
            }
            
            .brand-title {
                font-size: 24px;
            }
            
            .brand-subtitle {
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .auth-form-section {
                padding: 24px 16px;
            }
            
            .auth-brand {
                padding: 20px 16px;
            }
            
            .form-input {
                padding: 12px 14px;
                font-size: 14px;
            }
            
            .btn-primary {
                padding: 12px 16px;
                font-size: 14px;
            }
            
            .password-toggle {
                top: 36px;
                right: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-wrapper">
            <!-- Branding Section -->
            <div class="auth-brand">
                <div class="brand-logo">
                    <i class="fas fa-fire-flame-curved"></i>
                </div>
                <h1 class="brand-title">FRSM</h1>
                <p class="brand-subtitle">Fire and Rescue Service Management System</p>
                
                <div class="brand-features">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure Password Reset</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-key"></i>
                        <span>Token-Based Security</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-lock"></i>
                        <span>Encrypted Protection</span>
                    </div>
                </div>
            </div>
            
            <!-- Reset Password Form Section -->
            <div class="auth-form-section">
                <div class="form-header">
                    <h2 class="form-title">Reset Password</h2>
                    <p class="form-subtitle">Create a new secure password for your account</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success animate__animated animate__fadeIn">
                        <h3><i class="fas fa-check-circle" style="color: #16A34A; margin-right: 8px;"></i>Password Reset Successful!</h3>
                        <p>Your password has been reset successfully. You can now <a href="login.php">login</a> with your new password.</p>
                    </div>
                <?php elseif ($showForm): ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" id="password" name="password" class="form-input" 
                                   placeholder="Enter new password" required minlength="8">
                            <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                                   placeholder="Confirm new password" required minlength="8">
                            <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            Reset Password
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        <h3><i class="fas fa-exclamation-triangle" style="color: #0EA5E9; margin-right: 8px;"></i>Invalid Reset Link</h3>
                        <p>Invalid or expired password reset link. Please request a new password reset link from the <a href="login.php">login page</a>.</p>
                    </div>
                <?php endif; ?>
                
                <div class="auth-switch">
                    <span class="auth-switch-text">Remember your password?</span>
                    <a href="login.php" class="auth-switch-link">Sign in</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.querySelector('#togglePassword');
        const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
        const password = document.querySelector('#password');
        const confirmPassword = document.querySelector('#confirm_password');
        
        if (togglePassword && password) {
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye-slash');
            });
        }
        
        if (toggleConfirmPassword && confirmPassword) {
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPassword.setAttribute('type', type);
                this.classList.toggle('fa-eye-slash');
            });
        }
        
        // Client-side password validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long');
                    return false;
                }
                
                if (!/[A-Z]/.test(password)) {
                    e.preventDefault();
                    alert('Password must contain at least one uppercase letter');
                    return false;
                }
                
                if (!/[a-z]/.test(password)) {
                    e.preventDefault();
                    alert('Password must contain at least one lowercase letter');
                    return false;
                }
                
                if (!/[0-9]/.test(password)) {
                    e.preventDefault();
                    alert('Password must contain at least one number');
                    return false;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match');
                    return false;
                }
                
                return true;
            });
        }
        
        // Enhanced form interactions
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>