//v2 DB 

//Create Submissions Table (For Assignments & Reports)
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('Pending', 'Submitted', 'Overdue') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'head_of_division', 'training_section', 'pengerusi_besar', 'head_of_department') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

 //Create task Table (For Training Programs)
 
 CREATE TABLE task (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

//Example Submissions
INSERT INTO submissions (user_id, title, due_date, status) 
VALUES 
(1, 'Penilaian Keberkesanan Kursus', '2025-03-17', 'Overdue'),
(2, 'Training Effectiveness Assessment Form', '2025-03-20', 'Pending');

//Example task
INSERT INTO task (task_name, description) 
VALUES ('Penilaian Keberkesanan Kursus', 'Training Effectiveness Assessment Form');

use sts;
-- User Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'head_of_division', 'training_section', 'pengerusi_besar', 'head_of_department') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


INSERT INTO users (name, email, password, role) 
VALUES 
('wani', 'wani.gmail.com', 'hashed_password', 'staff'),
('Siti', 'siti@gmail.com', 'hashed_password', 'head_of_department');

ALTER TABLE users ADD COLUMN fullname VARCHAR(100) NOT NULL;
ALTER TABLE users ADD COLUMN phone_number VARCHAR(20) NOT NULL;
