<?php
/**
 * EEMS Error Page Template
 * User-friendly error display
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($error['title']); ?> - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .error-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 60px 40px;
            max-width: 600px;
            width: 90%;
            text-align: center;
        }
        
        .error-icon {
            font-size: 80px;
            margin-bottom: 30px;
        }
        
        .error-code {
            font-size: 14px;
            color: #6c757d;
            font-weight: 600;
            letter-spacing: 2px;
            margin-bottom: 15px;
        }
        
        .error-title {
            font-size: 32px;
            font-weight: 700;
            color: #212529;
            margin-bottom: 20px;
        }
        
        .error-message {
            font-size: 18px;
            color: #495057;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .error-action {
            font-size: 16px;
            color: #6c757d;
            margin-bottom: 30px;
        }
        
        .error-details {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
            border-radius: 5px;
        }
        
        .error-details pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 13px;
            color: #495057;
        }
        
        .btn-action {
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .btn-secondary-custom {
            background: #6c757d;
            color: white;
            border: none;
            margin-left: 10px;
        }
        
        .btn-secondary-custom:hover {
            background: #5a6268;
            transform: translateY(-2px);
            color: white;
        }
        
        .error-info {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #dee2e6;
        }
        
        .error-info p {
            font-size: 14px;
            color: #6c757d;
            margin: 5px 0;
        }
        
        .icon-variants {
            color: #dc3545;
        }
        
        .icon-variants.warning {
            color: #ffc107;
        }
        
        .icon-variants.info {
            color: #17a2b8;
        }
        
        @media (max-width: 576px) {
            .error-container {
                padding: 40px 20px;
            }
            
            .error-icon {
                font-size: 60px;
            }
            
            .error-title {
                font-size: 24px;
            }
            
            .error-message {
                font-size: 16px;
            }
            
            .btn-action {
                display: block;
                margin: 10px 0;
            }
            
            .btn-secondary-custom {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <!-- Error Icon -->
        <div class="error-icon">
            <i class="bi bi-<?php echo htmlspecialchars($error['icon']); ?> 
               icon-variants <?php echo ($error['code'] === 'SESSION_EXPIRED' || $error['code'] === 'RATE_LIMIT_EXCEEDED') ? 'warning' : ''; ?>"></i>
        </div>
        
        <!-- Error Code -->
        <div class="error-code">
            ERROR: <?php echo htmlspecialchars($error['code']); ?>
        </div>
        
        <!-- Error Title -->
        <h1 class="error-title">
            <?php echo htmlspecialchars($error['title']); ?>
        </h1>
        
        <!-- Error Message -->
        <p class="error-message">
            <?php echo htmlspecialchars($error['message']); ?>
        </p>
        
        <!-- Error Action -->
        <p class="error-action">
            <?php echo htmlspecialchars($error['action']); ?>
        </p>
        
        <!-- Additional Details (if in development mode) -->
        <?php if (isset($error['details']) && (defined('APP_DEBUG') && APP_DEBUG)): ?>
        <div class="error-details">
            <strong><i class="bi bi-bug"></i> Debug Information:</strong>
            <pre><?php echo htmlspecialchars($error['details']); ?></pre>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="mt-4">
            <?php if ($error['code'] === 'AUTH_REQUIRED' || $error['code'] === 'SESSION_EXPIRED'): ?>
                <a href="login.php" class="btn btn-primary-custom btn-action">
                    <i class="bi bi-box-arrow-in-right"></i> Go to Login
                </a>
            <?php elseif ($error['code'] === 'NOT_FOUND'): ?>
                <a href="dashboard.php" class="btn btn-primary-custom btn-action">
                    <i class="bi bi-house-door"></i> Go to Dashboard
                </a>
                <a href="javascript:history.back()" class="btn btn-secondary-custom btn-action">
                    <i class="bi bi-arrow-left"></i> Go Back
                </a>
            <?php elseif ($error['code'] === 'PERMISSION_DENIED' || $error['code'] === 'INVALID_ROLE'): ?>
                <a href="dashboard.php" class="btn btn-primary-custom btn-action">
                    <i class="bi bi-house-door"></i> Go to Dashboard
                </a>
            <?php else: ?>
                <a href="javascript:history.back()" class="btn btn-primary-custom btn-action">
                    <i class="bi bi-arrow-left"></i> Go Back
                </a>
                <a href="dashboard.php" class="btn btn-secondary-custom btn-action">
                    <i class="bi bi-house-door"></i> Dashboard
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Support Information -->
        <div class="error-info">
            <p><i class="bi bi-clock"></i> <strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            <?php if (isset($_SESSION['user_id'])): ?>
            <p><i class="bi bi-person"></i> <strong>User ID:</strong> <?php echo htmlspecialchars($_SESSION['user_id']); ?></p>
            <?php endif; ?>
            <p><i class="bi bi-envelope"></i> <strong>Need Help?</strong> Contact <a href="mailto:support@eems.edu">support@eems.edu</a></p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
