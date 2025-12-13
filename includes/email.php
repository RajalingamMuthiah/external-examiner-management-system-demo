<?php
/**
 * EEMS - Email Notification System
 * =====================================================
 * Centralized email notification handling
 * Uses PHPMailer for robust email delivery
 */

class EmailNotifier {
    private $from_email;
    private $from_name;
    private $smtp_enabled;
    
    public function __construct() {
        // Configuration - Update these with your SMTP settings
        $this->from_email = 'noreply@eems.edu';
        $this->from_name = 'EEMS - Exam Management System';
        $this->smtp_enabled = false; // Set to true when SMTP is configured
    }
    
    /**
     * Send email using PHP mail() or SMTP
     */
    private function send($to, $subject, $htmlBody, $textBody = null) {
        if ($this->smtp_enabled) {
            return $this->sendSMTP($to, $subject, $htmlBody, $textBody);
        } else {
            return $this->sendPHPMail($to, $subject, $htmlBody, $textBody);
        }
    }
    
    /**
     * Send email using PHP's mail() function
     */
    private function sendPHPMail($to, $subject, $htmlBody, $textBody) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "Reply-To: {$this->from_email}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $success = mail($to, $subject, $htmlBody, $headers);
        
        // Log email attempt
        error_log("Email sent to {$to}: " . ($success ? 'SUCCESS' : 'FAILED'));
        
