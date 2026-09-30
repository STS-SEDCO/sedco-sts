-- SEDCO Smart Training System (STS)
-- Fresh database schema for local XAMPP / MySQL / MariaDB.
-- Import this file into a NEW empty database named "sts".

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone_number VARCHAR(30) DEFAULT NULL,
    role ENUM(
        'admin',
        'staff',
        'head_of_division',
        'training_section',
        'pengerusi_besar',
        'general_manager',
        'head_of_department'
    ) NOT NULL DEFAULT 'staff',
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_no VARCHAR(40) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    form_type ENUM('BPL', 'PKK', 'TEA') NOT NULL,
    title VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending', 'approved', 'correction', 'rejected') NOT NULL DEFAULT 'pending',
    current_stage ENUM('hod', 'training', 'gm', 'completed') NOT NULL DEFAULT 'hod',
    review_note TEXT DEFAULT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_applications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_applications_user (user_id),
    INDEX idx_applications_status (status),
    INDEX idx_applications_stage (current_stage),
    INDEX idx_applications_type (form_type),
    INDEX idx_applications_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS application_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    reviewer_id INT UNSIGNED NOT NULL,
    review_stage ENUM('hod', 'training', 'gm') NOT NULL,
    decision ENUM('approved', 'rejected', 'correction') NOT NULL,
    note TEXT DEFAULT NULL,
    reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_application
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user
        FOREIGN KEY (reviewer_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    INDEX idx_reviews_application (application_id),
    INDEX idx_reviews_reviewer (reviewer_id),
    INDEX idx_reviews_stage (review_stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the first account through signup.php so the password is stored
-- using PHP password_hash(). Change role in phpMyAdmin when creating
-- HoD / Training Department / General Manager / Admin test accounts.
