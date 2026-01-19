<?php
/**
 * Email Helper Functions
 * ELMS - Electronic Learning Management System
 * Uses PHPMailer for sending emails via Gmail SMTP
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Get security setting value from database
 */
function get_security_setting($key, $default = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM security_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Update security setting value
 */
function update_security_setting($key, $value, $user_id = null) {
    global $conn;
    $stmt = $conn->prepare("UPDATE security_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
    $stmt->bind_param("sis", $value, $user_id, $key);
    return $stmt->execute();
}

/**
 * Validate if email exists using DNS MX record check
 */
function validate_email_exists($email) {
    // Basic format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Invalid email format'];
    }
    
    // Extract domain
    $domain = substr(strrchr($email, "@"), 1);
    
    // Check MX records
    if (!checkdnsrr($domain, "MX")) {
        // Fallback to A record check
        if (!checkdnsrr($domain, "A")) {
            return ['valid' => false, 'message' => 'Email domain does not exist'];
        }
    }
    
    return ['valid' => true, 'message' => 'Email appears valid'];
}

/**
 * Initialize PHPMailer with SMTP settings from database
 */
function get_mailer() {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = get_security_setting('smtp_host', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = get_security_setting('smtp_username', '');
        $mail->Password = get_security_setting('smtp_password', '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)get_security_setting('smtp_port', 587);
        
        // Sender info
        $from_email = get_security_setting('smtp_from_email', '');
        $from_name = get_security_setting('smtp_from_name', 'ELMS System');
        
        if (!empty($from_email)) {
            $mail->setFrom($from_email, $from_name);
        }
        
        // Content settings
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        return $mail;
    } catch (Exception $e) {
        error_log("Mailer initialization error: " . $e->getMessage());
        return null;
    }
}

/**
 * Send email and log the result
 */
function send_email($to_email, $subject, $body, $template_type = 'general', $sent_by = null) {
    global $conn;
    
    // Validate email first
    $validation = validate_email_exists($to_email);
    if (!$validation['valid']) {
        log_email($to_email, $subject, $template_type, 'failed', $validation['message'], $sent_by);
        return ['success' => false, 'message' => $validation['message']];
    }
    
    $mail = get_mailer();
    if (!$mail) {
        log_email($to_email, $subject, $template_type, 'failed', 'Failed to initialize mailer', $sent_by);
        return ['success' => false, 'message' => 'Email service not configured'];
    }
    
    try {
        $mail->addAddress($to_email);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
        
        $mail->send();
        log_email($to_email, $subject, $template_type, 'sent', null, $sent_by);
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        $error = $mail->ErrorInfo;
        log_email($to_email, $subject, $template_type, 'failed', $error, $sent_by);
        return ['success' => false, 'message' => 'Failed to send email: ' . $error];
    }
}

/**
 * Log email to database
 */
function log_email($recipient, $subject, $template_type, $status, $error = null, $sent_by = null) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO email_logs (recipient_email, subject, template_type, status, error_message, sent_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $recipient, $subject, $template_type, $status, $error, $sent_by);
    $stmt->execute();
}

/**
 * Generate account creation email HTML
 */
function generate_account_email($first_name, $last_name, $email, $password, $role_name, $login_url = null) {
    $login_url = $login_url ?: BASE_URL;
    $site_name = SITE_NAME;
    $logo_url = BASE_URL . 'assets/image/datamexlogo.png';
    $year = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {$site_name}</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa; line-height: 1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f7fa; padding: 40px 20px;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);">
                    
                    <!-- Header with Logo -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #003366 0%, #004488 50%, #800000 100%); padding: 40px 30px; text-align: center;">
                            <img src="{$logo_url}" alt="Datamex Logo" style="max-width: 120px; height: auto; margin-bottom: 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">Welcome to ELMS</h1>
                            <p style="color: rgba(255, 255, 255, 0.9); margin: 10px 0 0 0; font-size: 15px;">Electronic Learning Management System</p>
                        </td>
                    </tr>
                    
                    <!-- Success Badge -->
                    <tr>
                        <td style="padding: 30px 40px 0 40px; text-align: center;">
                            <div style="display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 8px 20px; border-radius: 50px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                ✓ Account Created Successfully
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Greeting -->
                    <tr>
                        <td style="padding: 30px 40px 20px 40px;">
                            <p style="color: #1a1a2e; margin: 0 0 15px 0; font-size: 16px;">Dear <strong style="color: #003366;">{$first_name} {$last_name}</strong>,</p>
                            <p style="color: #4a5568; margin: 0; font-size: 15px;">Your account has been successfully created in the DATAMEX E-Learning Management System. You have been registered with the following role:</p>
                        </td>
                    </tr>
                    
                    <!-- Role Badge -->
                    <tr>
                        <td style="padding: 0 40px 25px 40px; text-align: center;">
                            <span style="display: inline-block; background: #f0f4ff; color: #003366; padding: 10px 25px; border-radius: 8px; font-size: 14px; font-weight: 600; border: 2px solid #003366;">
                                👤 {$role_name}
                            </span>
                        </td>
                    </tr>
                    
                    <!-- Credentials Box -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background: linear-gradient(145deg, #f8fafc, #f1f5f9); border-radius: 12px; border: 1px solid #e2e8f0;">
                                <tr>
                                    <td style="padding: 25px;">
                                        <h3 style="color: #003366; margin: 0 0 20px 0; font-size: 16px; display: flex; align-items: center;">
                                            <span style="background: #003366; color: white; width: 28px; height: 28px; border-radius: 50%; display: inline-block; text-align: center; line-height: 28px; margin-right: 10px; font-size: 14px;">🔐</span>
                                            Your Login Credentials
                                        </h3>
                                        
                                        <!-- Email Field -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 12px;">
                                            <tr>
                                                <td style="background: #ffffff; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                                    <p style="color: #64748b; margin: 0 0 5px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Email Address</p>
                                                    <p style="color: #1a1a2e; margin: 0; font-size: 15px; font-weight: 600; font-family: 'Courier New', monospace;">{$email}</p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Password Field -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="background: #ffffff; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                                    <p style="color: #64748b; margin: 0 0 5px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Temporary Password</p>
                                                    <p style="color: #1a1a2e; margin: 0; font-size: 15px; font-weight: 600; font-family: 'Courier New', monospace; background: #fef3c7; padding: 5px 10px; border-radius: 4px; display: inline-block;">{$password}</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Warning Box -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background: #fef9e7; border-radius: 10px; border-left: 4px solid #f59e0b;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <p style="color: #92400e; margin: 0; font-size: 14px;">
                                            <strong>⚠️ Security Notice:</strong> For your protection, please change your password immediately after your first login. Do not share your credentials with anyone.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Login Button -->
                    <tr>
                        <td style="padding: 0 40px 20px 40px; text-align: center;">
                            <a href="{$login_url}" style="display: inline-block; background: linear-gradient(135deg, #800000, #a00000); color: #ffffff; padding: 16px 50px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 14px rgba(128, 0, 0, 0.3); transition: all 0.3s ease;">
                                Login to ELMS Portal →
                            </a>
                        </td>
                    </tr>
                    
                    <!-- Login URL Fallback -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px; text-align: center;">
                            <p style="color: #64748b; margin: 0 0 8px 0; font-size: 13px;">Or copy and paste this link in your browser:</p>
                            <p style="color: #003366; margin: 0; font-size: 13px; word-break: break-all; background: #f1f5f9; padding: 10px; border-radius: 6px;">{$login_url}</p>
                        </td>
                    </tr>
                    
                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 40px;">
                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0;">
                        </td>
                    </tr>
                    
                    <!-- Help Section -->
                    <tr>
                        <td style="padding: 25px 40px; text-align: center;">
                            <p style="color: #64748b; margin: 0 0 10px 0; font-size: 14px;">Need help getting started?</p>
                            <p style="color: #4a5568; margin: 0; font-size: 13px;">Contact the school administrator or visit our help center for assistance.</p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background: #1a1a2e; padding: 30px 40px; text-align: center;">
                            <img src="{$logo_url}" alt="Datamex" style="max-width: 80px; height: auto; margin-bottom: 15px; opacity: 0.9;">
                            <p style="color: rgba(255, 255, 255, 0.7); margin: 0 0 10px 0; font-size: 13px;">
                                DATAMEX College Foundation, Inc.
                            </p>
                            <p style="color: rgba(255, 255, 255, 0.5); margin: 0 0 15px 0; font-size: 12px;">
                                Excellence in Education • Innovation in Learning
                            </p>
                            <hr style="border: none; border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 15px 0;">
                            <p style="color: rgba(255, 255, 255, 0.4); margin: 0; font-size: 11px;">
                                This is an automated message from {$site_name}. Please do not reply to this email.
                            </p>
                            <p style="color: rgba(255, 255, 255, 0.4); margin: 8px 0 0 0; font-size: 11px;">
                                © {$year} DATAMEX College Foundation, Inc. All rights reserved.
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
                <!-- Bottom Spacing -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px;">
                    <tr>
                        <td style="padding: 20px; text-align: center;">
                            <p style="color: #94a3b8; margin: 0; font-size: 11px;">
                                If you did not request this account, please contact the administrator immediately.
                            </p>
                        </td>
                    </tr>
                </table>
                
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Generate password reset email HTML
 */
function generate_password_reset_email($first_name, $reset_link, $expiry_minutes = 60) {
    $site_name = SITE_NAME;
    $logo_url = BASE_URL . 'assets/image/datamexlogo.png';
    $year = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - {$site_name}</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa; line-height: 1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f7fa; padding: 40px 20px;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);">
                    
                    <!-- Header with Logo -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #003366 0%, #004488 50%, #800000 100%); padding: 40px 30px; text-align: center;">
                            <img src="{$logo_url}" alt="Datamex Logo" style="max-width: 120px; height: auto; margin-bottom: 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -0.5px;">Password Reset Request</h1>
                            <p style="color: rgba(255, 255, 255, 0.9); margin: 10px 0 0 0; font-size: 15px;">ELMS Security</p>
                        </td>
                    </tr>
                    
                    <!-- Lock Icon -->
                    <tr>
                        <td style="padding: 35px 40px 20px 40px; text-align: center;">
                            <div style="display: inline-block; background: linear-gradient(145deg, #fee2e2, #fecaca); width: 80px; height: 80px; border-radius: 50%; line-height: 80px; font-size: 36px;">
                                🔒
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Greeting -->
                    <tr>
                        <td style="padding: 20px 40px;">
                            <p style="color: #1a1a2e; margin: 0 0 15px 0; font-size: 16px; text-align: center;">Dear <strong style="color: #003366;">{$first_name}</strong>,</p>
                            <p style="color: #4a5568; margin: 0; font-size: 15px; text-align: center;">We received a request to reset the password for your ELMS account. Click the button below to create a new password.</p>
                        </td>
                    </tr>
                    
                    <!-- Reset Button -->
                    <tr>
                        <td style="padding: 25px 40px; text-align: center;">
                            <a href="{$reset_link}" style="display: inline-block; background: linear-gradient(135deg, #800000, #a00000); color: #ffffff; padding: 18px 60px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 14px rgba(128, 0, 0, 0.3);">
                                Reset My Password
                            </a>
                        </td>
                    </tr>
                    
                    <!-- Timer Warning -->
                    <tr>
                        <td style="padding: 0 40px 25px 40px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background: linear-gradient(145deg, #fef3c7, #fde68a); border-radius: 10px; border-left: 4px solid #f59e0b;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <p style="color: #92400e; margin: 0; font-size: 14px; text-align: center;">
                                            <strong>⏰ Time Sensitive:</strong> This link will expire in <strong>{$expiry_minutes} minutes</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Security Notice -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="color: #64748b; margin: 0 0 10px 0; font-size: 14px;">
                                            <strong style="color: #475569;">🛡️ Didn't request this?</strong>
                                        </p>
                                        <p style="color: #64748b; margin: 0; font-size: 13px;">
                                            If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged and your account is secure.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Link Fallback -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px; text-align: center;">
                            <p style="color: #64748b; margin: 0 0 8px 0; font-size: 13px;">If the button doesn't work, copy and paste this link:</p>
                            <p style="color: #003366; margin: 0; font-size: 12px; word-break: break-all; background: #f1f5f9; padding: 12px; border-radius: 6px; font-family: 'Courier New', monospace;">{$reset_link}</p>
                        </td>
                    </tr>
                    
                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 40px;">
                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0;">
                        </td>
                    </tr>
                    
                    <!-- Security Tips -->
                    <tr>
                        <td style="padding: 25px 40px;">
                            <p style="color: #475569; margin: 0 0 15px 0; font-size: 14px; font-weight: 600; text-align: center;">🔐 Password Security Tips</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="padding: 5px 0; color: #64748b; font-size: 13px;">✓ Use at least 8 characters</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; color: #64748b; font-size: 13px;">✓ Include uppercase and lowercase letters</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; color: #64748b; font-size: 13px;">✓ Add numbers and special characters</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; color: #64748b; font-size: 13px;">✓ Avoid using personal information</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background: #1a1a2e; padding: 30px 40px; text-align: center;">
                            <img src="{$logo_url}" alt="Datamex" style="max-width: 80px; height: auto; margin-bottom: 15px; opacity: 0.9;">
                            <p style="color: rgba(255, 255, 255, 0.7); margin: 0 0 10px 0; font-size: 13px;">
                                DATAMEX College Foundation, Inc.
                            </p>
                            <p style="color: rgba(255, 255, 255, 0.5); margin: 0 0 15px 0; font-size: 12px;">
                                Excellence in Education • Innovation in Learning
                            </p>
                            <hr style="border: none; border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 15px 0;">
                            <p style="color: rgba(255, 255, 255, 0.4); margin: 0; font-size: 11px;">
                                This is an automated security message. Please do not reply to this email.
                            </p>
                            <p style="color: rgba(255, 255, 255, 0.4); margin: 8px 0 0 0; font-size: 11px;">
                                © {$year} DATAMEX College Foundation, Inc. All rights reserved.
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Send account credentials email
 */
function send_account_credentials($email, $first_name, $last_name, $password, $role_name, $sent_by = null) {
    $login_url = BASE_URL . 'index.php'; // Login page URL
    $subject = SITE_NAME . " - Your Account Has Been Created";
    $body = generate_account_email($first_name, $last_name, $email, $password, $role_name, $login_url);
    return send_email($email, $subject, $body, 'account_creation', $sent_by);
}

/**
 * Send password reset email
 */
function send_password_reset($email, $first_name, $reset_link, $sent_by = null) {
    $expiry = (int)get_security_setting('password_reset_expiry', 60);
    $subject = SITE_NAME . " - Password Reset Request";
    $body = generate_password_reset_email($first_name, $reset_link, $expiry);
    return send_email($email, $subject, $body, 'password_reset', $sent_by);
}

/**
 * Generate a secure random password
 */
function generate_secure_password($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*()_+-=';
    
    $password = '';
    
    // Ensure at least one of each required type
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    
    if (get_security_setting('password_require_special', '0') === '1') {
        $password .= $special[random_int(0, strlen($special) - 1)];
    }
    
    // Fill remaining length
    $all_chars = $uppercase . $lowercase . $numbers;
    if (get_security_setting('password_require_special', '0') === '1') {
        $all_chars .= $special;
    }
    
    $remaining = $length - strlen($password);
    for ($i = 0; $i < $remaining; $i++) {
        $password .= $all_chars[random_int(0, strlen($all_chars) - 1)];
    }
    
    // Shuffle the password
    return str_shuffle($password);
}

/**
 * Validate password against security requirements
 */
function validate_password($password) {
    $errors = [];
    
    $min_length = (int)get_security_setting('password_min_length', 8);
    if (strlen($password) < $min_length) {
        $errors[] = "Password must be at least {$min_length} characters long";
    }
    
    if (get_security_setting('password_require_uppercase', '1') === '1' && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (get_security_setting('password_require_lowercase', '1') === '1' && !preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (get_security_setting('password_require_number', '1') === '1' && !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (get_security_setting('password_require_special', '0') === '1' && !preg_match('/[!@#$%^&*()_+\-=]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Create password reset token
 */
function create_password_reset_token($user_id) {
    global $conn;
    
    // Generate secure token
    $token = bin2hex(random_bytes(32));
    $expiry_minutes = (int)get_security_setting('password_reset_expiry', 60);
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_minutes} minutes"));
    
    // Invalidate any existing tokens for this user
    $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Create new token
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $token, $expires_at);
    
    if ($stmt->execute()) {
        return $token;
    }
    return null;
}

/**
 * Verify password reset token
 */
function verify_password_reset_token($token) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT pr.*, u.email, up.first_name 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Mark password reset token as used
 */
function use_password_reset_token($token) {
    global $conn;
    $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
    $stmt->bind_param("s", $token);
    return $stmt->execute();
}
?>
