<?php
/**
 * Document Model - EEMS Documents Module
 * Provides CRUD and role-filtered queries for documents
 */
class Document
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO documents (
                    uploaded_by_teacher_id,
                    uploaded_for_teacher_id,
                    uploaded_for_college_id,
                    exam_id,
                    doc_type,
                    doc_title,
                    file_name,
                    file_path,
                    academic_year,
                    uploaded_on,
                    status,
                    comments,
                    approved_by,
                    approved_on
                ) VALUES (
                    :uploaded_by_teacher_id,
                    :uploaded_for_teacher_id,
                    :uploaded_for_college_id,
                    :exam_id,
                    :doc_type,
                    :doc_title,
                    :file_name,
                    :file_path,
                    :academic_year,
                    NOW(),
                    :status,
                    :comments,
                    :approved_by,
                    :approved_on
                )";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':uploaded_by_teacher_id' => $data['uploaded_by_teacher_id'],
            ':uploaded_for_teacher_id' => $data['uploaded_for_teacher_id'] ?? null,
            ':uploaded_for_college_id' => $data['uploaded_for_college_id'] ?? null,
            ':exam_id' => $data['exam_id'],
            ':doc_type' => $data['doc_type'] ?? null,
            ':doc_title' => $data['doc_title'] ?? null,
            ':file_name' => $data['file_name'],
            ':file_path' => $data['file_path'],
            ':academic_year' => $data['academic_year'],
            ':status' => $data['status'] ?? 'sent',
            ':comments' => $data['comments'] ?? null,
            ':approved_by' => $data['approved_by'] ?? null,
            ':approved_on' => $data['approved_on'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getMyUploads(int $teacherId, string $year): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE uploaded_by_teacher_id = ? AND academic_year = ? ORDER BY uploaded_on DESC");
        $stmt->execute([$teacherId, $year]);
        return $stmt->fetchAll();
    }

    public function getReceivedForTeacher(int $teacherId, string $year): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE uploaded_for_teacher_id = ? AND academic_year = ? ORDER BY uploaded_on DESC");
        $stmt->execute([$teacherId, $year]);
        return $stmt->fetchAll();
    }

    public function getForHod(string $deptIdOrName, string $year): array
    {
        // Assuming department filtering via uploaded_for_college_id or joining users, adjust as needed
        $stmt = $this->pdo->prepare("SELECT d.* FROM documents d WHERE d.academic_year = ? ORDER BY d.uploaded_on DESC");
        $stmt->execute([$year]);
        return $stmt->fetchAll();
    }

    public function getForVp(array $filters, string $year): array
    {
        $sql = "SELECT d.* FROM documents d WHERE d.academic_year = :year";
        $params = [':year' => $year];
        if (!empty($filters['department'])) {
            $sql .= " AND d.uploaded_for_college_id = :dept";
            $params[':dept'] = $filters['department'];
        }
        if (!empty($filters['teacher'])) {
            $sql .= " AND (d.uploaded_by_teacher_id = :teacher OR d.uploaded_for_teacher_id = :teacher)";
            $params[':teacher'] = $filters['teacher'];
        }
        if (!empty($filters['doc_type'])) {
            $sql .= " AND d.doc_type = :doc_type";
            $params[':doc_type'] = $filters['doc_type'];
        }
        $sql .= " ORDER BY d.uploaded_on DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getForAdmin(string $year): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE academic_year = ? ORDER BY uploaded_on DESC");
        $stmt->execute([$year]);
        return $stmt->fetchAll();
    }

    public function setStatus(int $id, string $status, ?string $comments, ?int $approvedBy): bool
    {
        $stmt = $this->pdo->prepare("UPDATE documents SET status = ?, comments = ?, approved_by = ?, approved_on = CASE WHEN ? IS NOT NULL THEN NOW() ELSE NULL END WHERE id = ?");
        return $stmt->execute([$status, $comments, $approvedBy, $approvedBy, $id]);
    }
}
