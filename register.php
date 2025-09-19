<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure'   => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);
require_once 'config/database.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$errors = [];
$success = false;
$verificationSent = false;
$firstName = $lastName = $username = $email = '';
$verificationMethod = '';
$emailError = false;

// Process form when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a verification code submission
    if (isset($_POST['verify_code'])) {
        $submittedCode = implode('', $_POST['verification_code']);
        $storedCode = $_SESSION['verification_code'];
        $verificationMethod = $_SESSION['verification_method'];
        
        if ($submittedCode === $storedCode) {
            // Code matches - complete registration
            $firstName = $_SESSION['reg_data']['firstName'];
            $lastName = $_SESSION['reg_data']['lastName'];
            $username = $_SESSION['reg_data']['username'];
            $email = $_SESSION['reg_data']['email'];
            $password = $_SESSION['reg_data']['password'];
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user into database
            $sql = "INSERT INTO users (username, password_hash, email, full_name, role, is_verified) 
                    VALUES (?, ?, ?, ?, 'citizen', 1)";
            $stmt = $pdo->prepare($sql);
            
            $fullName = $firstName . ' ' . $lastName;
            
            if ($stmt->execute([$username, $passwordHash, $email, $fullName])) {
                $success = true;
                // Clear session data
                unset($_SESSION['verification_code']);
                unset($_SESSION['verification_method']);
                unset($_SESSION['reg_data']);
                unset($_SESSION['verification_pending']);
                
                // Redirect to login after 3 seconds
                header("Refresh: 3; url=login.php");
            } else {
                $errors[] = "Registration failed: " . implode(" ", $stmt->errorInfo());
            }
        } else {
            $_SESSION['verification_error'] = "Invalid verification code. Please try again.";
            header("Location: register.php");
            exit();
        }
    } else {
        // This is the initial registration form submission
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $errors[] = "Invalid form submission";
        } else {
            // Sanitize and validate inputs
            $firstName = trim($_POST['firstName']);
            $lastName = trim($_POST['lastName']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confirmPassword = $_POST['confirmPassword'];
            $verificationMethod = $_POST['verificationMethod'];
            
            // Validate inputs
            if (empty($firstName)) {
                $errors[] = "First name is required";
            } elseif (!preg_match("/^[a-zA-Z-' ]*$/", $firstName)) {
                $errors[] = "First name can only contain letters and spaces";
            }
            
            if (empty($lastName)) {
                $errors[] = "Last name is required";
            } elseif (!preg_match("/^[a-zA-Z-' ]*$/", $lastName)) {
                $errors[] = "Last name can only contain letters and spaces";
            }
            
            if (empty($username)) {
                $errors[] = "Username is required";
            } elseif (!preg_match("/^[a-zA-Z0-9_]{3,20}$/", $username)) {
                $errors[] = "Username must be 3-20 characters and can only contain letters, numbers, and underscores";
            } else {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->rowCount() > 0) {
                    $errors[] = "Username already taken";
                }
            }
            
            if (empty($email)) {
                $errors[] = "Email is required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format";
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) {
                    $errors[] = "Email already registered";
                }
            }
            
            if (empty($password)) {
                $errors[] = "Password is required";
            } elseif (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters long";
            } elseif (!preg_match("/[A-Z]/", $password)) {
                $errors[] = "Password must contain at least one uppercase letter";
            } elseif (!preg_match("/[a-z]/", $password)) {
                $errors[] = "Password must contain at least one lowercase letter";
            } elseif (!preg_match("/[0-9]/", $password)) {
                $errors[] = "Password must contain at least one number";
            } elseif (!preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $password)) {
                $errors[] = "Password must contain at least one special character";
            }
            
            if ($password !== $confirmPassword) {
                $errors[] = "Passwords do not match";
            }
            
            if (empty($verificationMethod)) {
                $errors[] = "Please select a verification method";
            }
            
            // If no errors, proceed with registration
            if (empty($errors)) {
                // Generate verification code
                $verificationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Store data in session for verification step
                $_SESSION['reg_data'] = [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'username' => $username,
                    'email' => $email,
                    'password' => $password
                ];
                
                $_SESSION['verification_code'] = $verificationCode;
                $_SESSION['verification_method'] = $verificationMethod;
                $_SESSION['verification_pending'] = true;
                
                // Send verification code
                if ($verificationMethod === 'email') {
                    // Send verification email using PHPMailer
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
                        $mail->addAddress($email, $firstName . ' ' . $lastName);
                        
                        $mail->isHTML(true);
                        $mail->Subject = 'Verify Your TTM Account';
                        $mail->Body = "
                            <h2>TTM Account Verification</h2>
                            <p>Hello $firstName,</p>
                            <p>Thank you for registering with the Traffic and Transport Management System.</p>
                            <p>Your verification code is: <strong style='font-size: 24px; letter-spacing: 3px;'>$verificationCode</strong></p>
                            <p>Enter this code on the registration page to complete your account setup.</p>
                            <p>If you didn't request this, please ignore this email.</p>
                        ";
                        
                        $mail->send();
                        $verificationSent = true;
                    } catch (Exception $e) {
                        $errors[] = "Failed to send verification email. Please try again.";
                        $emailError = true;
                    }
                } else {
                    // SMS verification would go here (would require SMS API integration)
                    $errors[] = "SMS verification is not available at this time. Please use email verification.";
                }
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if there's a verification error from a previous attempt
if (isset($_SESSION['verification_error'])) {
    $errors[] = $_SESSION['verification_error'];
    unset($_SESSION['verification_error']);
    $verificationSent = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Traffic and Transport Management</title>
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
          
           
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            position: relative;
            backdrop-filter: blur(10px);
     
         
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .password-strength {
            margin-top: 8px;
            height: 6px;
            background: var(--gray-200);
            border-radius: 3px;
            overflow: hidden;
            position: relative;
        }
        
        .password-strength-meter {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 3px;
        }
        
        .password-weak {
            background: #EF4444;
            width: 33%;
        }
        
        .password-medium {
            background: #F59E0B;
            width: 66%;
        }
        
        .password-strong {
            background: #10B981;
            width: 100%;
        }
        
        .password-requirements {
            margin-top: 8px;
            font-size: 11px;
            color: var(--gray-500);
        }
        
        .password-requirements ul {
            padding-left: 16px;
        }
        
        .password-requirements li {
            margin-bottom: 2px;
        }
        
        .password-requirements .valid {
            color: #10B981;
        }
        
        .password-requirements .invalid {
            color: var(--gray-500);
        }
        
        .verification-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .verification-method {
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--gray-50);
        }
        
        .verification-method:hover {
            border-color: var(--primary-blue);
            background: var(--white);
        }
        
        .verification-method.selected {
            border-color: var(--primary-blue);
            background: var(--light-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .verification-method i {
            font-size: 24px;
            color: var(--gray-600);
            margin-bottom: 8px;
        }
        
        .verification-method.selected i {
            color: var(--primary-blue);
        }
        
        .verification-method span {
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .verification-method.selected span {
            color: var(--primary-blue);
        }
        
        .verification-code-input {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
        }
        
        .verification-code-input input {
            width: 40px;
            height: 50px;
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            background: var(--gray-50);
            transition: all 0.2s ease;
        }
        
        .verification-code-input input:focus {
            border-color: var(--primary-blue);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
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
        
        .btn-primary:disabled {
            background: var(--gray-300);
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .btn-primary:disabled:hover {
            transform: none;
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .verification-methods {
                grid-template-columns: 1fr;
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
            
            .verification-code-input input {
                width: 35px;
                height: 45px;
                font-size: 18px;
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
    <img src="img/ttm.png" alt="TTM Logo" style="width: 175px; height: 175px;">
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
            
            <!-- Registration Form Section -->
            <div class="auth-form-section">
                <div class="form-header">
                    <h2 class="form-title">Create Account</h2>
                    <p class="form-subtitle">Join the TTM community today</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <p>Registration successful! Redirecting to login page...</p>
                    </div>
                <?php endif; ?>
                
                <?php if ($verificationSent && !$success): ?>
                    <!-- Verification Code Form -->
                    <form class="auth-form" method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Verification Code</label>
                            <p style="font-size: 13px; color: var(--gray-600); margin-bottom: 12px;">
                                We've sent a 6-digit verification code to your <?php echo $verificationMethod; ?>.
                            </p>
                            <div class="verification-code-input">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                    <input type="text" name="verification_code[]" maxlength="1" 
                                           pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code"
                                           required oninput="moveToNext(this, <?php echo $i; ?>)">
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <button type="submit" name="verify_code" class="btn-primary">
                            Verify & Complete Registration
                        </button>
                    </form>
                <?php elseif (!$success): ?>
                    <!-- Registration Form -->
                    <form class="auth-form" method="POST" action="" id="registrationForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" id="firstName" name="firstName" class="form-input" 
                                       placeholder="Enter your first name" 
                                       value="<?php echo htmlspecialchars($firstName); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" id="lastName" name="lastName" class="form-input" 
                                       placeholder="Enter your last name" 
                                       value="<?php echo htmlspecialchars($lastName); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" name="username" class="form-input" 
                                   placeholder="Choose a username" 
                                   value="<?php echo htmlspecialchars($username); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   placeholder="Enter your email" 
                                   value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-input" 
                                   placeholder="Create a strong password" required>
                            <div class="password-strength">
                                <div class="password-strength-meter" id="passwordStrengthMeter"></div>
                            </div>
                            <div class="password-requirements">
                                <ul>
                                    <li id="req-length" class="invalid">At least 8 characters</li>
                                    <li id="req-upper" class="invalid">One uppercase letter</li>
                                    <li id="req-lower" class="invalid">One lowercase letter</li>
                                    <li id="req-number" class="invalid">One number</li>
                                    <li id="req-special" class="invalid">One special character</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" 
                                   placeholder="Confirm your password" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Verification Method</label>
                            <div class="verification-methods">
                                <div class="verification-method <?php echo $verificationMethod === 'email' ? 'selected' : ''; ?>" 
                                     onclick="selectVerificationMethod('email')">
                                    <input type="radio" name="verificationMethod" value="email" 
                                           <?php echo $verificationMethod === 'email' ? 'checked' : ''; ?> required style="display: none;">
                                    <i class="fas fa-envelope"></i>
                                    <span>Email</span>
                                </div>
                            
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary" id="submitButton">
                            Create Account
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="auth-switch">
                    <span class="auth-switch-text">Already have an account?</span>
                    <a href="login.php" class="auth-switch-link">Sign in</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password strength meter
        const passwordInput = document.getElementById('password');
        const strengthMeter = document.getElementById('passwordStrengthMeter');
        const requirements = {
            length: document.getElementById('req-length'),
            upper: document.getElementById('req-upper'),
            lower: document.getElementById('req-lower'),
            number: document.getElementById('req-number'),
            special: document.getElementById('req-special')
        };
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Check length
            if (password.length >= 8) {
                requirements.length.classList.remove('invalid');
                requirements.length.classList.add('valid');
                strength += 20;
            } else {
                requirements.length.classList.remove('valid');
                requirements.length.classList.add('invalid');
            }
            
            // Check uppercase
            if (/[A-Z]/.test(password)) {
                requirements.upper.classList.remove('invalid');
                requirements.upper.classList.add('valid');
                strength += 20;
            } else {
                requirements.upper.classList.remove('valid');
                requirements.upper.classList.add('invalid');
            }
            
            // Check lowercase
            if (/[a-z]/.test(password)) {
                requirements.lower.classList.remove('invalid');
                requirements.lower.classList.add('valid');
                strength += 20;
            } else {
                requirements.lower.classList.remove('valid');
                requirements.lower.classList.add('invalid');
            }
            
            // Check number
            if (/[0-9]/.test(password)) {
                requirements.number.classList.remove('invalid');
                requirements.number.classList.add('valid');
                strength += 20;
            } else {
                requirements.number.classList.remove('valid');
                requirements.number.classList.add('invalid');
            }
            
            // Check special character
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                requirements.special.classList.remove('invalid');
                requirements.special.classList.add('valid');
                strength += 20;
            } else {
                requirements.special.classList.remove('valid');
                requirements.special.classList.add('invalid');
            }
            
            // Update strength meter
            strengthMeter.className = 'password-strength-meter';
            if (strength <= 20) {
                strengthMeter.classList.add('password-weak');
            } else if (strength <= 60) {
                strengthMeter.classList.add('password-medium');
            } else {
                strengthMeter.classList.add('password-strong');
            }
        });
        
        // Verification method selection
        function selectVerificationMethod(method) {
            document.querySelectorAll('.verification-method').forEach(el => {
                el.classList.remove('selected');
                el.querySelector('input[type="radio"]').checked = false;
            });
            
            const selected = document.querySelector(`.verification-method[onclick="selectVerificationMethod('${method}')"]`);
            selected.classList.add('selected');
            selected.querySelector('input[type="radio"]').checked = true;
        }
        
        // Verification code input navigation
        function moveToNext(input, index) {
            if (input.value.length === 1) {
                if (index < 5) {
                    document.querySelectorAll('input[name="verification_code[]"]')[index + 1].focus();
                } else {
                    input.blur();
                }
            }
        }
        
        // Form validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const verificationMethod = document.querySelector('input[name="verificationMethod"]:checked');
            
            if (!verificationMethod) {
                e.preventDefault();
                alert('Please select a verification method');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }
            
            return true;
        });
        
        // Auto-focus first verification code input
        <?php if ($verificationSent && !$success): ?>
            window.onload = function() {
                document.querySelector('input[name="verification_code[]"]').focus();
            };
        <?php endif; ?>
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>