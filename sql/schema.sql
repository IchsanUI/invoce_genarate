-- ============================================================
-- Invoice Generator — Database Schema
-- ============================================================
-- Database: invoice_system
-- Engine: InnoDB
-- Charset: utf8mb4
--
-- Run in MySQL/MariaDB:
--   mysql -u root -p < sql/schema.sql
-- ============================================================

-- 1. DATABASE -------------------------------------------------
CREATE DATABASE IF NOT EXISTS `invoice_system`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `invoice_system`;

-- -----------------------------------------------------------
-- 2. TABLES
-- -----------------------------------------------------------

-- 2a. users ---------------------------------------------------
CREATE TABLE `users` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`     VARCHAR(50)  NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name`    VARCHAR(100) NOT NULL,
    `role`         ENUM('admin','superadmin') NOT NULL DEFAULT 'admin',
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login`   DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2b. company_settings ---------------------------------------
CREATE TABLE `company_settings` (
    `id`                   INT UNSIGNED NOT NULL,
    `company_name`         VARCHAR(150)  NOT NULL,
    `address`              TEXT          NOT NULL,
    `phone`                VARCHAR(50)   NOT NULL,
    `email`                VARCHAR(100)  NOT NULL,
    `website`              VARCHAR(100)  NOT NULL DEFAULT '',
    `logo_path`            VARCHAR(255)  NULL,
    `bank_name`            VARCHAR(50)   NOT NULL DEFAULT '',
    `bank_account_number`  VARCHAR(50)   NOT NULL DEFAULT '',
    `bank_account_name`    VARCHAR(100)  NOT NULL DEFAULT '',
    `updated_at`           DATETIME      NULL DEFAULT NULL,
    `updated_by`           INT UNSIGNED  NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_company_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2c. invoices -----------------------------------------------
CREATE TABLE `invoices` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_number`   VARCHAR(60)  NOT NULL,
    `customer_name`    VARCHAR(150) NOT NULL,
    `customer_address` TEXT         NOT NULL,
    `customer_phone`   VARCHAR(50)  NOT NULL,
    `invoice_date`     DATE         NOT NULL,
    `total_qty`        INT UNSIGNED NOT NULL DEFAULT 0,
    `total_amount`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `grand_total`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `data_snapshot`    JSON         NULL,
    `signature`        VARCHAR(255) NOT NULL DEFAULT '',
    `status`           ENUM('active','void') NOT NULL DEFAULT 'active',
    `created_by`       INT UNSIGNED NOT NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invoices_number` (`invoice_number`),
    INDEX `idx_invoices_number` (`invoice_number`),
    INDEX `idx_invoices_date` (`invoice_date`),
    INDEX `idx_invoices_created_by` (`created_by`),
    CONSTRAINT `fk_invoices_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2d. invoice_items ------------------------------------------
CREATE TABLE `invoice_items` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id` INT UNSIGNED NOT NULL,
    `no_urut`    INT UNSIGNED NOT NULL,
    `barcode`    VARCHAR(50)  NOT NULL DEFAULT '',
    `kategori`   VARCHAR(100) NOT NULL DEFAULT '',
    `nama_barang` VARCHAR(150) NOT NULL,
    `qty`        INT UNSIGNED NOT NULL,
    `satuan`     VARCHAR(20)  NOT NULL DEFAULT '',
    `harga`      DECIMAL(15,2) NOT NULL,
    `total`      DECIMAL(15,2) NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_invoice_items_invoice` (`invoice_id`),
    CONSTRAINT `fk_invoice_items_invoice`
        FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2e. activity_logs ------------------------------------------
CREATE TABLE `activity_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NULL,
    `action`      VARCHAR(100) NOT NULL,
    `description` TEXT         NOT NULL,
    `ip_address`  VARCHAR(45)  NOT NULL DEFAULT '',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_activity_logs_action` (`action`),
    INDEX `idx_activity_logs_user` (`user_id`),
    INDEX `idx_activity_logs_time` (`created_at`),
    CONSTRAINT `fk_activity_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2f. verification_logs --------------------------------------
CREATE TABLE `verification_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id` INT UNSIGNED NULL,
    `scanned_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45)  NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
    `result`     ENUM('valid','invalid','not_found') NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_verification_logs_invoice` (`invoice_id`),
    INDEX `idx_verification_logs_time` (`scanned_at`),
    CONSTRAINT `fk_verification_logs_invoice`
        FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
