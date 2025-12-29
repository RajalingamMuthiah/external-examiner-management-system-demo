<?php
/**
 * DocumentsController - EEMS Documents Module
 */
class DocumentsController
{
    private PDO $pdo;
    private Document $documentModel;
    private int $userId;
    private string $role;
    private $collegeId;
    private $departmentId;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->documentModel = new Document($pdo);
        $this->userId = $_SESSION['user_id'] ?? 0;
        $this->role = $_SESSION['role'] ?? 'guest';
        $this->collegeId = $_SESSION['college_id'] ?? null;
        $this->departmentId = $_SESSION['department_id'] ?? null;
    }

    private function currentAcademicYear(): string
    {
        // Ultimatix style: e.g., 2025-26 based on today's date
        $y = (int)date('Y');
        $m = (int)date('n');
        // Assume academic year starts in July; adjust if needed
        if ($m >= 7) {
            return sprintf('%d-%02d', $y, ($y + 1) % 100);
        }
        return sprintf('%d-%02d', $y - 1, $y % 100);
    }

    private function getSelectedYear(): string
    {
        $year = isset($_GET['year']) ? trim($_GET['year']) : '';
        if ($year === '') {
            $year = $this->currentAcademicYear();
        }
        return $year;
    }

    private function requireAuth(array $roles = []): void
    {
        if (!function_exists('require_auth')) {
            require_once __DIR__ . '/../../../includes/security.php';
        }
        require_auth();
        if ($roles) {
            require_role($roles, true);
        }
    }

    private function render(string $view, array $vars = []): void
    {
        $viewFile = __DIR__ . '/../views/' . $view;
        extract($vars);
        include __DIR__ . '/../views/components/_header.php';
        include $viewFile;
        include __DIR__ . '/../views/components/_footer.php';
    }

    public function index(): void
    {
        $this->requireAuth(['admin','teacher','faculty','hod','principal','vice-principal','external_examiner']);
        $year = $this->getSelectedYear();
        $data = [];
        switch ($this->normalizeRole($this->role)) {
            case 'teacher':
            case 'faculty':
                $data['myUploads'] = $this->documentModel->getMyUploads($this->userId, $year);
                $data['received'] = $this->documentModel->getReceivedForTeacher($this->userId, $year);
                $this->render('index.php', ['year' => $year, 'role' => 'teacher'] + $data);
                return;
            case 'hod':
                $data['all'] = $this->documentModel->getForHod((string)$this->departmentId, $year);
                $this->render('hod_review.php', ['year' => $year] + $data);
                return;
            case 'vice-principal':
            case 'principal':
                $filters = [
                    'department' => $_GET['department'] ?? null,
                    'teacher' => isset($_GET['teacher']) ? (int)$_GET['teacher'] : null,
                    'doc_type' => $_GET['doc_type'] ?? null,
                ];
                $data['all'] = $this->documentModel->getForVp($filters, $year);
                $this->render('vp_college_documents.php', ['year' => $year, 'filters' => $filters] + $data);
                return;
            case 'admin':
                $data['all'] = $this->documentModel->getForAdmin($year);
                $this->render('admin_all_documents.php', ['year' => $year] + $data);
                return;
            case 'external_examiner':
                $data['received'] = $this->documentModel->getReceivedForTeacher($this->userId, $year);
                $this->render('received_documents.php', ['year' => $year] + $data);
                return;
            default:
                http_response_code(403);
                echo 'Forbidden';
        }
    }

    public function uploadGet(): void
    {
        $this->requireAuth(['teacher','faculty']);
        $year = $this->getSelectedYear();
        $this->render('teacher_upload.php', ['year' => $year]);
    }

    public function uploadPost(): void
    {
        $this->requireAuth(['teacher','faculty']);
        $year = isset($_POST['academic_year']) ? trim($_POST['academic_year']) : $this->currentAcademicYear();
        if (!$year) {
            $this->jsonError('Academic Year required');
            return;
        }
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->jsonError('File upload failed');
            return;
        }
        $allowed = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $mime = mime_content_type($_FILES['file']['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $this->jsonError('Only PDF/DOC/DOCX allowed');
            return;
        }
        $baseDir = realpath(__DIR__ . '/../../../');
        $uploadDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $year;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            $this->jsonError('Failed to prepare upload directory');
            return;
        }
        $safeName = $this->sanitizeFileName($_FILES['file']['name']);
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            $this->jsonError('Failed to save file');
            return;
        }
        $relPath = '/uploads/documents/' . $year . '/' . $safeName;
        $data = [
            'uploaded_by_teacher_id' => $this->userId,
            'uploaded_for_teacher_id' => isset($_POST['uploaded_for_teacher_id']) ? (int)$_POST['uploaded_for_teacher_id'] : null,
            'uploaded_for_college_id' => isset($_POST['uploaded_for_college_id']) ? (int)$_POST['uploaded_for_college_id'] : null,
            'exam_id' => isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0,
            'doc_type' => $_POST['doc_type'] ?? null,
            'doc_title' => $_POST['doc_title'] ?? null,
            'file_name' => $safeName,
            'file_path' => $relPath,
            'academic_year' => $year,
            'status' => 'sent',
        ];
        $id = $this->documentModel->create($data);
        $this->jsonOk(['id' => $id, 'file_path' => $relPath]);
    }

    public function myUploads(): void
    {
        $this->requireAuth(['teacher','faculty']);
        $year = $this->getSelectedYear();
        $list = $this->documentModel->getMyUploads($this->userId, $year);
        $this->render('teacher_uploaded_list.php', ['year' => $year, 'list' => $list]);
    }

    public function received(): void
    {
        $this->requireAuth(['teacher','faculty','external_examiner']);
        $year = $this->getSelectedYear();
        $list = $this->documentModel->getReceivedForTeacher($this->userId, $year);
        $this->render('received_documents.php', ['year' => $year, 'list' => $list]);
    }

    public function hodReview(): void
    {
        $this->requireAuth(['hod']);
        $year = $this->getSelectedYear();
        $list = $this->documentModel->getForHod((string)$this->departmentId, $year);
        $this->render('hod_review.php', ['year' => $year, 'list' => $list]);
    }

    public function hodActionPost(): void
    {
        $this->requireAuth(['hod']);
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $action = $_POST['action'] ?? ''; // approve | reject | request_changes
        $comments = $_POST['comments'] ?? null;
        $statusMap = [
            'approve' => 'approved',
            'reject' => 'rejected',
            'request_changes' => 'changes_requested',
        ];
        if (!$id || !isset($statusMap[$action])) {
            $this->jsonError('Invalid request');
            return;
        }
        $ok = $this->documentModel->setStatus($id, $statusMap[$action], $comments, $this->userId);
        $ok ? $this->jsonOk() : $this->jsonError('Failed to update');
    }

    public function vpAll(): void
    {
        $this->requireAuth(['vice-principal','principal']);
        $year = $this->getSelectedYear();
        $filters = [
            'department' => $_GET['department'] ?? null,
            'teacher' => isset($_GET['teacher']) ? (int)$_GET['teacher'] : null,
            'doc_type' => $_GET['doc_type'] ?? null,
        ];
        $list = $this->documentModel->getForVp($filters, $year);
        $this->render('vp_college_documents.php', ['year' => $year, 'filters' => $filters, 'list' => $list]);
    }

    public function adminAll(): void
    {
        $this->requireAuth(['admin']);
        $year = $this->getSelectedYear();
        $list = $this->documentModel->getForAdmin($year);
        $this->render('admin_all_documents.php', ['year' => $year, 'list' => $list]);
    }

    public function download(int $id): void
    {
        $this->requireAuth(['admin','teacher','faculty','hod','principal','vice-principal','external_examiner']);
        $doc = $this->documentModel->getById($id);
        if (!$doc) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        if (!$this->canAccessDocument($doc)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        $baseDir = realpath(__DIR__ . '/../../../');
        $absolutePath = $baseDir . $doc['file_path'];
        if (!file_exists($absolutePath)) {
            http_response_code(404);
            echo 'File missing';
            return;
        }
        $this->sendFile($absolutePath, $doc['file_name']);
    }

    private function sendFile(string $path, string $name): void
    {
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($name) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function canAccessDocument(array $doc): bool
    {
        $role = $this->normalizeRole($this->role);
        if (in_array($role, ['admin','hod','vice-principal','principal'], true)) {
            return true;
        }
        if (in_array($role, ['teacher','faculty'], true)) {
            return ($doc['uploaded_by_teacher_id'] == $this->userId) || ($doc['uploaded_for_teacher_id'] == $this->userId);
        }
        if ($role === 'external_examiner') {
            return ($doc['uploaded_for_teacher_id'] == $this->userId);
        }
        return false;
    }

    private function normalizeRole(string $role): string
    {
        $r = strtolower($role);
        $map = [
            'vice_principal' => 'vice-principal',
        ];
        return $map[$r] ?? $r;
    }

    private function sanitizeFileName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $name);
        return preg_replace('/_+/', '_', $name);
    }

    private function jsonOk(array $extra = []): void
    {
        header('Content-Type: application/json');
        echo json_encode(['success' => true] + $extra);
        exit;
    }

    private function jsonError(string $message): void
    {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}
