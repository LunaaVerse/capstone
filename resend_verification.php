<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure'   => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);
require_once 'db_connection.php';

require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';
require 'vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = false;
$email = '';

// Process form when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email exists and is not verified in users table
        $stmt = $conn->prepare("SELECT id, first_name, last_name, 'user' as type FROM users WHERE email = ? AND is_verified = 0 
                               UNION 
                               SELECT id, first_name, last_name, 'employee' as type FROM employees WHERE email = ? AND is_verified = 0");
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate new verification token
            $verificationToken = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiration
            
            // Update verification token in database
            $table = ($user['type'] === 'user') ? 'users' : 'employees';
            $updateStmt = $conn->prepare("UPDATE $table SET verification_token = ?, verification_token_expires = ? WHERE email = ?");
            $updateStmt->bind_param("sss", $verificationToken, $expires, $email);
            $updateStmt->execute();
            
            // Send verification email using PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'Stephenviray12@gmail.com'; // Replace with your email
                $mail->Password = 'bubr nckn tgqf lvus'; // Replace with your app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                $mail->setFrom('noreply@frsm.com', 'FRSM System');
                $mail->addAddress($email, $user['first_name'] . ' ' . $user['last_name']);
                
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your FRSM Account';
                $verificationLink = "http://localhost/lgulogin/verify_email.php?token=" . $verificationToken;
                $mail->Body = "
                    <h2>Welcome to FRSM!</h2>
                    <p>Hello {$user['first_name']},</p>
                    <p>Please click the link below to verify your email address:</p>
                    <p><a href='$verificationLink'>Verify Email Address</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't create an account, please ignore this email.</p>
                ";
                
                $mail->send();
                $success = true;
            } catch (Exception $e) {
                $errors[] = "Failed to send verification email: " . $mail->ErrorInfo;
            }
            $updateStmt->close();
        } else {
            // Don't reveal if email exists or not for security
            $success = true;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification - Fire and Rescue Service Management</title>
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
                        <i class="fas fa-envelope"></i>
                        <span>Email Verification</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure Authentication</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        <span>Quick Resend</span>
                    </div>
                </div>
            </div>
            
            <!-- Resend Verification Form Section -->
            <div class="auth-form-section">
                <div class="form-header">
                    <h2 class="form-title">Resend Verification</h2>
                    <p class="form-subtitle">Enter your email to receive a new verification link</p>
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
                        <h3><i class="fas fa-check-circle" style="color: #16A34A; margin-right: 8px;"></i>Verification Email Sent!</h3>
                        <p>If an unverified account exists with that email, a new verification link has been sent. Please check your inbox and spam folder.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   placeholder="Enter your email address" 
                                   value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-paper-plane" style="margin-right: 8px;"></i>
                            Resend Verification Email
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="auth-switch">
                    <span class="auth-switch-text">Already verified?</span>
                    <a href="login.php" class="auth-switch-link">Sign in</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            
            if (!email) {
                e.preventDefault();
                alert('Please enter your email address');
                return false;
            }
            
            if (!email.includes('@') || !email.includes('.')) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
            
            return true;
        });

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