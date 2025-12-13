<?php
/**
 * EMAIL CONFIGURATION GUIDE FOR EEMS
 * ===================================
 * 
 * This file provides instructions for configuring email in EEMS.
 * By default, EEMS uses PHP's mail() function, but you can configure SMTP for better reliability.
 * 
 * CURRENT STATUS: PHP mail() - Ready to use (requires server mail configuration)
 * RECOMMENDED: Configure SMTP for production use
 */

// ============================================================================
// OPTION 1: PHP mail() (Default - Already Active)
// ============================================================================
// The system is configured to use PHP's built-in mail() function.
// 
// REQUIREMENTS:
// - Server must have mail configured (sendmail, postfix, or similar)
// - For Windows/XAMPP: Configure php.ini with SMTP settings
//
// XAMPP Configuration (php.ini):
// [mail function]
// SMTP = smtp.gmail.com
// smtp_port = 587
// sendmail_from = your-email@gmail.com
// sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"
//
// Then configure sendmail.ini in C:\xampp\sendmail\:
// [sendmail]
// smtp_server=smtp.gmail.com
// smtp_port=587
// auth_username=your-email@gmail.com
// auth_password=your-app-password
// force_sender=your-email@gmail.com

// ============================================================================
// OPTION 2: SMTP with PHPMailer (Recommended for Production)
// ============================================================================
// For reliable email delivery, configure SMTP in includes/email.php
//
// STEPS:
// 1. Install PHPMailer (if not already installed):
//    composer require phpmailer/phpmailer
//
// 2. Edit includes/email.php and set:
//    $this->smtp_enabled = true;
//
// 3. Configure SMTP settings in includes/email.php:
//    - SMTP host (e.g., smtp.gmail.com)
//    - SMTP port (587 for TLS, 465 for SSL)
//    - Username and password
//    - Enable TLS/SSL
//
// Example SMTP providers:
// - Gmail: smtp.gmail.com:587 (requires App Password)
// - Outlook: smtp.office365.com:587
// - SendGrid: smtp.sendgrid.net:587
// - Mailgun: smtp.mailgun.org:587
// - AWS SES: email-smtp.region.amazonaws.com:587

// ============================================================================
// TESTING EMAIL FUNCTIONALITY
// ============================================================================
// To test if emails are working:
// 1. Visit: test_email.php
// 2. Enter your email address
// 3. Check if you receive the test email
//
// If emails are not working:
// - Check server error logs
// - Verify SMTP settings
// - Check spam/junk folder
// - Ensure firewall allows outbound SMTP connections
// - For Gmail, use App Password (not regular password)

// ============================================================================
// EMAIL TEMPLATES AVAILABLE
// ============================================================================
// The system includes these email templates:
// - welcome: New user registration
// - exam_assignment: Exam assignment notification
// - exam_approval: Exam approved/rejected
// - verification_pending: Account pending verification
// - password_reset: Password reset link
// - examiner_invite: Examiner invitation with accept/decline link ✨ NEW!

// ============================================================================
// INVITE EMAIL WORKFLOW
// ============================================================================
// When an invite is sent:
// 1. System generates unique token
// 2. Invite record created in exam_invites table
// 3. Email sent to invitee with secure link
// 4. Invitee clicks link → invite_response.php?token=...
// 5. Invitee accepts/declines without login
// 6. Status updated, notifications sent

// ============================================================================
// PRODUCTION CHECKLIST
// ============================================================================
// Before deploying to production:
// ☐ Configure SMTP with reliable provider (SendGrid, Mailgun, AWS SES)
// ☐ Set proper from_email and from_name in includes/email.php
// ☐ Test all email types (registration, invites, approvals)
// ☐ Configure SPF, DKIM, DMARC records for your domain
// ☐ Monitor email delivery rates
// ☐ Set up email error logging
// ☐ Configure email rate limiting if needed

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 40px; background: #f8f9fa; }
        .config-card { background: white; border-radius: 8px; padding: 30px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-active { color: #10b981; font-weight: bold; }
        .status-inactive { color: #6b7280; }
        pre { background: #1f2937; color: #10b981; padding: 15px; border-radius: 6px; overflow-x: auto; }
        .checklist li { padding: 8px 0; }
        .checklist input[type="checkbox"] { margin-right: 10px; transform: scale(1.3); }
    </style>
</head>
<body>
    <div class="container">
        <div class="config-card">
            <h1>📧 Email Configuration Guide</h1>
            <p class="lead">EEMS Email System Status and Configuration</p>
            <hr>
            
            <div class="alert alert-success">
                <h5>✅ Email System Active</h5>
                <p class="mb-0">The examiner invitation system is ready! Emails will be sent using PHP mail() function.</p>
            </div>

            <h3 class="mt-4">Current Configuration</h3>
            <table class="table">
                <tr>
                    <td><strong>Email Method:</strong></td>
                    <td><span class="status-active">PHP mail()</span></td>
                </tr>
                <tr>
                    <td><strong>SMTP:</strong></td>
                    <td><span class="status-inactive">Not Configured</span> (Optional)</td>
                </tr>
                <tr>
                    <td><strong>From Email:</strong></td>
                    <td>noreply@eems.edu</td>
                </tr>
                <tr>
                    <td><strong>Templates:</strong></td>
                    <td>7 templates (including examiner_invite ✨)</td>
                </tr>
            </table>

            <h3 class="mt-4">Quick XAMPP Setup</h3>
            <p>For Windows/XAMPP, configure sendmail in <code>C:\xampp\sendmail\sendmail.ini</code>:</p>
            <pre>smtp_server=smtp.gmail.com
smtp_port=587
auth_username=your-email@gmail.com
auth_password=your-app-password
force_sender=your-email@gmail.com</pre>
            
            <h3 class="mt-4">Test Email Functionality</h3>
            <a href="test_email.php" class="btn btn-primary btn-lg">
                <i class="bi bi-envelope"></i> Send Test Email
            </a>
            <a href="manage_exam_invites.php" class="btn btn-success btn-lg ms-2">
                <i class="bi bi-person-plus"></i> Test Examiner Invite
            </a>

            <h3 class="mt-4">Production Deployment Checklist</h3>
            <ul class="checklist">
                <li><input type="checkbox"> Configure SMTP with reliable provider (SendGrid, Mailgun, AWS SES)</li>
                <li><input type="checkbox"> Update from_email to your domain email</li>
                <li><input type="checkbox"> Test all email templates</li>
                <li><input type="checkbox"> Configure SPF/DKIM/DMARC DNS records</li>
                <li><input type="checkbox"> Set up email delivery monitoring</li>
                <li><input type="checkbox"> Configure proper error logging</li>
                <li><input type="checkbox"> Test invite workflow end-to-end</li>
            </ul>

            <div class="alert alert-info mt-4">
                <h5>📚 Documentation</h5>
                <p class="mb-0">Full email configuration details are in the comments of this file. Edit <code>includes/email.php</code> to customize email templates and SMTP settings.</p>
            </div>
        </div>
    </div>
</body>
</html>
