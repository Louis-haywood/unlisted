-- LouVentory Database Schema
-- Import this file via phpMyAdmin or MySQL CLI before first use.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table: tenants
-- --------------------------------------------------------
CREATE TABLE `tenants` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(255)    NOT NULL,
  `subdomain`     VARCHAR(100)    NOT NULL,
  `custom_domain` VARCHAR(255)    NULL DEFAULT NULL,
  `plan`          ENUM('free','pro') NOT NULL DEFAULT 'free',
  `item_limit`    INT             NOT NULL DEFAULT 100,
  `active`        TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subdomain` (`subdomain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `name`       VARCHAR(255) NOT NULL,
  `email`      VARCHAR(255) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  INDEX `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_users_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `name`       VARCHAR(255) NOT NULL,
  `colour`     VARCHAR(7)   NOT NULL DEFAULT '#378ADD',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_categories_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: items
-- --------------------------------------------------------
CREATE TABLE `items` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`           INT UNSIGNED  NOT NULL,
  `category_id`         INT UNSIGNED  NULL DEFAULT NULL,
  `name`                VARCHAR(255)  NOT NULL,
  `description`         TEXT          NULL,
  `quantity`            INT           NOT NULL DEFAULT 0,
  `low_stock_threshold` INT           NOT NULL DEFAULT 5,
  `serial_number`       VARCHAR(255)  NULL DEFAULT NULL,
  `barcode`             VARCHAR(255)  NULL DEFAULT NULL,
  `photo_path`          VARCHAR(500)  NULL DEFAULT NULL,
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_category_id` (`category_id`),
  CONSTRAINT `fk_items_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: borrowers
-- --------------------------------------------------------
CREATE TABLE `borrowers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `name`       VARCHAR(255) NOT NULL,
  `email`      VARCHAR(255) NOT NULL DEFAULT '',
  `phone`      VARCHAR(50)  NOT NULL DEFAULT '',
  `address`    TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_borrowers_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: loans
-- --------------------------------------------------------
CREATE TABLE `loans` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`       INT UNSIGNED NOT NULL,
  `item_id`         INT UNSIGNED NOT NULL,
  `borrower_id`     INT UNSIGNED NOT NULL,
  `quantity_loaned` INT          NOT NULL DEFAULT 1,
  `checked_out_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `due_date`        DATE         NULL DEFAULT NULL,
  `returned_at`     DATETIME     NULL DEFAULT NULL,
  `notes`           TEXT         NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_item_id` (`item_id`),
  INDEX `idx_borrower_id` (`borrower_id`),
  INDEX `idx_returned_at` (`returned_at`),
  CONSTRAINT `fk_loans_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loans_item`
    FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loans_borrower`
    FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: activity_log
-- --------------------------------------------------------
CREATE TABLE `activity_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NULL DEFAULT NULL,
  `action`      VARCHAR(100) NOT NULL,
  `description` TEXT         NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_activity_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
