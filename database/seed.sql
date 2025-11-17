
-- ฐานข้อมูลระบบประเมินผลการปฏิบัติงาน มหาวิทยาลัยราชภัฏยะลา
-- YRU Performance Evaluation System Database Schema

CREATE DATABASE IF NOT EXISTS yru_evaluation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE yru_evaluation;

-- ตารางผู้ใช้งาน
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name_th VARCHAR(255) NOT NULL,
    full_name_en VARCHAR(255),
    personnel_type ENUM('academic', 'support', 'lecturer') NOT NULL,
    department_id INT,
    position VARCHAR(100),
    role ENUM('admin', 'staff', 'manager') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_personnel_type (personnel_type),
    INDEX idx_role (role),
    INDEX idx_department (department_id)
) ENGINE=InnoDB;

-- ตารางหน่วยงาน/ภาควิชา
CREATE TABLE departments (
    department_id INT PRIMARY KEY AUTO_INCREMENT,
    department_code VARCHAR(20) UNIQUE NOT NULL,
    department_name_th VARCHAR(255) NOT NULL,
    department_name_en VARCHAR(255),
    faculty_id INT,
    head_user_id INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_faculty (faculty_id)
) ENGINE=InnoDB;

-- ตารางด้านการประเมิน
CREATE TABLE evaluation_aspects (
    aspect_id INT PRIMARY KEY AUTO_INCREMENT,
    aspect_code VARCHAR(20) UNIQUE NOT NULL,
    aspect_name_th VARCHAR(255) NOT NULL,
    aspect_name_en VARCHAR(255),
    description TEXT,
    weight_percentage DECIMAL(5,2) DEFAULT 0,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ตารางหัวข้อประเมิน
CREATE TABLE evaluation_topics (
    topic_id INT PRIMARY KEY AUTO_INCREMENT,
    aspect_id INT NOT NULL,
    topic_code VARCHAR(50) NOT NULL,
    topic_name_th VARCHAR(500) NOT NULL,
    topic_name_en VARCHAR(500),
    max_score DECIMAL(5,2) DEFAULT 0,
    weight_percentage DECIMAL(5,2) DEFAULT 0,
    description TEXT,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aspect_id) REFERENCES evaluation_aspects(aspect_id) ON DELETE CASCADE,
    INDEX idx_aspect (aspect_id)
) ENGINE=InnoDB;

-- ตารางสิทธิ์การประเมินตามประเภทบุคลากร
CREATE TABLE personnel_evaluation_rights (
    right_id INT PRIMARY KEY AUTO_INCREMENT,
    personnel_type ENUM('academic', 'support', 'lecturer') NOT NULL,
    aspect_id INT NOT NULL,
    can_evaluate TINYINT(1) DEFAULT 1,
    is_required TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aspect_id) REFERENCES evaluation_aspects(aspect_id) ON DELETE CASCADE,
    UNIQUE KEY unique_personnel_aspect (personnel_type, aspect_id)
) ENGINE=InnoDB;

