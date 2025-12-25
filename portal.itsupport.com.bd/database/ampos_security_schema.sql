-- AMPOS Security Enhancement Schema
-- Add new columns and tables to support license security features

-- Add checksum tracking to licenses table
ALTER TABLE `licenses` 
ADD COLUMN `code_checksum` VARCHAR(64) NULL DEFAULT NULL COMMENT 'SHA256 checksum of AMPOS code files' AFTER `license_key`,
ADD COLUMN `last_check_in` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last successful license verification' AFTER `updated_at`,
ADD COLUMN `check_in_count` INT UNSIGNED DEFAULT 0 COMMENT 'Total number of check-ins' AFTER `last_check_in`,
ADD COLUMN `suspended_reason` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Reason for suspension if status is suspended' AFTER `status`;

-- Create table for tracking license verification logs
CREATE TABLE IF NOT EXISTS `license_verification_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_id` INT UNSIGNED NOT NULL,
  `device_id` VARCHAR(64) NULL,
  `ip_address` VARCHAR(45) NULL,
  `checksum` VARCHAR(64) NULL COMMENT 'Code checksum sent by client',
  `version` VARCHAR(20) NULL COMMENT 'AMPOS version',
  `status` ENUM('success', 'failed', 'tampering_detected', 'expired', 'suspended') NOT NULL DEFAULT 'success',
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_license_id` (`license_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_verification_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of all license verification attempts';

-- Create table for tracking licensed devices
CREATE TABLE IF NOT EXISTS `license_devices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_id` INT UNSIGNED NOT NULL,
  `device_id` VARCHAR(64) NOT NULL COMMENT 'Unique device identifier hash',
  `hostname` VARCHAR(255) NULL,
  `ip_address` VARCHAR(45) NULL,
  `first_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_license_device` (`license_id`, `device_id`),
  KEY `idx_license_id` (`license_id`),
  KEY `idx_device_id` (`device_id`),
  CONSTRAINT `fk_device_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track devices using each license';

-- Create table for security incidents
CREATE TABLE IF NOT EXISTS `license_security_incidents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_id` INT UNSIGNED NOT NULL,
  `incident_type` ENUM('tampering', 'nullification', 'checksum_mismatch', 'device_limit_exceeded', 'expired_usage', 'other') NOT NULL,
  `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
  `description` TEXT NOT NULL,
  `device_id` VARCHAR(64) NULL,
  `ip_address` VARCHAR(45) NULL,
  `checksum_sent` VARCHAR(64) NULL,
  `checksum_expected` VARCHAR(64) NULL,
  `auto_action_taken` VARCHAR(255) NULL COMMENT 'Automatic action taken (e.g., license suspended)',
  `admin_notified` TINYINT(1) DEFAULT 0,
  `resolved` TINYINT(1) DEFAULT 0,
  `resolved_at` TIMESTAMP NULL,
  `resolved_by` INT UNSIGNED NULL COMMENT 'Admin user ID who resolved',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_license_id` (`license_id`),
  KEY `idx_incident_type` (`incident_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_incident_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track security incidents related to licenses';

