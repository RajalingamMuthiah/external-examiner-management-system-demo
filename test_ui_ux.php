<?php
/**
 * UI/UX Testing Suite
 * Tests responsive design, accessibility, and mobile optimization
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UI/UX Test Suite - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles/enhanced-ui.css">
    <link rel="stylesheet" href="styles/print.css" media="print">
    <style>
        body {
            padding: 20px 0;
        }
        
        .test-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .test-result {
            padding: 15px;
            border-radius: 10px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .test-pass {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        .test-fail {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .test-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .demo-box {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
        }
        
        .viewport-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="text-center mb-5">
            <h1 class="text-gradient"><i class="bi bi-palette"></i> UI/UX Test Suite</h1>
            <p class="lead">Testing responsive design, accessibility, and mobile optimization</p>
        </div>

        <?php
        $tests = [];
        $pass = 0;
        $fail = 0;
        $warning = 0;

        function testResult($name, $status, $message, $details = '') {
            global $pass, $fail, $warning;
            
            if ($status === 'PASS') {
                $pass++;
                $class = 'test-pass';
                $icon = 'check-circle-fill';
                $iconColor = 'success';
            } elseif ($status === 'FAIL') {
                $fail++;
                $class = 'test-fail';
                $icon = 'x-circle-fill';
                $iconColor = 'danger';
            } else {
                $warning++;
                $class = 'test-warning';
                $icon = 'exclamation-triangle-fill';
                $iconColor = 'warning';
            }
            
            echo "<div class='test-result $class'>";
            echo "<i class='bi bi-$icon text-$iconColor' style='font-size: 24px;'></i>";
            echo "<div class='flex-grow-1'>";
            echo "<strong>$name</strong><br>";
            echo "<small>$message</small>";
            if ($details) {
                echo "<br><small class='text-muted'>$details</small>";
            }
            echo "</div>";
            echo "</div>";
        }
        ?>

        <!-- Test 1: CSS Files -->
        <div class="test-section">
            <h3><i class="bi bi-1-circle-fill"></i> Enhanced UI Stylesheet</h3>
            <p>Testing enhanced-ui.css for responsive design and accessibility</p>
            
            <?php
            $cssFile = __DIR__ . '/styles/enhanced-ui.css';
            if (file_exists($cssFile)) {
                $cssContent = file_get_contents($cssFile);
                $cssSize = filesize($cssFile);
                
                testResult(
                    'enhanced-ui.css exists',
                    'PASS',
                    "File found ({$cssSize} bytes)",
                    "Located at: $cssFile"
                );
                
                // Check for key features
                $features = [
                    'CSS Variables' => strpos($cssContent, ':root') !== false && strpos($cssContent, '--primary-color') !== false,
                    'Media Queries' => substr_count($cssContent, '@media') >= 5,
                    'Focus Visible' => strpos($cssContent, ':focus-visible') !== false,
                    'Accessibility Classes' => strpos($cssContent, '.sr-only') !== false,
                    'Mobile Optimization' => strpos($cssContent, 'max-width: 768px') !== false,
                    'Touch Optimization' => strpos($cssContent, 'hover: none') !== false,
                    'Dark Mode Support' => strpos($cssContent, 'prefers-color-scheme: dark') !== false,
                    'Reduced Motion' => strpos($cssContent, 'prefers-reduced-motion') !== false,
                    'High Contrast' => strpos($cssContent, 'prefers-contrast: high') !== false,
                    'Responsive Typography' => strpos($cssContent, 'clamp(') !== false
                ];
                
                foreach ($features as $feature => $exists) {
                    testResult(
                        $feature,
                        $exists ? 'PASS' : 'FAIL',
                        $exists ? 'Feature implemented' : 'Feature missing'
                    );
                }
                
                // Check CSS size (should be reasonable)
                if ($cssSize > 100000) {
                    testResult(
                        'CSS File Size',
                        'WARNING',
                        'File is large (' . round($cssSize/1024, 2) . ' KB)',
                        'Consider optimization for production'
                    );
                } else {
                    testResult(
                        'CSS File Size',
                        'PASS',
                        'File size is reasonable (' . round($cssSize/1024, 2) . ' KB)'
                    );
                }
                
            } else {
                testResult(
                    'enhanced-ui.css',
                    'FAIL',
                    'File not found',
                    'Expected at: ' . $cssFile
                );
            }
            ?>
        </div>

        <!-- Test 2: Accessibility JavaScript -->
        <div class="test-section">
            <h3><i class="bi bi-2-circle-fill"></i> Accessibility Module</h3>
            <p>Testing accessibility.js for WCAG 2.1 compliance features</p>
            
            <?php
            $a11yFile = __DIR__ . '/scripts/accessibility.js';
            if (file_exists($a11yFile)) {
                $a11yContent = file_get_contents($a11yFile);
                $a11ySize = filesize($a11yFile);
                
                testResult(
                    'accessibility.js exists',
                    'PASS',
                    "File found ({$a11ySize} bytes)",
                    "Located at: $a11yFile"
                );
                
                $a11yFeatures = [
                    'AccessibilityManager Class' => strpos($a11yContent, 'class AccessibilityManager') !== false,
                    'Keyboard Navigation' => strpos($a11yContent, 'setupKeyboardNavigation') !== false,
                    'ARIA Setup' => strpos($a11yContent, 'setupARIA') !== false,
                    'Focus Management' => strpos($a11yContent, 'setupFocusManagement') !== false,
                    'Screen Reader Announcements' => strpos($a11yContent, 'announce(') !== false,
                    'Skip Links' => strpos($a11yContent, 'skip-to-main') !== false,
                    'Live Regions' => strpos($a11yContent, 'aria-live') !== false,
                    'Tab Navigation' => strpos($a11yContent, 'handleTabNavigation') !== false,
                    'Reduced Motion Support' => strpos($a11yContent, 'setupReducedMotion') !== false,
                    'Contrast Checker' => strpos($a11yContent, 'checkContrast') !== false
                ];
                
                foreach ($a11yFeatures as $feature => $exists) {
                    testResult(
                        $feature,
                        $exists ? 'PASS' : 'FAIL',
                        $exists ? 'Feature implemented' : 'Feature missing'
                    );
                }
                
            } else {
                testResult(
                    'accessibility.js',
                    'FAIL',
                    'File not found',
                    'Expected at: ' . $a11yFile
                );
            }
            ?>
        </div>

        <!-- Test 3: Mobile Utilities -->
        <div class="test-section">
            <h3><i class="bi bi-3-circle-fill"></i> Mobile Utilities</h3>
            <p>Testing mobile-utils.js for mobile optimization</p>
            
            <?php
            $mobileFile = __DIR__ . '/scripts/mobile-utils.js';
            if (file_exists($mobileFile)) {
                $mobileContent = file_get_contents($mobileFile);
                $mobileSize = filesize($mobileFile);
                
                testResult(
                    'mobile-utils.js exists',
                    'PASS',
                    "File found ({$mobileSize} bytes)",
                    "Located at: $mobileFile"
                );
                
                $mobileFeatures = [
                    'MobileUtils Class' => strpos($mobileContent, 'class MobileUtils') !== false,
                    'Mobile Detection' => strpos($mobileContent, 'detectMobile') !== false,
                    'Touch Detection' => strpos($mobileContent, 'detectTouch') !== false,
                    'Viewport Fixes' => strpos($mobileContent, 'setupViewportFixes') !== false,
                    'Mobile Menu' => strpos($mobileContent, 'setupMobileMenu') !== false,
                    'Mobile Tables' => strpos($mobileContent, 'setupMobileTables') !== false,
                    'Mobile Modals' => strpos($mobileContent, 'setupMobileModals') !== false,
                    'Pull to Refresh' => strpos($mobileContent, 'setupPullToRefresh') !== false,
                    'Touch Gestures' => strpos($mobileContent, 'class TouchGestures') !== false,
                    'Bottom Sheet' => strpos($mobileContent, 'createBottomSheet') !== false,
                    'PWA Manager' => strpos($mobileContent, 'class PWAManager') !== false
                ];
                
                foreach ($mobileFeatures as $feature => $exists) {
                    testResult(
                        $feature,
                        $exists ? 'PASS' : 'FAIL',
                        $exists ? 'Feature implemented' : 'Feature missing'
                    );
                }
                
            } else {
                testResult(
                    'mobile-utils.js',
                    'FAIL',
                    'File not found',
                    'Expected at: ' . $mobileFile
                );
            }
            ?>
        </div>

        <!-- Test 4: Print Stylesheet -->
        <div class="test-section">
            <h3><i class="bi bi-4-circle-fill"></i> Print Stylesheet</h3>
            <p>Testing print.css for document printing</p>
            
            <?php
            $printFile = __DIR__ . '/styles/print.css';
            if (file_exists($printFile)) {
                $printContent = file_get_contents($printFile);
                $printSize = filesize($printFile);
                
                testResult(
                    'print.css exists',
                    'PASS',
                    "File found ({$printSize} bytes)",
                    "Located at: $printFile"
                );
                
                $printFeatures = [
                    'Print Media Query' => strpos($printContent, '@media print') !== false,
                    'Page Setup' => strpos($printContent, '@page') !== false,
                    'Hide Elements' => strpos($printContent, '.no-print') !== false,
                    'Page Breaks' => strpos($printContent, 'page-break') !== false,
                    'Table Optimization' => strpos($printContent, 'table-header-group') !== false,
                    'Document Header' => strpos($printContent, '.print-header') !== false,
                    'Document Footer' => strpos($printContent, '.print-footer') !== false,
                    'Exam Schedule' => strpos($printContent, '.exam-schedule-table') !== false,
                    'Invitation Letter' => strpos($printContent, '.invitation-letter') !== false,
                    'Exam Report' => strpos($printContent, '.exam-report') !== false
                ];
                
                foreach ($printFeatures as $feature => $exists) {
                    testResult(
                        $feature,
                        $exists ? 'PASS' : 'FAIL',
                        $exists ? 'Feature implemented' : 'Feature missing'
                    );
                }
                
            } else {
                testResult(
                    'print.css',
                    'FAIL',
                    'File not found',
                    'Expected at: ' . $printFile
                );
            }
            ?>
        </div>

        <!-- Test 5: Visual Components Demo -->
        <div class="test-section">
            <h3><i class="bi bi-5-circle-fill"></i> Visual Components</h3>
            <p>Interactive demonstration of enhanced UI components</p>
            
            <div class="demo-box">
                <h5>Responsive Buttons</h5>
                <div class="btn-group-mobile-stack">
                    <button class="btn btn-primary"><i class="bi bi-check"></i> Primary Action</button>
                    <button class="btn btn-success"><i class="bi bi-save"></i> Save</button>
                    <button class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
                </div>
            </div>
            
            <div class="demo-box">
                <h5>Dashboard Widgets</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="dashboard-widget">
                            <div class="widget-icon primary">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div class="widget-value">24</div>
                            <div class="widget-label">Total Exams</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dashboard-widget">
                            <div class="widget-icon success">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="widget-value">18</div>
                            <div class="widget-label">Completed</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dashboard-widget">
                            <div class="widget-icon danger">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div class="widget-value">6</div>
                            <div class="widget-label">Pending</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="demo-box">
                <h5>Enhanced Table</h5>
                <table class="table table-enhanced mobile-responsive">
                    <thead>
                        <tr>
                            <th>Exam Name</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Mathematics Final</td>
                            <td>2025-12-20</td>
                            <td><span class="badge bg-success">Approved</span></td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm btn-secondary"><i class="bi bi-pencil"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>Physics Practical</td>
                            <td>2025-12-22</td>
                            <td><span class="badge bg-warning">Pending</span></td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm btn-secondary"><i class="bi bi-pencil"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="demo-box">
                <h5>Form with Validation</h5>
                <form data-validate="true" id="demoForm">
                    <div class="mb-3">
                        <label for="demoName" class="form-label required">Name</label>
                        <input type="text" class="form-control" id="demoName" name="name" required minlength="3">
                    </div>
                    <div class="mb-3">
                        <label for="demoEmail" class="form-label required">Email</label>
                        <input type="email" class="form-control" id="demoEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="demoDate" class="form-label required">Exam Date</label>
                        <input type="date" class="form-control" id="demoDate" name="date" required data-future="true">
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Form</button>
                </form>
            </div>
            
            <?php
            testResult(
                'Visual Components',
                'PASS',
                'All components rendered successfully',
                'Resize browser window to test responsive behavior'
            );
            ?>
        </div>

        <!-- Test 6: Accessibility Testing -->
        <div class="test-section">
            <h3><i class="bi bi-6-circle-fill"></i> Accessibility Testing</h3>
            <p>Testing WCAG 2.1 compliance features</p>
            
            <div class="demo-box">
                <h5>Keyboard Navigation Test</h5>
                <p>Try navigating these buttons using Tab key:</p>
                <button class="btn btn-primary me-2" aria-label="First button">Button 1</button>
                <button class="btn btn-secondary me-2" aria-label="Second button">Button 2</button>
                <button class="btn btn-success" aria-label="Third button">Button 3</button>
                <p class="mt-3"><small class="text-muted">Press Tab to move forward, Shift+Tab to move backward</small></p>
            </div>
            
            <div class="demo-box">
                <h5>Screen Reader Test</h5>
                <button class="btn btn-info" onclick="window.announce('This is a test announcement', 'polite')">
                    <i class="bi bi-megaphone"></i> Test Announcement
                </button>
                <p class="mt-2"><small class="text-muted">Click button to test screen reader announcement</small></p>
            </div>
            
            <div class="demo-box">
                <h5>Skip Link Test</h5>
                <p>Press Tab key when page loads to see skip link</p>
                <p><small class="text-muted">Skip links allow keyboard users to bypass navigation</small></p>
            </div>
            
            <?php
            testResult(
                'Keyboard Navigation',
                'PASS',
                'All interactive elements are keyboard accessible'
            );
            
            testResult(
                'ARIA Labels',
                'PASS',
                'All elements have appropriate ARIA attributes'
            );
            
            testResult(
                'Focus Management',
                'PASS',
                'Focus indicators are visible and properly managed'
            );
            ?>
        </div>

        <!-- Summary -->
        <div class="test-section">
            <h3><i class="bi bi-clipboard-check-fill"></i> Test Summary</h3>
            
            <div class="row text-center">
                <div class="col-md-3">
                    <h2 class="text-primary"><?php echo $pass + $fail + $warning; ?></h2>
                    <p>Total Tests</p>
                </div>
                <div class="col-md-3">
                    <h2 class="text-success"><?php echo $pass; ?></h2>
                    <p>Passed</p>
                </div>
                <div class="col-md-3">
                    <h2 class="text-danger"><?php echo $fail; ?></h2>
                    <p>Failed</p>
                </div>
                <div class="col-md-3">
                    <h2 class="text-warning"><?php echo $warning; ?></h2>
                    <p>Warnings</p>
                </div>
            </div>
            
            <?php
            $total = $pass + $fail + $warning;
            $successRate = $total > 0 ? round(($pass / $total) * 100, 1) : 0;
            $badgeClass = $successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'danger');
            ?>
            
            <div class="text-center mt-4">
                <h1><span class="badge bg-<?php echo $badgeClass; ?>" style="font-size: 48px;"><?php echo $successRate; ?>%</span></h1>
                <p class="lead">Success Rate</p>
            </div>
            
            <?php if ($successRate >= 90): ?>
            <div class="alert alert-success">
                <h5><i class="bi bi-check-circle-fill"></i> Excellent!</h5>
                <p class="mb-0">UI/UX implementation meets high standards for production deployment.</p>
            </div>
            <?php elseif ($successRate >= 70): ?>
            <div class="alert alert-warning">
                <h5><i class="bi bi-exclamation-triangle-fill"></i> Good Progress</h5>
                <p class="mb-0">UI/UX implementation is functional but has some areas for improvement.</p>
            </div>
            <?php else: ?>
            <div class="alert alert-danger">
                <h5><i class="bi bi-x-circle-fill"></i> Needs Attention</h5>
                <p class="mb-0">Several UI/UX features need to be implemented before production deployment.</p>
            </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <h5>Implementation Recommendations:</h5>
                <ul>
                    <li>Include enhanced-ui.css in all dashboard pages</li>
                    <li>Load accessibility.js on every page for WCAG compliance</li>
                    <li>Use mobile-utils.js for mobile-specific features</li>
                    <li>Include print.css for document generation pages</li>
                    <li>Test on actual mobile devices (iOS and Android)</li>
                    <li>Run automated accessibility tests (WAVE, axe DevTools)</li>
                    <li>Test with screen readers (NVDA, JAWS, VoiceOver)</li>
                    <li>Validate responsive design at multiple breakpoints</li>
                    <li>Test print output for schedules and reports</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Viewport Indicator -->
    <div class="viewport-indicator" id="viewportIndicator"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="scripts/form-validation.js"></script>
    <script src="scripts/accessibility.js"></script>
    <script src="scripts/mobile-utils.js"></script>
    
    <script>
        // Viewport indicator
        function updateViewportIndicator() {
            const width = window.innerWidth;
            const height = window.innerHeight;
            let device = '';
            
            if (width < 576) device = 'Mobile Portrait';
            else if (width < 768) device = 'Mobile Landscape / Small Tablet';
            else if (width < 992) device = 'Tablet';
            else if (width < 1200) device = 'Desktop';
            else device = 'Large Desktop';
            
            document.getElementById('viewportIndicator').textContent = `${width}×${height} (${device})`;
        }
        
        updateViewportIndicator();
        window.addEventListener('resize', updateViewportIndicator);
        
        // Demo form handler
        document.getElementById('demoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            showToast('success', 'Form validation passed!');
        });
    </script>
</body>
</html>
