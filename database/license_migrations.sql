-- ==========================================================================
-- database/license_migrations.sql
-- ==========================================================================
-- Schema for GroCo Client Software Licensing & Installation State
-- Stores local cryptographic activation tokens, cached verification states,
-- domain bindings, and non-sensitive diagnostic audit logs.
-- ==========================================================================

-- 1. Client License Activation & Verification Cache
CREATE TABLE IF NOT EXISTS system_license (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key_hash VARCHAR(64) NOT NULL,
    license_mask VARCHAR(32) NOT NULL,
    installation_id VARCHAR(64) NOT NULL,
    license_type ENUM('development', 'production', 'trial') NOT NULL DEFAULT 'production',
    domain VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NULL,
    status ENUM('active', 'suspended', 'expired', 'revoked') NOT NULL DEFAULT 'active',
    activation_payload LONGTEXT NOT NULL,
    signature LONGTEXT NOT NULL,
    last_verified_at TIMESTAMP NULL DEFAULT NULL,
    next_check_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_license_status (status),
    INDEX idx_license_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Licensing Event Audit Trail
CREATE TABLE IF NOT EXISTS system_license_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    message VARCHAR(255) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_license_event (event_type),
    INDEX idx_license_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
