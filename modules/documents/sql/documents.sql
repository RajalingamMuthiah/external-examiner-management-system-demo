-- EEMS Documents Module Schema
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by_teacher_id INT NOT NULL,
    uploaded_for_teacher_id INT DEFAULT NULL,
    uploaded_for_college_id INT DEFAULT NULL,
    exam_id INT NOT NULL,
    doc_type VARCHAR(100),
    doc_title VARCHAR(255),
    file_name VARCHAR(255),
    file_path VARCHAR(255),
    academic_year VARCHAR(20) NOT NULL,
    uploaded_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) DEFAULT 'sent',
    comments TEXT,
    approved_by INT,
    approved_on DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
