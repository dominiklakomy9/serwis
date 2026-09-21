-- =====================================================================
--  Dominik Łakomy - Pogotowie Komputerowe
--  Schemat bazy danych (MySQL 8.0+ / MariaDB 10.5+)
--
--  Import:
--     mysql -u root -p < database/schema.sql
--  lub do istniejącej bazy:
--     mysql -u user -p nazwa_bazy < database/schema.sql
--
--  Konwencje:
--   - utf8mb4 (pełne wsparcie znaków, w tym emoji)
--   - InnoDB (transakcje + klucze obce)
--   - created_at / updated_at wszędzie tam, gdzie ma to sens
--   - klucze obce, indeksy i ograniczenia UNIQUE
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  admins — konta administratora (logowanie do panelu)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`          VARCHAR(190)    NOT NULL,
    `password_hash`  VARCHAR(255)    NOT NULL,
    `full_name`      VARCHAR(120)    NOT NULL DEFAULT '',
    `is_active`      TINYINT(1)      NOT NULL DEFAULT 1,
    `last_login_at`  DATETIME        NULL DEFAULT NULL,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  login_attempts — audyt i ochrona brute-force logowania
--  Przechowujemy próby per IP + email (email tylko do korelacji, nie hasło!)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address`   VARCHAR(45)     NOT NULL,
    `email`        VARCHAR(190)    NULL DEFAULT NULL,
    `success`      TINYINT(1)      NOT NULL DEFAULT 0,
    `attempted_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_ip_time` (`ip_address`, `attempted_at`),
    KEY `idx_login_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  rate_limit_hits — generyczny rate limiting (np. formularz rezerwacji)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limit_hits` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45)     NOT NULL,
    `action`     VARCHAR(60)     NOT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rl_ip_action_time` (`ip_address`, `action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  customers — klienci (dane z formularza rezerwacji)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name`  VARCHAR(120)    NOT NULL,
    `phone`      VARCHAR(30)     NOT NULL,
    `email`      VARCHAR(190)    NULL DEFAULT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customers_phone` (`phone`),
    KEY `idx_customers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  availability — bloki dostępności ustawiane przez administratora
--  Pojedynczy wiersz = jeden przedział czasu w danym dniu + interwał.
--  Sloty są GENEROWANE dynamicznie w aplikacji na podstawie tych bloków.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `availability` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slot_date`        DATE            NOT NULL,
    `start_time`       TIME            NOT NULL,
    `end_time`         TIME            NOT NULL,
    `interval_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `created_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_availability_date` (`slot_date`),
    CONSTRAINT `fk_availability_admin`
        FOREIGN KEY (`created_by`) REFERENCES `admins`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  blocked_slots — pojedyncze zablokowane terminy (nagłe obowiązki)
--  Slot zablokowany nie zostanie pokazany klientowi jako wolny.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blocked_slots` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slot_date`  DATE            NOT NULL,
    `slot_time`  TIME            NOT NULL,
    `reason`     VARCHAR(190)    NULL DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_blocked_slot` (`slot_date`, `slot_time`),
    CONSTRAINT `fk_blocked_admin`
        FOREIGN KEY (`created_by`) REFERENCES `admins`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  appointments — zgłoszenia / rezerwacje terminu dostarczenia sprzętu
