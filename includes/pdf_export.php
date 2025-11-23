/**
 * EEMS - PDF Export System
 * =====================================================
 * Generate professional PDF reports and exports
 * Uses FPDF library (lightweight, no dependencies)
 */

class PDFExporter {
    private $pdf;
    private $primaryColor;
    private $secondaryColor;
    
    public function __construct() {
        // Check if FPDF is available
        if (!class_exists('FPDF')) {
            // Include FPDF if available
            $fpdfPath = __DIR__ . '/../vendor/fpdf/fpdf.php';
            if (file_exists($fpdfPath)) {
                require_once($fpdfPath);
            } else {
                throw new Exception('FPDF library not found. Please install: composer require setasign/fpdf or download from http://www.fpdf.org/');
            }
        }
        
        $this->primaryColor = [102, 126, 234]; // #667eea
        $this->secondaryColor = [118, 75, 162]; // #764ba2
    }
    
    /**
     * Initialize PDF with standard settings
     */
    private function initPDF($orientation = 'P', $title = 'EEMS Report') {
        $this->pdf = new FPDF($orientation, 'mm', 'A4');
        $this->pdf->SetTitle($title);
        $this->pdf->SetAuthor('EEMS - Exam Management System');
        $this->pdf->SetCreator('EEMS');
        $this->pdf->AddPage();
        
        return $this->pdf;
    }
    
    /**
     * Add header with logo and title
     */
    private function addHeader($title, $subtitle = '') {
        // Gradient background for header
        $this->pdf->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->pdf->Rect(0, 0, 210, 40, 'F');
        
        // Title
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->SetFont('Arial', 'B', 24);
        $this->pdf->SetXY(15, 10);
        $this->pdf->Cell(0, 10, $title, 0, 1);
        
        // Subtitle
        if ($subtitle) {
            $this->pdf->SetFont('Arial', '', 12);
            $this->pdf->SetXY(15, 22);
            $this->pdf->Cell(0, 8, $subtitle, 0, 1);
        }
        
        // EEMS branding
        $this->pdf->SetFont('Arial', 'B', 10);
        $this->pdf->SetXY(15, 30);
        $this->pdf->Cell(0, 6, 'EEMS - Exam Management System', 0, 1);
        
        // Reset position and color
        $this->pdf->SetY(45);
        $this->pdf->SetTextColor(0, 0, 0);
    }
    
    /**
     * Add footer with page number
     */
    private function addFooter() {
        $this->pdf->SetY(-15);
        $this->pdf->SetFont('Arial', 'I', 8);
        $this->pdf->SetTextColor(128, 128, 128);
        $this->pdf->Cell(0, 10, 'Generated on ' . date('F d, Y') . ' - Page ' . $this->pdf->PageNo(), 0, 0, 'C');
    }
    
    /**
     * Export User List
     */
    public function exportUserList($users, $filters = []) {
        $this->initPDF('P', 'User List Report');
        
        // Header
        $filterText = '';
        if (!empty($filters['status'])) {
            $filterText = 'Status: ' . ucfirst($filters['status']);
        }
        if (!empty($filters['post'])) {
            $filterText .= ($filterText ? ' | ' : '') . 'Role: ' . ucfirst($filters['post']);
        }
        
        $this->addHeader('User List Report', $filterText ?: 'All Users');
        
        // Summary
        $this->pdf->SetFont('Arial', 'B', 12);
        $this->pdf->Cell(0, 8, 'Summary', 0, 1);
        $this->pdf->SetFont('Arial', '', 10);
        $this->pdf->Cell(0, 6, 'Total Users: ' . count($users), 0, 1);
        $this->pdf->Ln(5);
        
        // Table Header
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->SetFont('Arial', 'B', 10);
        $this->pdf->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->pdf->Cell(50, 8, 'Name', 1, 0, 'L', true);
        $this->pdf->Cell(55, 8, 'Email', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, 'Role', 1, 0, 'C', true);
        $this->pdf->Cell(45, 8, 'College', 1, 1, 'L', true);
        
        // Table Data
        $this->pdf->SetFont('Arial', '', 9);
        $count = 1;
        foreach ($users as $user) {
            // Check if need new page
            if ($this->pdf->GetY() > 260) {
                $this->pdf->AddPage();
                $this->pdf->SetY(20);
                // Repeat header
                $this->pdf->SetFillColor(240, 240, 240);
                $this->pdf->SetFont('Arial', 'B', 10);
                $this->pdf->Cell(10, 8, '#', 1, 0, 'C', true);
                $this->pdf->Cell(50, 8, 'Name', 1, 0, 'L', true);
                $this->pdf->Cell(55, 8, 'Email', 1, 0, 'L', true);
                $this->pdf->Cell(30, 8, 'Role', 1, 0, 'C', true);
                $this->pdf->Cell(45, 8, 'College', 1, 1, 'L', true);
                $this->pdf->SetFont('Arial', '', 9);
            }
            
            $this->pdf->Cell(10, 7, $count++, 1, 0, 'C');
            $this->pdf->Cell(50, 7, substr($user['name'], 0, 25), 1, 0, 'L');
            $this->pdf->Cell(55, 7, substr($user['email'], 0, 30), 1, 0, 'L');
            $this->pdf->Cell(30, 7, ucfirst($user['post']), 1, 0, 'C');
            $this->pdf->Cell(45, 7, substr($user['college_name'], 0, 22), 1, 1, 'L');
        }
        
        $this->addFooter();
        
        return $this->pdf->Output('S'); // Return as string
    }
    