-- ตารางรอบการประเมิน
CREATE TABLE evaluation_periods (
    period_id INT PRIMARY KEY AUTO_INCREMENT,
    period_name VARCHAR(255) NOT NULL,
    year INT NOT NULL,
    semester TINYINT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    submission_deadline DATETIME NOT NULL,
    approval_deadline DATETIME,
    status ENUM('draft', 'active', 'closed') DEFAULT 'draft',
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_year_semester (year, semester),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ตารางแบบประเมิน
CREATE TABLE evaluations (
    evaluation_id INT PRIMARY KEY AUTO_INCREMENT,
    period_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'returned') DEFAULT 'draft',
    total_score DECIMAL(10,2) DEFAULT 0,
    submitted_at DATETIME,
    reviewed_at DATETIME,
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES evaluation_periods(period_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_period (period_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ตารางรายละเอียดการประเมินแต่ละด้าน
CREATE TABLE evaluation_details (
    detail_id INT PRIMARY KEY AUTO_INCREMENT,
    evaluation_id INT NOT NULL,
    aspect_id INT NOT NULL,
    topic_id INT,
    score DECIMAL(10,2) DEFAULT 0,
    self_assessment TEXT,
    evidence_description TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(evaluation_id) ON DELETE CASCADE,
    FOREIGN KEY (aspect_id) REFERENCES evaluation_aspects(aspect_id),
    FOREIGN KEY (topic_id) REFERENCES evaluation_topics(topic_id),
    INDEX idx_evaluation (evaluation_id)
) ENGINE=InnoDB;

-- ตารางคลังผลงาน
CREATE TABLE work_portfolio (
    portfolio_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    aspect_id INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    work_type VARCHAR(100),
    work_date DATE,
    max_usage_count INT DEFAULT 1,
    current_usage_count INT DEFAULT 0,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    file_size INT,
    is_shared TINYINT(1) DEFAULT 0,
    tags VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (aspect_id) REFERENCES evaluation_aspects(aspect_id),
    INDEX idx_user (user_id),
    INDEX idx_aspect (aspect_id)
) ENGINE=InnoDB;

-- ตารางการเชื่อมโยงผลงานกับแบบประเมิน
CREATE TABLE evaluation_portfolios (
    link_id INT PRIMARY KEY AUTO_INCREMENT,
    evaluation_id INT NOT NULL,
    detail_id INT,
    portfolio_id INT NOT NULL,
    is_claimed TINYINT(1) DEFAULT 0,
    claimed_at DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(evaluation_id) ON DELETE CASCADE,
    FOREIGN KEY (detail_id) REFERENCES evaluation_details(detail_id) ON DELETE CASCADE,
    FOREIGN KEY (portfolio_id) REFERENCES work_portfolio(portfolio_id) ON DELETE CASCADE,
    INDEX idx_evaluation (evaluation_id),
    INDEX idx_portfolio (portfolio_id)
) ENGINE=InnoDB;

-- ตารางผู้บริหารที่เลือกประเมิน
CREATE TABLE evaluation_managers (
    em_id INT PRIMARY KEY AUTO_INCREMENT,
    evaluation_id INT NOT NULL,
    manager_user_id INT NOT NULL,
    selection_order TINYINT DEFAULT 1,
    status ENUM('pending', 'reviewing', 'approved', 'rejected') DEFAULT 'pending',
    review_comment TEXT,
    review_score DECIMAL(5,2),
    reviewed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(evaluation_id) ON DELETE CASCADE,
    FOREIGN KEY (manager_user_id) REFERENCES users(user_id),
    INDEX idx_evaluation (evaluation_id),
    INDEX idx_manager (manager_user_id)
) ENGINE=InnoDB;

-- ตารางการอนุมัติ/ส่งกลับ
CREATE TABLE approval_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    evaluation_id INT NOT NULL,
    manager_user_id INT NOT NULL,
    action ENUM('submit', 'return', 'approve', 'reject') NOT NULL,
    comment TEXT,
    previous_status VARCHAR(50),
    new_status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(evaluation_id) ON DELETE CASCADE,
    FOREIGN KEY (manager_user_id) REFERENCES users(user_id),
    INDEX idx_evaluation (evaluation_id)
) ENGINE=InnoDB;

-- ตารางการแจ้งเตือน
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50),
    related_id INT,
    related_type VARCHAR(50),
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ตารางการตั้งค่าระบบ
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50),
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ตารางบันทึกการใช้งาน (Audit Trail)
CREATE TABLE activity_logs (
    log_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ตารางสำรองข้อมูล
CREATE TABLE backup_history (
    backup_id INT PRIMARY KEY AUTO_INCREMENT,
    backup_name VARCHAR(255) NOT NULL,
    backup_path VARCHAR(500),
    backup_size BIGINT,
    backup_type ENUM('manual', 'automatic') DEFAULT 'manual',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ข้อมูลเริ่มต้นด้านการประเมิน
INSERT INTO evaluation_aspects (aspect_code, aspect_name_th, aspect_name_en, weight_percentage, display_order) VALUES
('TEACHING', 'การสอน', 'Teaching', 40.00, 1),
('RESEARCH', 'การวิจัย', 'Research', 30.00, 2),
('SERVICE', 'การบริการวิชาการ', 'Academic Service', 15.00, 3),
('CULTURE', 'การทำนุบำรุงศิลปวัฒนธรรม', 'Cultural Preservation', 10.00, 4),
('MANAGEMENT', 'การบริหาร', 'Management', 5.00, 5);

-- ข้อมูลเริ่มต้นผู้ดูแลระบบ (password: admin123)
INSERT INTO users (username, password, email, full_name_th, full_name_en, personnel_type, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@yru.ac.th', 'ผู้ดูแลระบบ', 'System Administrator', 'academic', 'admin');

-- ข้อมูลเริ่มต้นการตั้งค่า
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('system_name', 'ระบบประเมินผลการปฏิบัติงาน มหาวิทยาลัยราชภัฏยะลา', 'text', 'ชื่อระบบ'),
('system_version', '1.0.0', 'text', 'เวอร์ชันระบบ'),
('backup_auto', '1', 'boolean', 'สำรองข้อมูลอัตโนมัติ'),
('notification_email', '1', 'boolean', 'ส่งการแจ้งเตือนทางอีเมล'),
('max_file_size', '10485760', 'number', 'ขนาดไฟล์สูงสุด (bytes)'),
('allowed_file_types', 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'text', 'ประเภทไฟล์ที่อนุญาต');