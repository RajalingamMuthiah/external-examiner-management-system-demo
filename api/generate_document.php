<?php
/**
 * DOCUMENT GENERATION API
 * ===================================
 * Generate PDF documents for exams, invitations, schedules, reports
 * Supports: Exam Schedules, Invitation Letters, Duty Rosters, Reports
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth();

// Get document type and related ID
$docType = $_GET['type'] ?? '';
$examId = (int)($_GET['exam_id'] ?? 0);
$inviteId = (int)($_GET['invite_id'] ?? 0);
$format = $_GET['format'] ?? 'pdf'; // pdf or html

// Validate parameters
if (empty($docType) || ($examId === 0 && $inviteId === 0)) {
    http_response_code(400);
    die('Missing required parameters: type and exam_id or invite_id');
}

// Check if TCPDF is available, fallback to HTML
$useTCPDF = file_exists(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');

try {
    // Generate document based on type
    switch ($docType) {
        case 'exam_schedule':
            generateExamSchedule($pdo, $examId, $format, $useTCPDF);
            break;
            
        case 'invitation_letter':
            generateInvitationLetter($pdo, $inviteId, $format, $useTCPDF);
            break;
            
        case 'duty_roster':
            generateDutyRoster($pdo, $examId, $format, $useTCPDF);
            break;
            
        case 'exam_report':
            generateExamReport($pdo, $examId, $format, $useTCPDF);
            break;
            
        default:
            http_response_code(400);
            die('Invalid document type');
    }
    
} catch (Exception $e) {
    error_log('Document generation error: ' . $e->getMessage());
    http_response_code(500);
    die('Error generating document: ' . $e->getMessage());
}

// ============================================================================
// DOCUMENT GENERATION FUNCTIONS
// ============================================================================

/**
 * Generate Exam Schedule PDF/HTML
 */
function generateExamSchedule($pdo, $examId, $format, $useTCPDF) {
    // Fetch exam details with college info
    $stmt = $pdo->prepare("
        SELECT e.*, c.college_name, c.address as college_address, c.phone as college_phone,
               d.dept_name, u.name as creator_name
        FROM exams e
        LEFT JOIN colleges c ON e.college_id = c.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        throw new Exception('Exam not found');
    }
    
    // Fetch assigned examiners
    $stmt = $pdo->prepare("
        SELECT a.*, u.name, u.email, u.phone, a.role as duty_role
        FROM assignments a
        INNER JOIN users u ON a.faculty_id = u.id
        WHERE a.exam_id = ?
        ORDER BY a.role
    ");
    $stmt->execute([$examId]);
    $examiners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'html' || !$useTCPDF) {
        generateExamScheduleHTML($exam, $examiners);
    } else {
        generateExamSchedulePDF($exam, $examiners);
    }
}

/**
 * Generate Exam Schedule HTML (with print CSS)
 */