    /**
     * Export Exam Schedule
     */
    public function exportExamSchedule($exams, $filters = []) {
        $this->initPDF('L', 'Exam Schedule Report'); // Landscape
        
        $filterText = '';
        if (!empty($filters['college'])) {
            $filterText = 'College: ' . $filters['college'];
        }
        if (!empty($filters['status'])) {
            $filterText .= ($filterText ? ' | ' : '') . 'Status: ' . ucfirst($filters['status']);
        }
        
        $this->addHeader('Exam Schedule Report', $filterText ?: 'All Exams');
        
        // Summary
        $this->pdf->SetFont('Arial', 'B', 12);
        $this->pdf->Cell(0, 8, 'Summary', 0, 1);
        $this->pdf->SetFont('Arial', '', 10);
        $this->pdf->Cell(0, 6, 'Total Exams: ' . count($exams), 0, 1);
        $this->pdf->Ln(5);
        
        // Table Header
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->SetFont('Arial', 'B', 9);
        $this->pdf->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->pdf->Cell(65, 8, 'Exam Name', 1, 0, 'L', true);
        $this->pdf->Cell(40, 8, 'Subject', 1, 0, 'L', true);
        $this->pdf->Cell(28, 8, 'Date', 1, 0, 'C', true);
        $this->pdf->Cell(55, 8, 'College', 1, 0, 'L', true);
        $this->pdf->Cell(25, 8, 'Status', 1, 0, 'C', true);
        $this->pdf->Cell(20, 8, 'Faculty', 1, 1, 'C', true);
        
        // Table Data
        $this->pdf->SetFont('Arial', '', 8);
        $count = 1;
        foreach ($exams as $exam) {
            if ($this->pdf->GetY() > 180) {
                $this->pdf->AddPage();
                $this->pdf->SetY(20);
            }
            
            $this->pdf->Cell(10, 7, $count++, 1, 0, 'C');
            $this->pdf->Cell(65, 7, substr($exam['exam_name'] ?? $exam['title'], 0, 35), 1, 0, 'L');
            $this->pdf->Cell(40, 7, substr($exam['subject'] ?? 'N/A', 0, 20), 1, 0, 'L');
            $this->pdf->Cell(28, 7, date('M d, Y', strtotime($exam['exam_date'])), 1, 0, 'C');
            $this->pdf->Cell(55, 7, substr($exam['college_name'] ?? $exam['department'], 0, 28), 1, 0, 'L');
            
            // Status with color
            $status = $exam['status'] ?? 'Pending';
            if ($status == 'Approved') {
                $this->pdf->SetTextColor(0, 150, 0);
            } elseif ($status == 'Pending') {
                $this->pdf->SetTextColor(200, 100, 0);
            } else {
                $this->pdf->SetTextColor(200, 0, 0);
            }
            $this->pdf->Cell(25, 7, $status, 1, 0, 'C');
            $this->pdf->SetTextColor(0, 0, 0);
            
            $this->pdf->Cell(20, 7, $exam['faculty_count'] ?? '0', 1, 1, 'C');
        }
        
        $this->addFooter();
        
        return $this->pdf->Output('S');
    }
    
    /**
     * Export Faculty Workload Report
     */
    public function exportFacultyWorkload($facultyData, $college = '') {
        $this->initPDF('P', 'Faculty Workload Report');
        
        $subtitle = $college ? 'College: ' . $college : 'All Colleges';
        $this->addHeader('Faculty Workload Report', $subtitle);
        
        // Summary
        $this->pdf->SetFont('Arial', 'B', 12);
        $this->pdf->Cell(0, 8, 'Summary', 0, 1);
        $this->pdf->SetFont('Arial', '', 10);
        $this->pdf->Cell(0, 6, 'Total Faculty: ' . count($facultyData), 0, 1);
        
        $totalAssignments = array_sum(array_column($facultyData, 'assignment_count'));
        $this->pdf->Cell(0, 6, 'Total Assignments: ' . $totalAssignments, 0, 1);
        
        $avgAssignments = count($facultyData) > 0 ? round($totalAssignments / count($facultyData), 1) : 0;
        $this->pdf->Cell(0, 6, 'Average Assignments per Faculty: ' . $avgAssignments, 0, 1);
        $this->pdf->Ln(5);
        
        // Table Header
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->SetFont('Arial', 'B', 10);
        $this->pdf->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->pdf->Cell(70, 8, 'Faculty Name', 1, 0, 'L', true);
        $this->pdf->Cell(35, 8, 'Assignments', 1, 0, 'C', true);
        $this->pdf->Cell(35, 8, 'Upcoming', 1, 0, 'C', true);
        $this->pdf->Cell(35, 8, 'Completed', 1, 1, 'C', true);
        
        // Table Data
        $this->pdf->SetFont('Arial', '', 9);
        $count = 1;
        foreach ($facultyData as $faculty) {
            if ($this->pdf->GetY() > 260) {
                $this->pdf->AddPage();
                $this->pdf->SetY(20);
            }
            
            $this->pdf->Cell(10, 7, $count++, 1, 0, 'C');
            $this->pdf->Cell(70, 7, substr($faculty['faculty_name'] ?? $faculty['name'], 0, 35), 1, 0, 'L');
            $this->pdf->Cell(35, 7, $faculty['assignment_count'] ?? 0, 1, 0, 'C');
            $this->pdf->Cell(35, 7, $faculty['upcoming_assignments'] ?? 0, 1, 0, 'C');
            $this->pdf->Cell(35, 7, $faculty['past_assignments'] ?? 0, 1, 1, 'C');
        }
        
        $this->addFooter();
        
        return $this->pdf->Output('S');
    }
    
