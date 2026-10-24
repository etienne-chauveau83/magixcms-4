-- Migration : section Sécurité du back-office. À exécuter une fois sur une installation existante.

CREATE TABLE IF NOT EXISTS `mc_admin_audit_log` (
    `id_log` int UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_admin` smallint UNSIGNED DEFAULT NULL,
    `email_admin` varchar(255) DEFAULT NULL,
    `action_log` varchar(100) NOT NULL,
    `target_log` varchar(255) DEFAULT NULL,
    `details_log` mediumtext DEFAULT NULL,
    `ip_log` varchar(45) DEFAULT NULL,
    `date_register` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_log`),
    KEY `idx_audit_admin` (`id_admin`),
    KEY `idx_audit_date` (`date_register`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Compatibilité avec une préversion ayant déjà créé le journal (MySQL et MariaDB).
SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mc_admin_audit_log` ADD COLUMN `details_log` MEDIUMTEXT NULL AFTER `target_log`',
    'SELECT 1') INTO @mc_security_upgrade
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mc_admin_audit_log' AND COLUMN_NAME = 'details_log';
PREPARE mc_security_upgrade_stmt FROM @mc_security_upgrade;
EXECUTE mc_security_upgrade_stmt;
DEALLOCATE PREPARE mc_security_upgrade_stmt;

CREATE TABLE IF NOT EXISTS `mc_admin_login_attempt` (
    `id_attempt` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope` enum('ip','account') NOT NULL,
    `subject_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `subject_label` varchar(255) NOT NULL,
    `ip_attempt` varchar(45) NOT NULL,
    `email_attempt` varchar(255) NOT NULL,
    `date_attempt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id_attempt`),
    KEY `idx_login_subject` (`scope`, `subject_hash`, `date_attempt`),
    KEY `idx_login_expiry` (`date_attempt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Modules (mc_module.name n'a pas de contrainte UNIQUE → insérer seulement si absent)
INSERT INTO `mc_module` (`name`) SELECT 'auditlog'        WHERE NOT EXISTS (SELECT 1 FROM `mc_module` WHERE `name`='auditlog');
INSERT INTO `mc_module` (`name`) SELECT 'securitystatus'  WHERE NOT EXISTS (SELECT 1 FROM `mc_module` WHERE `name`='securitystatus');
INSERT INTO `mc_module` (`name`) SELECT 'loginprotection' WHERE NOT EXISTS (SELECT 1 FROM `mc_module` WHERE `name`='loginprotection');

-- Droits : rôle 1 (administrator) = accès complet sur les 3 nouveaux modules
INSERT INTO `mc_admin_access` (`id_role`, `id_module`, `view`, `append`, `edit`, `del`, `action`)
SELECT 1, m.`id_module`, 1, 1, 1, 1, 1
FROM `mc_module` m
WHERE m.`name` IN ('auditlog','securitystatus','loginprotection')
  AND NOT EXISTS (SELECT 1 FROM `mc_admin_access` a WHERE a.`id_role`=1 AND a.`id_module`=m.`id_module`);

-- Réglages (mc_setting.name est UNIQUE → INSERT IGNORE)
INSERT IGNORE INTO `mc_setting` (`name`, `value`, `type`, `label`, `category`) VALUES
('sec_audit_retention_days', '90', 'int', 'Audit log retention (days)', 'security'),
('sec_audit_max_rows', '50000', 'int', 'Audit log max rows', 'security'),
('sec_bruteforce_enabled', '1', 'int', 'Login brute-force protection enabled', 'security'),
('sec_bruteforce_max', '5', 'int', 'Login brute-force failure threshold', 'security'),
('sec_bruteforce_window', '15', 'int', 'Login brute-force window (minutes)', 'security'),
('sec_alert_bruteforce', '1', 'int', 'Alert on brute-force lockout', 'security'),
('sec_alert_superadmin', '1', 'int', 'Alert on administrator creation/role assignment', 'security'),
('sec_alert_email', NULL, 'string', 'Security alert recipient e-mail', 'security'),
('sec_dashboard_widget', '1', 'int', 'Show security tile on dashboard', 'security'),
('sec_superadmin_threshold', '3', 'int', 'Unrestricted account count warning threshold', 'security'),
('sec_trusted_proxies', NULL, 'string', 'Trusted proxy IPs/CIDRs for client IP resolution', 'security');
