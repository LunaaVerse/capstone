<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure'   => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);
require_once 'config/db_connection.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$errors = [];
$loginInput = '';
$forgotPasswordMessage = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process forgot password form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid form submission";
    } else {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } else {
            // Check if email exists in users table
            $stmt = $pdo->prepare("SELECT user_id, username, email, full_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch();
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiration
                
                // Store token in database
                $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
                $updateStmt->execute([$token, $expires, $email]);
                
                // Send reset email using PHPMailer
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'yannabarrete@gmail.com';
                    $mail->Password = 'cxgy dxgc elfe snau';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    
                    $mail->setFrom('noreply@ttm.com', 'TTM System');
                    $mail->addAddress($email, $user['full_name']);
                    
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - TTM';
                    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
                    $mail->Body = "
                        <h2>Password Reset Request</h2>
                        <p>Hello {$user['full_name']},</p>
                        <p>You requested a password reset for your TTM account. Click the link below to reset your password:</p>
                        <p><a href='$resetLink'>Reset Password</a></p>
                        <p>This link will expire in 1 hour.</p>
                        <p>If you didn't request this, please ignore this email.</p>
                    ";
                    
                    $mail->send();
                    $forgotPasswordMessage = "Password reset link sent to your email";
                } catch (Exception $e) {
                    $forgotPasswordMessage = "Failed to send reset email. Please try again.";
                }
            } else {
                $forgotPasswordMessage = "If an account with that email exists, a reset link has been sent";
            }
        }
    }
}

// Process login form when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid form submission";
    } else {
        // Sanitize and validate inputs
        $loginInput = trim($_POST['loginInput']);
        $password = $_POST['password'];
        $rememberMe = isset($_POST['remember']) ? true : false;
        
        // Validate inputs
        if (empty($loginInput)) {
            $errors[] = "Username or email is required";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required";
        }
        
        // Rate limiting
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
        }
        
        if ($_SESSION['login_attempts'] > 5) {
            $errors[] = "Too many login attempts. Please try again later.";
        }
        
        // If no errors, proceed with login
        if (empty($errors)) {
            // Check in users table
            $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, full_name, role, is_verified 
                                   FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$loginInput, $loginInput]);
            $user = $stmt->fetch();
            
            // Check if we found a user
            if ($user) {
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Check if email is verified
                    if (!$user['is_verified']) {
                        $errors[] = "Please verify your email first. Check your inbox.";
                        $_SESSION['verification_email'] = $user['email'];
                    } else {
                        // Regenerate session ID to prevent fixation
                        session_regenerate_id(true);
                        
                        // Clear any existing session data
                        $_SESSION = [];
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_username'] = $user['username'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['last_activity'] = time();
                        
                        // Set secure remember me cookie if checked
                        if ($rememberMe) {
                            $token = bin2hex(random_bytes(32));
                            $expiry = time() + (86400 * 30); // 30 days
                            
                            // Store hashed token in database
                            $hashedToken = password_hash($token, PASSWORD_DEFAULT);
                            $insertStmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token_hash, expires_at) 
                                         VALUES (?, ?, FROM_UNIXTIME(?))");
                            $insertStmt->execute([$user['user_id'], $hashedToken, $expiry]);
                            
                            setcookie('remember_me', $user['user_id'] . ':' . $token, $expiry, "/", "", true, true);
                        }
                        
                        // Reset login attempts
                        $_SESSION['login_attempts'] = 0;
                        
                        // Redirect based on user role
                        $_SESSION['user_logged_in'] = true;
                        header("Location: admin/dashboard.html");
                        exit();
                    }
                } else {
                    // Increment failed login attempts
                    $_SESSION['login_attempts']++;
                    $errors[] = "Invalid credentials";
                }
            } else {
                $_SESSION['login_attempts']++;
                $errors[] = "Invalid credentials";
            }
        }
    }
}