    /**
     * Export Analytics Report with Charts (text-based)
     */
    public function exportAnalyticsReport($data) {
        $this->initPDF('P', 'Analytics Report');
        
        $this->addHeader('System Analytics Report', 'Period: ' . date('F Y'));
        
        // Key Metrics
        $this->pdf->SetFont('Arial', 'B', 14);
        $this->pdf->Cell(0, 10, 'Key Metrics', 0, 1);
        
        $metrics = [
            ['Total Users', $data['total_users'] ?? 0],
            ['Active Exams', $data['active_exams'] ?? 0],
            ['Pending Approvals', $data['pending_approvals'] ?? 0],
            ['Faculty Assignments', $data['total_assignments'] ?? 0],
        ];
        
        foreach ($metrics as $metric) {
            $this->pdf->SetFillColor(245, 247, 250);
            $this->pdf->SetFont('Arial', '', 11);
            $this->pdf->Cell(100, 10, $metric[0], 1, 0, 'L', true);
            $this->pdf->SetFont('Arial', 'B', 12);
            $this->pdf->Cell(85, 10, $metric[1], 1, 1, 'C', true);
        }
        
        $this->pdf->Ln(10);
        
        // College-wise Statistics
        if (isset($data['college_stats']) && is_array($data['college_stats'])) {
            $this->pdf->SetFont('Arial', 'B', 14);
            $this->pdf->Cell(0, 10, 'College-wise Statistics', 0, 1);
            
            $this->pdf->SetFillColor(240, 240, 240);
            $this->pdf->SetFont('Arial', 'B', 10);
            $this->pdf->Cell(80, 8, 'College', 1, 0, 'L', true);
            $this->pdf->Cell(35, 8, 'Exams', 1, 0, 'C', true);
            $this->pdf->Cell(35, 8, 'Faculty', 1, 0, 'C', true);
            $this->pdf->Cell(35, 8, 'Pending', 1, 1, 'C', true);
            
            $this->pdf->SetFont('Arial', '', 9);
            foreach ($data['college_stats'] as $stat) {
                $this->pdf->Cell(80, 7, substr($stat['college_name'], 0, 40), 1, 0, 'L');
                $this->pdf->Cell(35, 7, $stat['total_exams'] ?? 0, 1, 0, 'C');
                $this->pdf->Cell(35, 7, $stat['total_faculty'] ?? 0, 1, 0, 'C');
                $this->pdf->Cell(35, 7, $stat['pending_exams'] ?? 0, 1, 1, 'C');
            }
        }
        
        $this->addFooter();
        
        return $this->pdf->Output('S');
    }
    
    /**
     * Download PDF file
     */
    public static function download($content, $filename) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}

// Helper function for easy PDF export
function exportToPDF($type, $data, $filters = []) {
    try {
        $exporter = new PDFExporter();
        $content = '';
        $filename = '';
        
        switch ($type) {
            case 'users':
                $content = $exporter->exportUserList($data, $filters);
                $filename = 'users_' . date('Y-m-d') . '.pdf';
                break;
                
            case 'exams':
                $content = $exporter->exportExamSchedule($data, $filters);
                $filename = 'exams_' . date('Y-m-d') . '.pdf';
                break;
                
            case 'workload':
                $content = $exporter->exportFacultyWorkload($data, $filters['college'] ?? '');
                $filename = 'faculty_workload_' . date('Y-m-d') . '.pdf';
                break;
                
            case 'analytics':
                $content = $exporter->exportAnalyticsReport($data);
                $filename = 'analytics_' . date('Y-m-d') . '.pdf';
                break;
                
            default:
                throw new Exception('Invalid export type');
        }
        
        PDFExporter::download($content, $filename);
        
    } catch (Exception $e) {
        error_log('PDF Export Error: ' . $e->getMessage());
        return false;
    }
}