        return $success;
    }
    
    /**
     * Send email using SMTP (requires PHPMailer)
     * Install: composer require phpmailer/phpmailer
     */
    private function sendSMTP($to, $subject, $htmlBody, $textBody) {
        // Implementation would use PHPMailer
        // For now, fallback to mail()
        return $this->sendPHPMail($to, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Generate HTML email template
     */
    private function getEmailTemplate($title, $content, $actionButton = null) {
        $buttonHTML = '';
        if ($actionButton) {
            $buttonHTML = "
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$actionButton['url']}' style='
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 14px 32px;
                        text-decoration: none;
                        border-radius: 8px;
                        font-weight: 600;
                        display: inline-block;
                    '>{$actionButton['text']}</a>
                </div>
            ";
        }
        
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 40px 20px;'>
        <tr>
            <td align='center'>
                <table width='600' cellpadding='0' cellspacing='0' style='background-color: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                    <!-- Header -->
                    <tr>
                        <td style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;'>
                            <h1 style='color: white; margin: 0; font-size: 24px;'>📚 EEMS</h1>
                            <p style='color: rgba(255,255,255,0.9); margin: 5px 0 0 0; font-size: 14px;'>Exam Management System</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style='padding: 40px 30px;'>
                            <h2 style='color: #1f2937; margin: 0 0 20px 0; font-size: 20px;'>{$title}</h2>
                            <div style='color: #4b5563; line-height: 1.6; font-size: 15px;'>
                                {$content}
                            </div>
                            {$buttonHTML}
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style='background-color: #f9fafb; padding: 20px 30px; text-align: center; border-top: 1px solid #e5e7eb;'>
                            <p style='color: #6b7280; font-size: 13px; margin: 0;'>
                                This is an automated message from EEMS. Please do not reply to this email.
                            </p>
                            <p style='color: #9ca3af; font-size: 12px; margin: 10px 0 0 0;'>
                                © " . date('Y') . " EEMS. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        ";
    }
    
    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail($userEmail, $userName, $password = null) {
        $content = "
            <p>Welcome to EEMS, <strong>{$userName}</strong>!</p>
            <p>Your account has been successfully created and verified by your administrator.</p>
        ";
        
        if ($password) {
            $content .= "
                <div style='background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0 0 10px 0;'><strong>Your login credentials:</strong></p>
                    <p style='margin: 5px 0;'>📧 Email: <code>{$userEmail}</code></p>
                    <p style='margin: 5px 0;'>🔑 Password: <code>{$password}</code></p>
                </div>
                <p style='color: #ef4444; font-size: 13px;'>
                    ⚠️ Please change your password after your first login for security.
                </p>
            ";
        }
        
        $content .= "
            <p>You can now access the system and manage exams, assignments, and more.</p>
        ";
        
        $button = [
            'text' => 'Login to EEMS',
            'url' => $this->getBaseURL() . '/login.php'
        ];
        
        $html = $this->getEmailTemplate('Welcome to EEMS!', $content, $button);
        
        return $this->send($userEmail, 'Welcome to EEMS - Account Verified', $html);
    }
    
    /**
     * Send exam assignment notification
     */
    public function sendExamAssignmentEmail($userEmail, $userName, $examName, $examDate, $collegeName) {
        $content = "
            <p>Dear <strong>{$userName}</strong>,</p>
            <p>You have been assigned as an external examiner for the following exam:</p>
            
            <div style='background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong>Exam:</strong> {$examName}</p>
                <p style='margin: 5px 0;'><strong>Date:</strong> " . date('F d, Y', strtotime($examDate)) . "</p>
                <p style='margin: 5px 0;'><strong>College:</strong> {$collegeName}</p>
            </div>
            
            <p>Please log in to view full exam details and prepare accordingly.</p>
        ";
        
        $button = [
            'text' => 'View Exam Details',
            'url' => $this->getBaseURL() . '/teacher_dashboard.php'
        ];
        
        $html = $this->getEmailTemplate('New Exam Assignment', $content, $button);
        
        return $this->send($userEmail, 'Exam Assignment - ' . $examName, $html);
    }
    
    /**
     * Send exam approval notification to creator
     */
    public function sendExamApprovalEmail($userEmail, $userName, $examName, $status) {
        $statusText = $status === 'Approved' ? 'approved' : 'rejected';
        $statusColor = $status === 'Approved' ? '#10b981' : '#ef4444';
        $statusIcon = $status === 'Approved' ? '✅' : '❌';
        
        $content = "
            <p>Dear <strong>{$userName}</strong>,</p>
            <p>Your exam request has been <strong style='color: {$statusColor};'>{$statusIcon} {$statusText}</strong> by the administrator.</p>
            
            <div style='background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong>Exam:</strong> {$examName}</p>
                <p style='margin: 5px 0;'><strong>Status:</strong> <span style='color: {$statusColor};'>{$status}</span></p>
            </div>
        ";
        
        if ($status === 'Approved') {
            $content .= "<p>The exam is now visible to other colleges and faculty can apply to be external examiners.</p>";
        } else {
            $content .= "<p>Please contact the administrator for more details or resubmit with corrections.</p>";
        }
        
        $button = [
            'text' => 'View Dashboard',
            'url' => $this->getBaseURL() . '/admin_dashboard.php'
        ];
        
        $html = $this->getEmailTemplate('Exam ' . $status, $content, $button);
        
        return $this->send($userEmail, 'Exam ' . $status . ' - ' . $examName, $html);
    }
    
    /**
     * Send verification pending notification to user
     */
    public function sendVerificationPendingEmail($userEmail, $userName, $role) {
        $content = "
            <p>Dear <strong>{$userName}</strong>,</p>
            <p>Thank you for registering with EEMS!</p>
            <p>Your account has been created as <strong>{$role}</strong> and is currently pending verification by your administrator.</p>
            
            <div style='background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;'>
                <p style='margin: 0; color: #92400e;'>
                    ⏳ <strong>What happens next?</strong><br>
                    Your hierarchical authority will review your registration. Once approved, you will receive login credentials via email.
                </p>
            </div>
            
            <p>This process typically takes 24-48 hours. You will be notified via email once your account is verified.</p>
        ";
        
        $html = $this->getEmailTemplate('Registration Received - Pending Verification', $content);
        
        return $this->send($userEmail, 'EEMS Registration - Pending Verification', $html);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($userEmail, $userName, $resetToken) {
        $resetURL = $this->getBaseURL() . '/reset_password.php?token=' . $resetToken;
        
        $content = "
            <p>Dear <strong>{$userName}</strong>,</p>
            <p>We received a request to reset your password for your EEMS account.</p>
            <p>Click the button below to create a new password:</p>
            
            <div style='background-color: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0;'>
                <p style='margin: 0; color: #991b1b; font-size: 13px;'>
                    ⚠️ <strong>Security Notice:</strong><br>
                    This link will expire in 1 hour. If you didn't request this reset, please ignore this email.
                </p>
            </div>
        ";
        
        $button = [
            'text' => 'Reset Password',
            'url' => $resetURL
        ];
        
        $html = $this->getEmailTemplate('Password Reset Request', $content, $button);
        
        return $this->send($userEmail, 'EEMS - Password Reset Request', $html);
    }
    
    /**
     * Send examiner invitation email
     */
    public function sendExaminerInviteEmail($inviteeEmail, $inviteeName, $examTitle, $examDate, $examTime, $role, $dutyType, $collegeOrg, $token) {
        $inviteURL = $this->getBaseURL() . '/invite_response.php?token=' . $token;
        
        // Format exam date
        $formattedDate = date('l, F j, Y', strtotime($examDate));
        $timeInfo = $examTime ? ' at ' . date('h:i A', strtotime($examTime)) : '';
        
        // Format duty type nicely
        $dutyTypeFormatted = ucwords(str_replace('_', ' ', $dutyType));
        
        $content = "
            <p>Dear <strong>{$inviteeName}</strong>,</p>
            <p>You have been invited to serve as a <strong style='color: #667eea;'>{$role}</strong> for an upcoming examination.</p>
            
            <div style='background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='margin-top: 0; color: #1f2937;'>📋 Exam Details</h3>
                <p style='margin: 8px 0;'><strong>Exam:</strong> {$examTitle}</p>
                <p style='margin: 8px 0;'><strong>Date:</strong> {$formattedDate}{$timeInfo}</p>
                <p style='margin: 8px 0;'><strong>Your Role:</strong> {$role}</p>
                <p style='margin: 8px 0;'><strong>Duty Type:</strong> {$dutyTypeFormatted}</p>
                <p style='margin: 8px 0;'><strong>Requesting College:</strong> {$collegeOrg}</p>
            </div>
            
            <p>Please review the exam details and confirm your availability by clicking the button below:</p>
            
            <div style='background-color: #dbeafe; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0;'>
                <p style='margin: 0; color: #1e40af; font-size: 13px;'>
                    ℹ️ <strong>Important:</strong><br>
                    This invitation link is unique and secure. You can accept or decline the invitation by clicking the button below.
                    No account registration is required to respond.
                </p>
            </div>
        ";
        
        $button = [
            'text' => '👉 Respond to Invitation',
            'url' => $inviteURL
        ];
        
        $html = $this->getEmailTemplate('Examiner Invitation - ' . $examTitle, $content, $button);
        
        return $this->send($inviteeEmail, 'Invitation: ' . $role . ' for ' . $examTitle, $html);
    }
    
    /**
     * Get base URL of the application
     */
    private function getBaseURL() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['PHP_SELF'] ?? '');
        return $protocol . '://' . $host . $path;
    }
}

