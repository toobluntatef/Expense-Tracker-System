-- Spendly database schema + seed data
CREATE DATABASE IF NOT EXISTS spendly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE spendly;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS goals, budgets, transactions, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  user_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login    DATETIME NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  name        VARCHAR(60) NOT NULL,
  type        ENUM('expense','income') NOT NULL DEFAULT 'expense',
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE transactions (
  txn_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  category_id    INT UNSIGNED NULL,
  type           ENUM('expense','income') NOT NULL,
  amount         DECIMAL(12,2) NOT NULL,
  txn_date       DATE NOT NULL,
  note           VARCHAR(255) NULL,
  payment_method VARCHAR(30) NOT NULL DEFAULT 'Cash',
  is_recurring   TINYINT(1) NOT NULL DEFAULT 0,
  parent_id      INT UNSIGNED NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_date (user_id, txn_date),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE budgets (
  budget_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  month        CHAR(7) NOT NULL,
  limit_amount DECIMAL(12,2) NOT NULL,
  UNIQUE KEY uq_budget (user_id, category_id, month),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE goals (
  goal_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  title         VARCHAR(100) NOT NULL,
  target_amount DECIMAL(12,2) NOT NULL,
  saved_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
  target_date   DATE NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO categories (user_id, name, type) VALUES
 (NULL,'Food','expense'),(NULL,'Transport','expense'),(NULL,'Education','expense'),
 (NULL,'Bills','expense'),(NULL,'Shopping','expense'),(NULL,'Health','expense'),
 (NULL,'Entertainment','expense'),(NULL,'Other','expense'),
 (NULL,'Allowance','income'),(NULL,'Salary','income'),(NULL,'Part-time','income'),
 (NULL,'Tuition Income','income'),(NULL,'Other Income','income');

-- The admin account is created by setup.php so the password hash is generated
-- by PHP's password_hash() on your own machine.