function generateExamScheduleHTML($exam, $examiners) {
    header('Content-Type: text/html; charset=utf-8');
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Exam Schedule - ' . htmlspecialchars($exam['title']) . '</title>
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
        body {
            font-family: "Times New Roman", Times, serif;
            margin: 20px;
            line-height: 1.6;
        }
        .letterhead {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .letterhead h1 {
            margin: 5px 0;
            font-size: 24px;
            color: #1e3c72;
        }
        .letterhead p {
            margin: 3px 0;
            font-size: 12px;
        }
        .doc-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
            text-decoration: underline;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 10px;
            margin: 20px 0;
        }
        .info-label {
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #000;
            padding-top: 10px;
            font-size: 11px;
        }
        .signature-section {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            text-align: center;
        }
        @media print {
            button { display: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()" style="position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: #1e3c72; color: white; border: none; cursor: pointer; border-radius: 5px; z-index: 1000;">
        Print / Save as PDF
    </button>
    
    <div class="letterhead">
        <h1>' . htmlspecialchars($exam['college_name'] ?? 'External Examiner Management System') . '</h1>
        <p>' . htmlspecialchars($exam['college_address'] ?? '') . '</p>
        <p>Phone: ' . htmlspecialchars($exam['college_phone'] ?? '') . '</p>
    </div>
    
    <div class="doc-title">EXAMINATION SCHEDULE</div>
    
    <div class="info-grid">
        <div class="info-label">Exam Code:</div>
        <div>EX-' . str_pad($exam['id'], 5, '0', STR_PAD_LEFT) . '</div>
        
        <div class="info-label">Exam Title:</div>
        <div>' . htmlspecialchars($exam['title']) . '</div>
        
        <div class="info-label">Subject:</div>
        <div>' . htmlspecialchars($exam['subject'] ?? 'N/A') . '</div>
        
        <div class="info-label">Department:</div>
        <div>' . htmlspecialchars($exam['dept_name'] ?? 'N/A') . '</div>
        
        <div class="info-label">Exam Date:</div>
        <div>' . date('l, F j, Y', strtotime($exam['exam_date'])) . '</div>
        
        <div class="info-label">Exam Time:</div>
        <div>' . date('g:i A', strtotime($exam['start_time'])) . ' - ' . date('g:i A', strtotime($exam['end_time'])) . '</div>
        
        <div class="info-label">Venue:</div>
        <div>' . htmlspecialchars($exam['venue'] ?? 'TBD') . '</div>
        
        <div class="info-label">Status:</div>
        <div style="text-transform: uppercase;">' . htmlspecialchars($exam['status']) . '</div>
    </div>';
    
    if (!empty($exam['description'])) {
        $html .= '
    <div style="margin: 20px 0;">
        <strong>Description:</strong><br>
        ' . nl2br(htmlspecialchars($exam['description'])) . '
    </div>';
    }
    
    if (!empty($examiners)) {
        $html .= '
    <h3>Examiner Assignments</h3>
    <table>
        <thead>
            <tr>
                <th>S.No</th>
                <th>Name</th>
                <th>Role</th>
                <th>Contact</th>
            </tr>
        </thead>
        <tbody>';
        
        $sno = 1;
        foreach ($examiners as $examiner) {
            $html .= '
            <tr>
                <td>' . $sno++ . '</td>
                <td>' . htmlspecialchars($examiner['name']) . '</td>
                <td>' . ucwords(str_replace('_', ' ', $examiner['duty_role'])) . '</td>
                <td>' . htmlspecialchars($examiner['email']) . '<br>' . htmlspecialchars($examiner['phone'] ?? '') . '</td>
            </tr>';
        }
        
        $html .= '
        </tbody>
    </table>';
    }
    
    $html .= '
    <div class="signature-section">
        <div class="signature-box">
            <div>_____________________</div>
            <div>Prepared By</div>
            <div>' . htmlspecialchars($exam['creator_name'] ?? '') . '</div>
        </div>
        <div class="signature-box">
            <div>_____________________</div>
            <div>HOD Signature</div>
        </div>
        <div class="signature-box">
            <div>_____________________</div>
            <div>Principal Signature</div>
        </div>
    </div>
    
    <div class="footer">
        <p><strong>Note:</strong> This is a computer-generated document. Please verify all details before exam day.</p>
        <p>Generated on: ' . date('F j, Y g:i A') . ' | Document ID: SCHED-' . $exam['id'] . '-' . time() . '</p>
    </div>
</body>
</html>';
    
    echo $html;
}

/**
 * Generate Invitation Letter HTML
 */
function generateInvitationLetter($pdo, $inviteId, $format, $useTCPDF) {
    // Fetch invite details
    $stmt = $pdo->prepare("
        SELECT ei.*, e.title as exam_title, e.exam_date, e.start_time, e.end_time, e.venue,
               e.subject, c.college_name, c.address as college_address, c.phone as college_phone,
               u.name as invited_by_name, u.designation as invited_by_designation
        FROM exam_invites ei
        INNER JOIN exams e ON ei.exam_id = e.id
        LEFT JOIN colleges c ON ei.college_id = c.id
        LEFT JOIN users u ON ei.invited_by = u.id
        WHERE ei.id = ?
    ");
    $stmt->execute([$inviteId]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invite) {
        throw new Exception('Invitation not found');
    }
    
    header('Content-Type: text/html; charset=utf-8');
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invitation Letter - ' . htmlspecialchars($invite['name']) . '</title>
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            margin: 40px;
            line-height: 1.8;
        }
        .letterhead {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .letterhead h1 {
            margin: 5px 0;
            font-size: 24px;
            color: #1e3c72;
        }
        .ref-date {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            font-size: 14px;
        }
        .subject {
            font-weight: bold;
            text-decoration: underline;
            margin: 20px 0;
        }
        .content {
            text-align: justify;
            margin: 20px 0;
        }
        .details-box {
            border: 2px solid #000;
            padding: 15px;
            margin: 20px 0;
            background: #f9f9f9;
        }
        .signature {
            margin-top: 60px;
            text-align: right;
        }
        @media print {
            button { display: none; }
        }
    </style>
</head>
<body>
    <button onclick="window.print()" style="position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: #1e3c72; color: white; border: none; cursor: pointer; border-radius: 5px; z-index: 1000;">
        Print / Save as PDF
    </button>
    
    <div class="letterhead">
        <h1>' . htmlspecialchars($invite['college_name'] ?? 'External Examiner Management System') . '</h1>
        <p>' . htmlspecialchars($invite['college_address'] ?? '') . '</p>
        <p>Phone: ' . htmlspecialchars($invite['college_phone'] ?? '') . '</p>
    </div>
    
    <div class="ref-date">
        <div><strong>Ref No:</strong> EEMS/INV/' . date('Y') . '/' . str_pad($invite['id'], 4, '0', STR_PAD_LEFT) . '</div>
        <div><strong>Date:</strong> ' . date('F j, Y') . '</div>
    </div>
    
    <div style="margin: 30px 0;">
        <strong>To,</strong><br>
        ' . htmlspecialchars($invite['name']) . '<br>
        ' . htmlspecialchars($invite['email']) . '
    </div>
    
    <div class="subject">
        <strong>Subject:</strong> Invitation as ' . ucwords(str_replace('_', ' ', $invite['role'])) . ' for ' . htmlspecialchars($invite['exam_title']) . '
    </div>
    
    <div class="content">
        <p>Dear ' . htmlspecialchars($invite['name']) . ',</p>
        
        <p>We are pleased to invite you to serve as <strong>' . ucwords(str_replace('_', ' ', $invite['role'])) . '</strong> 
        for the upcoming examination at our institution. Your expertise and experience in the field make you an ideal 
        candidate for this responsibility.</p>
        
        <div class="details-box">
            <strong>EXAMINATION DETAILS</strong><br><br>
            <strong>Exam Title:</strong> ' . htmlspecialchars($invite['exam_title']) . '<br>
            <strong>Subject:</strong> ' . htmlspecialchars($invite['subject'] ?? 'N/A') . '<br>
            <strong>Date:</strong> ' . date('l, F j, Y', strtotime($invite['exam_date'])) . '<br>
            <strong>Time:</strong> ' . date('g:i A', strtotime($invite['start_time'])) . ' - ' . date('g:i A', strtotime($invite['end_time'])) . '<br>
            <strong>Venue:</strong> ' . htmlspecialchars($invite['venue'] ?? 'Will be communicated') . '<br>
            <strong>Role:</strong> ' . ucwords(str_replace('_', ' ', $invite['role'])) . '<br>
            <strong>Duty Type:</strong> ' . ucwords($invite['duty_type'] ?? 'General') . '
        </div>
        
        <p>We request you to kindly confirm your availability for this assignment at your earliest convenience. 
        Your confirmation will help us complete the examination arrangements in a timely manner.</p>
        
        <p>Please respond to this invitation by clicking the link provided in the email or by contacting us directly.</p>
        
        <p>We look forward to your positive response and thank you in advance for your cooperation.</p>
    </div>
    
    <div class="signature">
        <p>Yours sincerely,</p>
        <br><br>
        <p><strong>' . htmlspecialchars($invite['invited_by_name'] ?? 'Examination Coordinator') . '</strong><br>
        ' . htmlspecialchars($invite['invited_by_designation'] ?? '') . '<br>
        ' . htmlspecialchars($invite['college_name'] ?? '') . '</p>
    </div>
    
    <div style="margin-top: 40px; border-top: 1px solid #ccc; padding-top: 10px; font-size: 11px; color: #666;">
        <p><strong>Note:</strong> This is an official invitation. Please keep this for your records.</p>
        <p>Document ID: INV-' . $invite['id'] . '-' . time() . ' | Generated: ' . date('F j, Y g:i A') . '</p>
    </div>
</body>
</html>';
    
    echo $html;
}

/**
 * Generate Duty Roster HTML
 */
function generateDutyRoster($pdo, $examId, $format, $useTCPDF) {
    // Similar to exam schedule but focused on duty assignments
    generateExamSchedule($pdo, $examId, $format, $useTCPDF);
}

/**
 * Generate Exam Report HTML
 */
function generateExamReport($pdo, $examId, $format, $useTCPDF) {
    $stmt = $pdo->prepare("
        SELECT e.*, c.college_name, d.dept_name, u.name as creator_name,
               COUNT(DISTINCT a.faculty_id) as total_examiners,
               COUNT(DISTINCT r.rating_id) as total_ratings,
               AVG(r.score) as avg_rating
        FROM exams e
        LEFT JOIN colleges c ON e.college_id = c.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN assignments a ON e.id = a.exam_id
        LEFT JOIN ratings r ON e.id = r.exam_id
        WHERE e.id = ?
        GROUP BY e.id
    ");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        throw new Exception('Exam not found');
    }
    
    // Get approval history
    $stmt = $pdo->prepare("
        SELECT ap.*, u.name as approver_name
        FROM approvals ap
        LEFT JOIN users u ON ap.approver_id = u.id
        WHERE ap.exam_id = ?
        ORDER BY ap.created_at
    ");
    $stmt->execute([$examId]);
    $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/html; charset=utf-8');
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Exam Report - ' . htmlspecialchars($exam['title']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        .status-' . $exam['status'] . ' { 
            background: ' . ($exam['status'] === 'completed' ? '#d4edda' : '#fff3cd') . ';
            padding: 5px 10px;
            border-radius: 3px;
        }
        @media print { button { display: none; } }
    </style>
</head>
<body>
    <button onclick="window.print()" style="position: fixed; top: 10px; right: 10px; padding: 10px 20px;">Print</button>
    
    <div class="header">
        <h1>EXAMINATION REPORT</h1>
        <p>' . htmlspecialchars($exam['college_name']) . '</p>
    </div>
    
    <h2>Exam Summary</h2>
    <table>
        <tr><td><strong>Exam ID</strong></td><td>EX-' . str_pad($exam['id'], 5, '0', STR_PAD_LEFT) . '</td></tr>
        <tr><td><strong>Title</strong></td><td>' . htmlspecialchars($exam['title']) . '</td></tr>
        <tr><td><strong>Subject</strong></td><td>' . htmlspecialchars($exam['subject'] ?? 'N/A') . '</td></tr>
        <tr><td><strong>Department</strong></td><td>' . htmlspecialchars($exam['dept_name'] ?? 'N/A') . '</td></tr>
        <tr><td><strong>Date</strong></td><td>' . date('F j, Y', strtotime($exam['exam_date'])) . '</td></tr>
        <tr><td><strong>Status</strong></td><td><span class="status-' . $exam['status'] . '">' . strtoupper($exam['status']) . '</span></td></tr>
        <tr><td><strong>Total Examiners</strong></td><td>' . $exam['total_examiners'] . '</td></tr>
        <tr><td><strong>Average Rating</strong></td><td>' . ($exam['avg_rating'] ? number_format($exam['avg_rating'], 2) . ' / 5.0' : 'Not rated yet') . '</td></tr>
        <tr><td><strong>Created By</strong></td><td>' . htmlspecialchars($exam['creator_name']) . '</td></tr>
        <tr><td><strong>Created On</strong></td><td>' . date('F j, Y', strtotime($exam['created_at'])) . '</td></tr>
    </table>';
    
    if (!empty($approvals)) {
        $html .= '
    <h2>Approval History</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Approver</th>
                <th>Role</th>
                <th>Decision</th>
                <th>Comments</th>
            </tr>
        </thead>
        <tbody>';
        
        foreach ($approvals as $approval) {
            $html .= '
            <tr>
                <td>' . date('M j, Y', strtotime($approval['created_at'])) . '</td>
                <td>' . htmlspecialchars($approval['approver_name']) . '</td>
                <td>' . strtoupper($approval['approver_role']) . '</td>
                <td>' . strtoupper(str_replace('_', ' ', $approval['decision'])) . '</td>
                <td>' . htmlspecialchars($approval['comments'] ?? '-') . '</td>
            </tr>';
        }
        
        $html .= '
        </tbody>
    </table>';
    }
    
    $html .= '
    <div style="margin-top: 40px; font-size: 11px; color: #666;">
        <p>Report generated on: ' . date('F j, Y g:i A') . '</p>
        <p>This is a system-generated report from EEMS.</p>
    </div>
</body>
</html>';
    
    echo $html;
}

/**
 * Generate PDF using TCPDF (if available)
 */
function generateExamSchedulePDF($exam, $examiners) {
    // Placeholder - requires TCPDF library installation
    // For now, fallback to HTML
    generateExamScheduleHTML($exam, $examiners);
}