// Check for remember me cookie
if (empty($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    list($userId, $token) = explode(':', $_COOKIE['remember_me']);
    
    // Validate the token
    $stmt = $pdo->prepare("SELECT users.user_id, users.username, users.email, users.full_name, users.role 
                           FROM users 
                           JOIN auth_tokens ON users.user_id = auth_tokens.user_id 
                           WHERE users.user_id = ? AND auth_tokens.expires_at > NOW()");
    $stmt->execute([$userId]);
    
    if ($stmt->rowCount() === 1) {
        $user = $stmt->fetch();
        
        // Verify token against hashed version in DB
        $tokenCheck = $pdo->prepare("SELECT id FROM auth_tokens 
                                     WHERE user_id = ? AND token_hash = ?");
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        $tokenCheck->execute([$userId, $hashedToken]);
        
        if ($tokenCheck->rowCount() === 1) {
            // Regenerate session ID
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            
            $_SESSION['user_logged_in'] = true;
            header("Location: admin/dashboard.html");
            exit();
        }
    }
    
    // Invalid cookie - delete it
    setcookie('remember_me', '', time() - 3600, "/", "", true, true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Traffic and Transport Management</title>
    <link rel="icon" type="image/png" href="img/ttm-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #2563EB;
            --secondary-blue: #1D4ED8;
            --accent-blue: #3B82F6;
            --dark-blue: #1E40AF;
            --light-blue: #DBEAFE;
            --gradient-primary: linear-gradient(135deg, #2563EB 0%, #1D4ED8 50%, #1E40AF 100%);
            --gradient-secondary: linear-gradient(45deg, #3B82F6 0%, #2563EB 100%);
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
            background: var(--dark-blue);
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
            border-color: var(--primary-blue);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-input::placeholder {
            color: var(--gray-400);
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-blue);
        }
        
        .checkbox-wrapper label {
            font-size: 13px;
            color: var(--gray-600);
            cursor: pointer;
        }
        
        .forgot-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .forgot-link:hover {
            color: var(--secondary-blue);
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
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            position: relative;
            overflow: hidden;
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
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
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
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            margin-left: 4px;
            transition: color 0.2s ease;
        }
        
        .auth-switch-link:hover {
            color: var(--secondary-blue);
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
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
        }
        
        .modal-content {
            background: var(--white);
            margin: 5% auto;
            border-radius: 16px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            background: var(--gradient-primary);
            color: var(--white);
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--white);
            font-size: 20px;
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: background 0.2s ease;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 20px 24px;
            background: var(--gray-50);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-secondary {
            padding: 10px 16px;
            background: var(--gray-200);
            color: var(--gray-700);
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
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
            
            .form-actions {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
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
                    <i class="fas fa-traffic-light"></i>
                </div>
                <h1 class="brand-title">TTM</h1>
                <p class="brand-subtitle">Traffic and Transport Management System</p>
                
                <div class="brand-features">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure Authentication</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-route"></i>
                        <span>Route Optimization</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-car-side"></i>
                        <span>Real-time Traffic Updates</span>
                    </div>
                </div>
            </div>
            
            <!-- Login Form Section -->
            <div class="auth-form-section">
                <div class="form-header">
                    <h2 class="form-title">Welcome Back</h2>
                    <p class="form-subtitle">Sign in to your TTM account</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($forgotPasswordMessage)): ?>
                    <div class="alert <?php echo strpos($forgotPasswordMessage, 'sent') !== false ? 'alert-success' : 'alert-error'; ?>">
                        <p><?php echo htmlspecialchars($forgotPasswordMessage); ?></p>
                    </div>
                <?php endif; ?>
                
                <form class="auth-form" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="loginInput" class="form-label">Username or Email</label>
                        <input type="text" id="loginInput" name="loginInput" class="form-input" 
                               placeholder="Enter your username or email" 
                               value="<?php echo htmlspecialchars($loginInput); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="loginPassword" class="form-label">Password</label>
                        <input type="password" id="loginPassword" name="password" class="form-input" 
                               placeholder="Enter your password" required>
                    </div>
                    
                <!---    <div class="form-actions">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" id="rememberMe" name="remember">
                            <label for="rememberMe">Remember me</label>
                        </div>
                        <a href="#" id="forgotPasswordLink" class="forgot-link">Forgot password?</a>
                    </div> --->
                    
                    <button type="submit" name="login" class="btn-primary">
                        Sign In
                    </button>
                </form>
                
                <div class="auth-switch">
                    <span class="auth-switch-text">Don't have an account?</span>
                    <a href="register.php" class="auth-switch-link">Sign up</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-key" style="margin-right: 8px;"></i>Forgot Password</h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: var(--gray-600); line-height: 1.5;">
                    Enter your email address and we'll send you a link to reset your password.
                </p>
                <form id="forgotPasswordForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="Enter your email" required>
                        <small style="display: block; margin-top: 4px; color: var(--gray-500); font-size: 11px;">
                            We'll never share your email with anyone else.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelForgotPassword">
                    Cancel
                </button>
                <button type="submit" form="forgotPasswordForm" name="forgot_password" class="btn-primary" 
                        style="width: auto; padding: 10px 16px; font-size: 13px;">
                    <i class="fas fa-paper-plane" style="margin-right: 6px;"></i>Send Reset Link
                </button>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const loginInput = document.getElementById('loginInput').value;
            const password = document.getElementById('loginPassword').value;
            
            if (!loginInput || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            return true;
        });

        // Modal functionality
        const modal = document.getElementById("forgotPasswordModal");
        const btn = document.getElementById("forgotPasswordLink");
        const closeBtn = document.querySelector(".modal-close");
        const cancelBtn = document.getElementById("cancelForgotPassword");

        btn.onclick = function(e) {
            e.preventDefault();
            modal.style.display = "block";
            document.body.style.overflow = "hidden";
        }

        function closeModal() {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
        }

        closeBtn.onclick = closeModal;
        cancelBtn.onclick = closeModal;

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
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