-- Create table for license deactivation history
CREATE TABLE IF NOT EXISTS `license_deactivation_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_id` INT UNSIGNED NOT NULL,
  `deactivated_by` INT UNSIGNED NULL COMMENT 'Customer or admin user ID',
  `deactivated_by_type` ENUM('customer', 'admin', 'system') NOT NULL DEFAULT 'system',
  `reason` VARCHAR(255) NULL,
  `previous_status` VARCHAR(20) NOT NULL,
  `new_status` VARCHAR(20) NOT NULL,
  `can_reactivate` TINYINT(1) DEFAULT 1 COMMENT 'Whether license can be reactivated',
  `deactivated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_license_id` (`license_id`),
  KEY `idx_deactivated_at` (`deactivated_at`),
  CONSTRAINT `fk_deactivation_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track license deactivation history';

-- Add indexes for performance
ALTER TABLE `licenses` 
ADD INDEX `idx_last_check_in` (`last_check_in`),
ADD INDEX `idx_code_checksum` (`code_checksum`),
ADD INDEX `idx_status_expires` (`status`, `expires_at`);

-- Create view for license security dashboard
CREATE OR REPLACE VIEW `v_license_security_status` AS
SELECT 
    l.id,
    l.license_key,
    l.status,
    l.expires_at,
    l.last_check_in,
    l.check_in_count,
    l.code_checksum,
    l.current_devices,
    l.max_devices,
    c.email as customer_email,
    c.first_name,
    c.last_name,
    p.name as product_name,
    DATEDIFF(NOW(), l.last_check_in) as days_since_checkin,
    (SELECT COUNT(*) FROM license_devices ld WHERE ld.license_id = l.id AND ld.status = 'active') as active_devices,
    (SELECT COUNT(*) FROM license_verification_logs lvl WHERE lvl.license_id = l.id AND lvl.status = 'failed' AND lvl.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as failed_verifications_24h,
    (SELECT COUNT(*) FROM license_security_incidents lsi WHERE lsi.license_id = l.id AND lsi.resolved = 0) as unresolved_incidents,
    CASE 
        WHEN l.status = 'suspended' THEN 'critical'
        WHEN DATEDIFF(NOW(), l.last_check_in) > 7 THEN 'critical'
        WHEN DATEDIFF(NOW(), l.last_check_in) > 5 THEN 'high'
        WHEN l.expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 'medium'
        WHEN l.current_devices >= l.max_devices THEN 'medium'
        ELSE 'low'
    END as risk_level
FROM licenses l
JOIN customers c ON l.customer_id = c.id
JOIN products p ON l.product_id = p.id
WHERE p.category = 'AMPOS';

-- Create stored procedure for automatic license suspension on tampering
DELIMITER $$

CREATE PROCEDURE `sp_suspend_license_for_tampering`(
    IN p_license_id INT UNSIGNED,
    IN p_reason VARCHAR(255),
    IN p_device_id VARCHAR(64),
    IN p_ip_address VARCHAR(45),
    IN p_checksum_sent VARCHAR(64),
    IN p_checksum_expected VARCHAR(64)
)
BEGIN
    DECLARE v_previous_status VARCHAR(20);
    
    -- Start transaction
    START TRANSACTION;
    
    -- Get current status
    SELECT status INTO v_previous_status FROM licenses WHERE id = p_license_id;
    
    -- Update license status
    UPDATE licenses 
    SET status = 'suspended',
        suspended_reason = p_reason,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_license_id;
    
    -- Log security incident
    INSERT INTO license_security_incidents (
        license_id,
        incident_type,
        severity,
        description,
        device_id,
        ip_address,
        checksum_sent,
        checksum_expected,
        auto_action_taken
    ) VALUES (
        p_license_id,
        'tampering',
        'critical',
        p_reason,
        p_device_id,
        p_ip_address,
        p_checksum_sent,
        p_checksum_expected,
        'License automatically suspended'
    );
    
    -- Log deactivation
    INSERT INTO license_deactivation_history (
        license_id,
        deactivated_by_type,
        reason,
        previous_status,
        new_status,
        can_reactivate
    ) VALUES (
        p_license_id,
        'system',
        p_reason,
        v_previous_status,
        'suspended',
        0  -- Cannot reactivate automatically after tampering
    );
    
    COMMIT;
END$$

DELIMITER ;

-- Create function to check if license needs verification
DELIMITER $$

CREATE FUNCTION `fn_license_needs_verification`(
    p_license_id INT UNSIGNED
) RETURNS TINYINT(1)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_last_check_in TIMESTAMP;
    DECLARE v_hours_since_checkin INT;
    
    SELECT last_check_in INTO v_last_check_in
    FROM licenses
    WHERE id = p_license_id;
    
    IF v_last_check_in IS NULL THEN
        RETURN 1; -- Needs verification
    END IF;
    
    SET v_hours_since_checkin = TIMESTAMPDIFF(HOUR, v_last_check_in, NOW());
    
    -- Require verification every 24 hours
    IF v_hours_since_checkin >= 24 THEN
        RETURN 1;
    ELSE
        RETURN 0;
    END IF;
END$$

DELIMITER ;

-- Insert sample security settings
INSERT IGNORE INTO `portal_settings` (`setting_key`, `setting_value`, `description`) VALUES
('ampos_checksum_validation', '1', 'Enable code checksum validation for anti-tampering'),
('ampos_max_grace_period_days', '7', 'Maximum days without portal connection before deactivation'),
('ampos_auto_suspend_on_tampering', '1', 'Automatically suspend license when tampering detected'),
('ampos_verification_interval_hours', '24', 'Hours between required license verifications'),
('ampos_max_failed_verifications', '10', 'Maximum failed verifications before alert'),
('ampos_notify_admin_on_tampering', '1', 'Send email to admin when tampering detected');

-- Create trigger to update check_in_count
DELIMITER $$

CREATE TRIGGER `tr_update_checkin_count` 
BEFORE UPDATE ON `licenses`
FOR EACH ROW
BEGIN
    IF NEW.last_check_in != OLD.last_check_in OR OLD.last_check_in IS NULL THEN
        SET NEW.check_in_count = OLD.check_in_count + 1;
    END IF;
END$$

DELIMITER ;

-- Sample data for testing (optional)
-- Uncomment to add test security incidents
/*
INSERT INTO `license_security_incidents` (
    license_id,
    incident_type,
    severity,
    description,
    device_id,
    ip_address
) VALUES (
    1,
    'tampering',
    'critical',
    'Checksum mismatch detected - possible code modification',
    'test-device-001',
    '192.168.1.100'
);
*/