// Global helper function
function sendEmail($type, $userEmail, $data) {
    $notifier = new EmailNotifier();
    
    switch ($type) {
        case 'welcome':
            return $notifier->sendWelcomeEmail(
                $userEmail,
                $data['name'],
                $data['password'] ?? null
            );
            
        case 'exam_assignment':
            return $notifier->sendExamAssignmentEmail(
                $userEmail,
                $data['name'],
                $data['exam_name'],
                $data['exam_date'],
                $data['college_name']
            );
            
        case 'exam_approval':
            return $notifier->sendExamApprovalEmail(
                $userEmail,
                $data['name'],
                $data['exam_name'],
                $data['status']
            );
            
        case 'verification_pending':
            return $notifier->sendVerificationPendingEmail(
                $userEmail,
                $data['name'],
                $data['role']
            );
            
        case 'password_reset':
            return $notifier->sendPasswordResetEmail(
                $userEmail,
                $data['name'],
                $data['reset_token']
            );
            
        case 'examiner_invite':
            return $notifier->sendExaminerInviteEmail(
                $userEmail,
                $data['name'],
                $data['exam_title'],
                $data['exam_date'],
                $data['exam_time'] ?? null,
                $data['role'],
                $data['duty_type'],
                $data['college'],
                $data['token']
            );
            
        default:
            return false;
    }
}