--
--  Ochrona przed podwójną rezerwacją realizowana na dwóch poziomach:
--   1) kolumna generowana `active_slot_key` + UNIQUE — twarda gwarancja,
--      że dla danego (data, godzina) istnieje najwyżej JEDNA aktywna
--      rezerwacja. Anulowane / no_show mają klucz NULL (nie blokują slotu).
--   2) transakcja + SELECT ... FOR UPDATE w warstwie aplikacji.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appointments` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `appointment_number`   VARCHAR(20)     NOT NULL,
    `customer_id`          BIGINT UNSIGNED NOT NULL,
    `slot_date`            DATE            NOT NULL,
    `slot_time`            TIME            NOT NULL,
    `device_type`          ENUM('laptop','desktop','printer','monitor','other') NOT NULL,
    `device_manufacturer`  VARCHAR(100)    NULL DEFAULT NULL,
    `device_model`         VARCHAR(100)    NULL DEFAULT NULL,
    `problem_description`  TEXT            NOT NULL,
    `status`               ENUM('pending','confirmed','in_progress','waiting_for_customer','completed','cancelled','no_show')
                                           NOT NULL DEFAULT 'pending',
    `status_token`         CHAR(64)        NOT NULL,
    `privacy_accepted`     TINYINT(1)      NOT NULL DEFAULT 0,
    `source_ip`            VARCHAR(45)     NULL DEFAULT NULL,
    `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Klucz aktywnego slotu: NULL dla rezerwacji nieaktywnych (anulowane/nieobecność)
    `active_slot_key`      VARCHAR(30)
        GENERATED ALWAYS AS (
            CASE WHEN `status` IN ('cancelled','no_show')
                 THEN NULL
                 ELSE CONCAT(`slot_date`, ' ', `slot_time`)
            END
        ) STORED,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_appointment_number` (`appointment_number`),
    UNIQUE KEY `uq_active_slot` (`active_slot_key`),
    KEY `idx_appt_date` (`slot_date`),
    KEY `idx_appt_status` (`status`),
    KEY `idx_appt_customer` (`customer_id`),
    KEY `idx_appt_token` (`status_token`),
    CONSTRAINT `fk_appt_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  appointment_status_history — historia zmian statusu zgłoszenia
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appointment_status_history` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `appointment_id` BIGINT UNSIGNED NOT NULL,
    `old_status`     VARCHAR(30)     NULL DEFAULT NULL,
    `new_status`     VARCHAR(30)     NOT NULL,
    `note`           VARCHAR(255)    NULL DEFAULT NULL,
    `changed_by`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ash_appt` (`appointment_id`),
    CONSTRAINT `fk_ash_appt`
        FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ash_admin`
        FOREIGN KEY (`changed_by`) REFERENCES `admins`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  devices — sprzęt (warstwa protokołu przyjęcia; gotowe na przyszłość)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devices` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `type`          ENUM('laptop','desktop','printer','monitor','other') NOT NULL DEFAULT 'other',
    `manufacturer`  VARCHAR(100)    NULL DEFAULT NULL,
    `model`         VARCHAR(100)    NULL DEFAULT NULL,
    `serial_number` VARCHAR(120)    NULL DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_devices_customer` (`customer_id`),
    KEY `idx_devices_serial` (`serial_number`),
    CONSTRAINT `fk_devices_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  service_orders — protokół przyjęcia sprzętu (warstwa przyszła)
--  Powstaje z rezerwacji w chwili faktycznego przyjęcia sprzętu.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_orders` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number`      VARCHAR(20)     NOT NULL,
    `appointment_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `customer_id`       BIGINT UNSIGNED NOT NULL,
    `device_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `visual_condition`  TEXT            NULL DEFAULT NULL,
    `accessories`       TEXT            NULL DEFAULT NULL,
    `fault_description` TEXT            NULL DEFAULT NULL,
    `technician_notes`  TEXT            NULL DEFAULT NULL,
    `photos_json`       JSON            NULL DEFAULT NULL,
    `received_at`       DATETIME        NULL DEFAULT NULL,
    `released_at`       DATETIME        NULL DEFAULT NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_order_number` (`order_number`),
    KEY `idx_so_customer` (`customer_id`),
    KEY `idx_so_appt` (`appointment_id`),
    CONSTRAINT `fk_so_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_so_appt`
        FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_so_device`
        FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  service_order_status_history — historia zmian statusu protokołu
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_order_status_history` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service_order_id` BIGINT UNSIGNED NOT NULL,
    `old_status`       VARCHAR(30)     NULL DEFAULT NULL,
    `new_status`       VARCHAR(30)     NOT NULL,
    `note`             VARCHAR(255)    NULL DEFAULT NULL,
    `changed_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sosh_order` (`service_order_id`),
    CONSTRAINT `fk_sosh_order`
        FOREIGN KEY (`service_order_id`) REFERENCES `service_orders`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sosh_admin`
        FOREIGN KEY (`changed_by`) REFERENCES `admins`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  KONTO ADMINISTRATORA
--  Hasło jest zahaszowane bcrypt (cost=12). Aby zmienić hasło, użyj:
--      php bin/create_admin.php <email> <nowe_haslo>
--  albo wygeneruj nowy hash i podmień poniższy INSERT.
-- =====================================================================
INSERT INTO `admins` (`email`, `password_hash`, `full_name`, `is_active`)
VALUES (
    'dominiklakomy9@gmail.com',
    '$2y$12$axk4ENEYDobncTyRUUohWeclRxmI5I53rTtLeFDLkraVtR7iWIj5i',
    'Dominik Łakomy',
    1
)
ON DUPLICATE KEY UPDATE `email` = `email`;
