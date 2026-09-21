-- =====================================================================
--  Migracja: Panel serwisowy — protokół przyjęcia + kroki naprawy
--  Uruchom na ISTNIEJĄCEJ bazie (nowe instalacje mają to już w schema.sql):
--     mysql -u user -p nazwa_bazy < database/migrations/2026_06_service_panel.sql
-- =====================================================================

ALTER TABLE `appointments`
    ADD COLUMN `device_serial`    VARCHAR(120) NULL DEFAULT NULL AFTER `problem_description`,
    ADD COLUMN `visual_condition` TEXT         NULL DEFAULT NULL AFTER `device_serial`,
    ADD COLUMN `accessories`      TEXT         NULL DEFAULT NULL AFTER `visual_condition`,
    ADD COLUMN `technician_notes` TEXT         NULL DEFAULT NULL AFTER `accessories`,
    ADD COLUMN `received_at`      DATETIME     NULL DEFAULT NULL AFTER `technician_notes`,
    ADD COLUMN `released_at`      DATETIME     NULL DEFAULT NULL AFTER `received_at`;

CREATE TABLE IF NOT EXISTS `repair_steps` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `appointment_id` BIGINT UNSIGNED NOT NULL,
    `step_text`      VARCHAR(1000)   NOT NULL,
    `is_public`      TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rs_appt` (`appointment_id`),
    CONSTRAINT `fk_rs_appt`  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rs_admin` FOREIGN KEY (`created_by`)     REFERENCES `admins`(`id`)       ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
