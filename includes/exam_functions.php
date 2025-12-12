<?php
// includes/exam_functions.php
// Secure, modular exam CRUD functions for EEMS

function create_exam($pdo, $data) {
    $sql = "INSERT INTO exams (subject_name, college_name, course, exam_date, start_time, end_time, max_marks, pass_marks, type, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['subject_name'], $data['college_name'], $data['course'], $data['exam_date'],
        $data['start_time'], $data['end_time'], $data['max_marks'], $data['pass_marks'],
        $data['type'], $data['status']
    ]);
}

function update_exam($pdo, $id, $data) {
    $sql = "UPDATE exams SET subject_name=?, college_name=?, course=?, exam_date=?, start_time=?, end_time=?, max_marks=?, pass_marks=?, type=?, status=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['subject_name'], $data['college_name'], $data['course'], $data['exam_date'],
        $data['start_time'], $data['end_time'], $data['max_marks'], $data['pass_marks'],
        $data['type'], $data['status'], $id
    ]);
}

function delete_exam($pdo, $id) {
    $sql = "DELETE FROM exams WHERE id=?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$id]);
}

function approve_exam($pdo, $id) {
    $sql = "UPDATE exams SET status='approved' WHERE id=?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$id]);
}

function get_exams($pdo, $filters = []) {
    $sql = "SELECT * FROM exams WHERE 1=1";
    $params = [];
    if (!empty($filters['search'])) {
        $sql .= " AND (subject_name LIKE ? OR college_name LIKE ? OR course LIKE ?)";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
    }
    $sql .= " ORDER BY exam_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
