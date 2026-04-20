CREATE DATABASE IF NOT EXISTS loan_tracking_db;
USE loan_tracking_db;

-- Table for main loan records
CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    borrower_name VARCHAR(255) NOT NULL,
    principal DECIMAL(15, 2) NOT NULL,
    loan_type VARCHAR(50) NOT NULL, -- float, flat, effective
    duration INT NOT NULL,
    duration_unit VARCHAR(20) NOT NULL, -- day, month, year
    interest_rate DECIMAL(10, 4),
    pmt_amount DECIMAL(15, 2) NOT NULL,
    total_payment DECIMAL(15, 2) NOT NULL,
    start_date DATE NOT NULL,
    payment_time TIME,
    status VARCHAR(20) DEFAULT 'active', -- active, closed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for daily/period payments tracking
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    period_num INT NOT NULL,
    due_date DATE,
    amount DECIMAL(15, 2) NOT NULL,
    status TINYINT DEFAULT 0, -- 0: Unpaid (📍), 1: Paid (✅)
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    INDEX (loan_id)
);

-- Table for general income and expenses
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('income', 'expense') NOT NULL, -- income, expense
    category VARCHAR(100), -- Commission, Interest, Principal Return, Cost, etc.
    amount DECIMAL(15, 2) NOT NULL,
    description TEXT,
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
