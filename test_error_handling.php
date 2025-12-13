<?php
/**
 * Test Error Handling Implementation
 * Validates error handler, validators, and flash messages
 */

require_once 'config/db.php';
require_once 'includes/error_handler.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Handling Test - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        
        .test-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .test-result {
            padding: 10px 15px;
            border-radius: 8px;
            margin: 10px 0;
            display: flex;
            align-items: center;
        }
        
        .test-result.pass {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        
        .test-result.fail {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        
        .test-result i {
            font-size: 20px;
            margin-right: 10px;
        }
        
        .code-block {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="text-center text-white mb-4">
                    <h1><i class="bi bi-bug"></i> Error Handling Test Suite</h1>
                    <p class="lead">Comprehensive testing of error handling, validation, and flash messages</p>
                </div>
            </div>
        </div>

        <?php
        $totalTests = 0;
        $passedTests = 0;

        function testResult($name, $passed, $details = '') {
            global $totalTests, $passedTests;
            $totalTests++;
            if ($passed) $passedTests++;
            
            $icon = $passed ? 'check-circle' : 'x-circle';
            $class = $passed ? 'pass' : 'fail';
            $status = $passed ? 'PASS' : 'FAIL';
            
            echo "<div class='test-result $class'>";
            echo "<i class='bi bi-$icon'></i>";
            echo "<div><strong>$name:</strong> $status";
            if ($details) {
                echo "<br><small>$details</small>";
            }
            echo "</div></div>";
        }
        ?>

        <!-- Test 1: Error Handler Class -->
        <div class="row">
            <div class="col-12">
                <div class="test-card">
                    <h3><i class="bi bi-1-circle"></i> Error Handler Class</h3>
                    <p>Testing ErrorHandler class and error code definitions</p>
                    
                    <?php
                    // Test error codes exist
                    testResult(
                        'Error codes defined',
                        defined('ErrorHandler::AUTH_REQUIRED'),
                        'All error constants are accessible'
                    );
                    
                    // Test getError method
                    $error = ErrorHandler::getError(ErrorHandler::AUTH_REQUIRED);
                    testResult(
                        'getError() returns valid structure',
                        isset($error['title']) && isset($error['message']) && isset($error['action']),
                        'Contains title, message, action, icon, and code'
                    );
                    
                    echo "<div class='code-block'>";
                    echo "Sample Error Structure:\n";
                    echo htmlspecialchars(print_r($error, true));
                    echo "</div>";
                    
                    // Test all error codes
                    $errorCodes = [
                        'AUTH_REQUIRED', 'INVALID_ROLE', 'INVALID_CSRF', 'NOT_FOUND',
                        'PERMISSION_DENIED', 'VALIDATION_ERROR', 'DATABASE_ERROR',
                        'DUPLICATE_ENTRY', 'FILE_UPLOAD_ERROR', 'FILE_SIZE_ERROR',
                        'INVALID_FILE_TYPE', 'EMAIL_ERROR', 'SESSION_EXPIRED', 'RATE_LIMIT_EXCEEDED'
                    ];
                    
                    $allCodesValid = true;
                    foreach ($errorCodes as $code) {
                        $err = ErrorHandler::getError($code);
                        if (!isset($err['title']) || !isset($err['message'])) {
                            $allCodesValid = false;
                            break;
                        }
                    }
                    
                    testResult(
                        'All ' . count($errorCodes) . ' error codes valid',
                        $allCodesValid,
                        'Each code returns complete error information'
                    );
                    ?>
                </div>
            </div>
        </div>

        <!-- Test 2: Form Validator -->
        <div class="row">
            <div class="col-12">
                <div class="test-card">
                    <h3><i class="bi bi-2-circle"></i> Form Validator</h3>
                    <p>Testing FormValidator class validation methods</p>
                    
                    <?php
                    $validator = new FormValidator();
                    
                    // Test required validation
                    $validator->required('', 'test_field');
                    testResult(
                        'Required validation (empty)',
                        !$validator->passed(),
                        'Empty field fails required check'
                    );
                    
                    $validator = new FormValidator();
                    $validator->required('value', 'test_field');
                    testResult(
                        'Required validation (filled)',
                        $validator->passed(),
                        'Filled field passes required check'
                    );
                    
                    // Test email validation
                    $validator = new FormValidator();
                    $validator->email('invalid-email', 'email');
                    testResult(
                        'Email validation (invalid)',
                        !$validator->passed(),
                        'Invalid email format rejected'
                    );
                    
                    $validator = new FormValidator();
                    $validator->email('valid@email.com', 'email');
                    testResult(
                        'Email validation (valid)',
                        $validator->passed(),
                        'Valid email format accepted'
                    );
                    
                    // Test minLength validation
                    $validator = new FormValidator();
                    $validator->minLength('abc', 6, 'password');
                    testResult(
                        'MinLength validation (too short)',
                        !$validator->passed(),
                        'String shorter than minimum rejected'
                    );
                    
                    $validator = new FormValidator();
                    $validator->minLength('abcdef', 6, 'password');
                    testResult(
                        'MinLength validation (valid)',
                        $validator->passed(),
                        'String meeting minimum accepted'
                    );
                    
                    // Test numeric validation
                    $validator = new FormValidator();
                    $validator->numeric('abc', 'number');
                    testResult(
                        'Numeric validation (non-numeric)',
                        !$validator->passed(),
                        'Non-numeric value rejected'
                    );
                    
                    $validator = new FormValidator();
                    $validator->numeric('123.45', 'number');
                    testResult(
                        'Numeric validation (valid)',
                        $validator->passed(),
                        'Numeric value accepted'
                    );
                    
                    // Test range validation
                    $validator = new FormValidator();
                    $validator->range(5, 10, 20, 'score');
                    testResult(
                        'Range validation (below)',
                        !$validator->passed(),
                        'Value below range rejected'
                    );
                    
                    $validator = new FormValidator();
                    $validator->range(15, 10, 20, 'score');
                    testResult(
                        'Range validation (valid)',
                        $validator->passed(),
                        'Value within range accepted'
                    );
                    
                    // Test multiple validations
                    $validator = new FormValidator();
                    $validator->required('test@email.com', 'email');
                    $validator->email('test@email.com', 'email');
                    $validator->minLength('password123', 8, 'password');
                    
                    testResult(
                        'Multiple validations (chained)',
                        $validator->passed(),
                        'All validations can be chained successfully'
                    );
                    
                    // Test error collection
                    $validator = new FormValidator();
                    $validator->required('', 'field1');
                    $validator->email('invalid', 'field2');
                    $errors = $validator->getErrors();
                    
                    testResult(
                        'Error collection',
                        count($errors) === 2,
                        'Validator collects all errors: ' . count($errors) . ' errors found'
                    );
                    
                    echo "<div class='code-block'>";
                    echo "Collected Errors:\n";
                    echo htmlspecialchars(print_r($errors, true));
                    echo "</div>";
                    ?>
                </div>
            </div>
        </div>

        <!-- Test 3: Flash Messages -->
        <div class="row">
            <div class="col-12">
                <div class="test-card">
                    <h3><i class="bi bi-3-circle"></i> Flash Messages</h3>
                    <p>Testing FlashMessage class for session-based notifications</p>
                    
                    <?php
                    // Clear any existing flash
                    FlashMessage::get();
                    
                    // Test set and get
                    FlashMessage::success('Test success message');
                    $hasMessage = FlashMessage::has();
                    testResult(
                        'Flash message set',
                        $hasMessage,
                        'Flash message stored in session'
                    );
                    
                    $message = FlashMessage::get();
                    testResult(
                        'Flash message get',
                        $message && $message['type'] === 'success' && $message['message'] === 'Test success message',
                        'Retrieved correct message type and content'
                    );
                    
                    // Test auto-clear after get
                    testResult(
                        'Flash message auto-clear',
                        !FlashMessage::has(),
                        'Message cleared after retrieval'
                    );
                    
                    // Test all message types
                    $types = ['success', 'error', 'warning', 'info'];
                    $allTypesWork = true;
                    
                    foreach ($types as $type) {
                        FlashMessage::set($type, "Test $type message");
                        $msg = FlashMessage::get();
                        if (!$msg || $msg['type'] !== $type) {
                            $allTypesWork = false;
                            break;
                        }
                    }
                    
                    testResult(
                        'All message types',
                        $allTypesWork,
                        'Success, error, warning, and info types all work'
                    );
                    
                    // Test render method
                    FlashMessage::success('Test render');
                    $html = FlashMessage::render();
                    testResult(
                        'Flash message render',
                        strpos($html, 'alert') !== false && strpos($html, 'Test render') !== false,
                        'Renders Bootstrap alert HTML with message'
                    );
                    
                    echo "<div class='code-block'>";
                    echo "Rendered HTML (sample):\n";
                    echo htmlspecialchars(substr($html, 0, 200)) . '...';
                    echo "</div>";
                    ?>
                </div>
            </div>
        </div>

        <!-- Test 4: Database Error Handler -->
        <div class="row">
            <div class="col-12">
                <div class="test-card">
                    <h3><i class="bi bi-4-circle"></i> Database Error Handler</h3>
                    <p>Testing handleDatabaseError() function for user-friendly messages</p>
                    
                    <?php
                    // Simulate duplicate entry error
                    $duplicateError = new PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'test@email.com\' for key \'email\'');
                    $duplicateError->errorInfo = ['23000', 1062, 'Duplicate entry'];
                    
                    $message = handleDatabaseError($duplicateError);
                    testResult(
                        'Duplicate email detection',
                        strpos($message, 'email') !== false && strpos($message, 'already registered') !== false,
                        'Converts SQL error to user-friendly message'
                    );
                    
                    // Simulate foreign key error
                    $fkError = new PDOException('SQLSTATE[23000]: Cannot delete or update a parent row: a foreign key constraint fails');
                    $fkMessage = handleDatabaseError($fkError);
                    testResult(
                        'Foreign key error detection',
                        strpos($fkMessage, 'referenced') !== false || strpos($fkMessage, 'delete') !== false,
                        'Detects foreign key constraint violations'
                    );
                    
                    // Test generic error handling
                    $genericError = new PDOException('SQLSTATE[HY000]: General error');
                    $genericMessage = handleDatabaseError($genericError, 'Custom error message');
                    testResult(
                        'Generic error handling',
                        $genericMessage === 'Custom error message',
                        'Uses provided user message for unknown errors'
                    );
                    
                    echo "<div class='code-block'>";
                    echo "Duplicate Entry Message: " . htmlspecialchars($message) . "\n\n";
                    echo "Foreign Key Message: " . htmlspecialchars($fkMessage) . "\n\n";
                    echo "Generic Message: " . htmlspecialchars($genericMessage);
                    echo "</div>";
                    ?>
                </div>
            </div>
        </div>

        <!-- Test 5: Helper Functions -->
        <div class="row">
            <div class="col-12">
                <div class="test-card">
                    <h3><i class="bi bi-5-circle"></i> Helper Functions</h3>
                    <p>Testing utility functions (redirectWithMessage, jsonResponse)</p>
                    
                    <?php
                    // Test function existence
                    testResult(
                        'redirectWithMessage() exists',
                        function_exists('redirectWithMessage'),
                        'Function is defined and callable'
                    );
                    
                    testResult(
                        'jsonResponse() exists',
                        function_exists('jsonResponse'),
                        'Function is defined and callable'
                    );
                    
                    testResult(
                        'handleDatabaseError() exists',
                        function_exists('handleDatabaseError'),
                        'Function is defined and callable'
                    );
                    
                    // Note: Can't test redirectWithMessage directly as it calls header()
                    // Note: Can't test jsonResponse directly as it outputs JSON and exits
                    
                    echo "<div class='alert alert-info mt-3'>";
                    echo "<i class='bi bi-info-circle'></i> <strong>Note:</strong> ";
                    echo "redirectWithMessage() and jsonResponse() cannot be fully tested here ";
                    echo "as they modify headers and exit execution. They are tested in integration tests.";
                    echo "</div>";
                    ?>
                </div>
            </div>
        </div>

        <!-- Test 6: Error Page Template -->
        <div class="row">
            <div class="col-12">
                <div class="test-card">
                    <h3><i class="bi bi-6-circle"></i> Error Page Template</h3>
                    <p>Testing error page template file and structure</p>
                    
                    <?php
                    $templatePath = __DIR__ . '/includes/error_page_template.php';
                    testResult(
                        'Template file exists',
                        file_exists($templatePath),
                        'error_page_template.php found at: ' . $templatePath
                    );
                    
                    if (file_exists($templatePath)) {
                        $templateContent = file_get_contents($templatePath);
                        
                        testResult(
                            'Template has HTML structure',
                            strpos($templateContent, '<!DOCTYPE html>') !== false,
                            'Contains valid HTML5 doctype'
                        );
                        
                        testResult(
                            'Template uses error variables',
                            strpos($templateContent, '$error[\'title\']') !== false &&
                            strpos($templateContent, '$error[\'message\']') !== false,
                            'Uses $error array for dynamic content'
                        );
                        
                        testResult(
                            'Template has Bootstrap',
                            strpos($templateContent, 'bootstrap') !== false,
                            'Includes Bootstrap CSS framework'
                        );
                        
                        testResult(
                            'Template has action buttons',
                            strpos($templateContent, 'btn-action') !== false,
                            'Includes navigation buttons'
                        );
                    }
                    
                    echo "<div class='alert alert-success mt-3'>";
                    echo "<i class='bi bi-eye'></i> <strong>Preview:</strong> ";
                    echo "<a href='#' onclick=\"window.open('?preview_error=AUTH_REQUIRED', 'errorPreview', 'width=800,height=600'); return false;\" class='alert-link'>";
                    echo "Click to preview error page (AUTH_REQUIRED)</a>";
                    echo "</div>";
                    ?>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="row">
            <div class="col-12">
                <div class="test-card">
                    <h3><i class="bi bi-clipboard-check"></i> Test Summary</h3>
                    
                    <?php
                    $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
                    $badgeClass = $successRate === 100 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger');
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h2 class="text-primary"><?php echo $totalTests; ?></h2>
                            <p>Total Tests</p>
                        </div>
                        <div class="col-md-4">
                            <h2 class="text-success"><?php echo $passedTests; ?></h2>
                            <p>Passed</p>
                        </div>
                        <div class="col-md-4">
                            <h2 class="text-danger"><?php echo $totalTests - $passedTests; ?></h2>
                            <p>Failed</p>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <h1>
                            <span class="badge bg-<?php echo $badgeClass; ?>" style="font-size: 48px;">
                                <?php echo $successRate; ?>%
                            </span>
                        </h1>
                        <p class="lead">Success Rate</p>
                    </div>
                    
                    <?php if ($successRate === 100): ?>
                    <div class="alert alert-success mt-4">
                        <h5><i class="bi bi-check-circle"></i> All Tests Passed!</h5>
                        <p class="mb-0">Error handling system is fully functional and ready for production.</p>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning mt-4">
                        <h5><i class="bi bi-exclamation-triangle"></i> Some Tests Failed</h5>
                        <p class="mb-0">Please review the failed tests above and fix any issues before deployment.</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <h5>Recommendations:</h5>
                        <ul>
                            <li>Include error_handler.php in all PHP files that need validation</li>
                            <li>Use FormValidator for all user input validation</li>
                            <li>Use FlashMessage for user feedback after form submissions</li>
                            <li>Include form-validation.js for client-side validation</li>
                            <li>Test error pages with different error codes before production</li>
                            <li>Review error logs regularly in logs/error.log</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php
    // Preview error page if requested
    if (isset($_GET['preview_error'])) {
        $errorCode = $_GET['preview_error'];
        ErrorHandler::renderErrorPage($errorCode);
    }
    ?>
</body>
</html>
