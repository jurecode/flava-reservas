-- =====================================================================
--  FLAVA STUDIO — https://flava.cl
--  Ruta: /database/flava.sql
--  Esquema inicial completo (instalación NUEVA).
--
--  ⚠ Este archivo es SOLO para instalaciones nuevas.
--    Para actualizar un sistema en producción usar /database/migrations
--    (ver MigrationService y el panel SUPER_ADMIN). NUNCA reimportar
--    este archivo sobre una base con datos reales.
--
--  Motor: InnoDB · Charset: utf8mb4 · Collation: utf8mb4_unicode_ci
--  Compatible con MySQL 5.7+/8.x y MariaDB 10.3+
--
--  INSTALACIÓN (phpMyAdmin):
--    1. Crear la base de datos `flava_db` con cotejamiento utf8mb4_unicode_ci
--    2. Seleccionarla e importar este archivo
--    3. Configurar /config/database.php o /.env
--    4. Ingresar a /login  (credenciales por defecto al final del archivo)
-- =====================================================================

-- Descomentar SOLO si el usuario MySQL tiene permisos para crear bases:
-- CREATE DATABASE IF NOT EXISTS `flava_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `flava_db`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';


-- =====================================================================
-- 1. SUCURSALES  (multisucursal preparado desde el día uno — spec §48)
-- =====================================================================
DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(120)  NOT NULL,
    `slug`          VARCHAR(140)  NOT NULL,
    `address`       VARCHAR(255)  NULL,
    `commune`       VARCHAR(120)  NULL,
    `city`          VARCHAR(120)  NULL,
    `phone`         VARCHAR(30)   NULL,
    `whatsapp`      VARCHAR(30)   NULL,
    `email`         VARCHAR(150)  NULL,
    `maps_url`      VARCHAR(500)  NULL,
    `latitude`      DECIMAL(10,7) NULL,
    `longitude`     DECIMAL(10,7) NULL,
    `timezone`      VARCHAR(64)   NOT NULL DEFAULT 'America/Santiago',
    `is_default`    TINYINT(1)    NOT NULL DEFAULT 0,
    `status`        TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_branches_slug` (`slug`),
    KEY `ix_branches_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 2. USUARIOS INTERNOS
--    El rol se resuelve con una columna ENUM: simple, indexable y robusto.
--    Los CLIENTES NO son usuarios (reservan como invitados — spec §10).
-- =====================================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `branch_id`             INT UNSIGNED NULL,
    `first_name`            VARCHAR(80)  NOT NULL,
    `last_name`             VARCHAR(80)  NOT NULL,
    `email`                 VARCHAR(150) NOT NULL,
    `phone`                 VARCHAR(30)  NULL,
    `password`              VARCHAR(255) NOT NULL,
    `role`                  ENUM('SUPER_ADMIN','ADMIN','RECEPTION','BARBER') NOT NULL DEFAULT 'RECEPTION',
    `avatar`                VARCHAR(255) NULL,
    `status`                TINYINT(1)   NOT NULL DEFAULT 1,
    `must_change_password`  TINYINT(1)   NOT NULL DEFAULT 0,
    `reset_token`           CHAR(64)     NULL,
    `reset_expires_at`      DATETIME     NULL,
    `last_login_at`         DATETIME     NULL,
    `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `ix_users_role_status` (`role`, `status`),
    KEY `ix_users_reset_token` (`reset_token`),
    KEY `fk_users_branch` (`branch_id`),
    CONSTRAINT `fk_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 3. CLIENTES (CRM automático, sin registro — spec §12 y §52)
-- =====================================================================
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `branch_id`           INT UNSIGNED NULL,
    `first_name`          VARCHAR(80)  NOT NULL,
    `last_name`           VARCHAR(80)  NOT NULL,
    `rut`                 VARCHAR(15)  NULL COMMENT 'Formato presentación 12.345.678-K',
    `rut_normalized`      VARCHAR(12)  NULL COMMENT 'Formato canónico 12345678-K (búsquedas y unicidad)',
    `email`               VARCHAR(150) NULL,
    `phone`               VARCHAR(20)  NULL COMMENT 'E.164 +56912345678',
    `whatsapp_phone`      VARCHAR(20)  NULL,
    `birthday`            DATE         NULL,
    `notes`               TEXT         NULL COMMENT 'Notas administrativas generales',
    `preferred_barber_id` INT UNSIGNED NULL,
    `total_bookings`      INT UNSIGNED NOT NULL DEFAULT 0,
    `completed_bookings`  INT UNSIGNED NOT NULL DEFAULT 0,
    `cancelled_bookings`  INT UNSIGNED NOT NULL DEFAULT 0,
    `no_show_count`       INT UNSIGNED NOT NULL DEFAULT 0,
    `total_spent`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `loyalty_points`      INT NOT NULL DEFAULT 0,
    `first_visit_at`      DATETIME     NULL,
    `last_visit_at`       DATETIME     NULL,
    `accepts_marketing`   TINYINT(1)   NOT NULL DEFAULT 1,
    `status`              TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customers_rut` (`rut_normalized`),
    KEY `ix_customers_email` (`email`),
    KEY `ix_customers_phone` (`phone`),
    KEY `ix_customers_name` (`last_name`, `first_name`),
    KEY `ix_customers_last_visit` (`last_visit_at`),
    KEY `fk_customers_branch` (`branch_id`),
    CONSTRAINT `fk_customers_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 4. CATEGORÍAS Y SERVICIOS
-- =====================================================================
DROP TABLE IF EXISTS `service_categories`;
CREATE TABLE `service_categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `slug`        VARCHAR(120) NOT NULL,
    `description` VARCHAR(255) NULL,
    `icon`        VARCHAR(60)  NULL,
    `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_service_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id`      INT UNSIGNED NULL,
    `name`             VARCHAR(120) NOT NULL,
    `slug`             VARCHAR(140) NOT NULL,
    `description`      TEXT         NULL,
    `price`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `buffer_minutes`   SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Colchón posterior específico del servicio',
    `image`            VARCHAR(255) NULL,
    `color`            VARCHAR(9)   NULL DEFAULT '#FFC400',
    `sort_order`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_featured`      TINYINT(1)   NOT NULL DEFAULT 0,
    `online_bookable`  TINYINT(1)   NOT NULL DEFAULT 1,
    `status`           TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_services_slug` (`slug`),
    KEY `ix_services_status` (`status`, `online_bookable`),
    KEY `fk_services_category` (`category_id`),
    CONSTRAINT `fk_services_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 5. BARBEROS
-- =====================================================================
DROP TABLE IF EXISTS `barbers`;
CREATE TABLE `barbers` (
    `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`                INT UNSIGNED NULL COMMENT 'Cuenta de acceso al panel del barbero',
    `branch_id`              INT UNSIGNED NOT NULL,
    `first_name`             VARCHAR(80)  NOT NULL,
    `last_name`              VARCHAR(80)  NULL,
    `display_name`           VARCHAR(80)  NOT NULL COMMENT 'Nombre visible en el booking',
    `slug`                   VARCHAR(120) NOT NULL,
    `email`                  VARCHAR(150) NULL,
    `phone`                  VARCHAR(20)  NULL,
    `photo`                  VARCHAR(255) NULL,
    `bio`                    TEXT         NULL,
    `specialty`              VARCHAR(160) NULL,
    `instagram`              VARCHAR(120) NULL,
    `color`                  VARCHAR(9)   NOT NULL DEFAULT '#FFC400' COMMENT 'Color en el calendario',
    `accepts_online`         TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status`                 TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_barbers_user` (`user_id`),
    UNIQUE KEY `uq_barbers_slug` (`slug`),
    KEY `ix_barbers_branch_status` (`branch_id`, `status`),
    CONSTRAINT `fk_barbers_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`)    ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_barbers_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Servicios que realiza cada barbero (con precio/duración opcionalmente propios)
DROP TABLE IF EXISTS `barber_services`;
CREATE TABLE `barber_services` (
    `barber_id`        INT UNSIGNED NOT NULL,
    `service_id`       INT UNSIGNED NOT NULL,
    `custom_price`     DECIMAL(10,2) NULL,
    `custom_duration`  SMALLINT UNSIGNED NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`barber_id`, `service_id`),
    KEY `ix_barber_services_service` (`service_id`),
    CONSTRAINT `fk_bs_barber`  FOREIGN KEY (`barber_id`)  REFERENCES `barbers` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bs_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 6. HORARIOS SEMANALES (varios bloques por día — spec §21)
-- =====================================================================
DROP TABLE IF EXISTS `barber_schedules`;
CREATE TABLE `barber_schedules` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `barber_id`  INT UNSIGNED NOT NULL,
    `weekday`    TINYINT UNSIGNED NOT NULL COMMENT 'ISO-8601: 1=lunes ... 7=domingo',
    `start_time` TIME NOT NULL,
    `end_time`   TIME NOT NULL,
    `valid_from` DATE NULL COMMENT 'Vigencia opcional del bloque',
    `valid_to`   DATE NULL,
    `status`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_schedules_barber_day` (`barber_id`, `weekday`, `status`),
    CONSTRAINT `fk_schedules_barber` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 7. BLOQUEOS (almuerzo, vacaciones, permisos, cierre de local — spec §22)
--    barber_id NULL = bloqueo de toda la sucursal.
-- =====================================================================
DROP TABLE IF EXISTS `blocked_times`;
CREATE TABLE `blocked_times` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `branch_id`      INT UNSIGNED NOT NULL,
    `barber_id`      INT UNSIGNED NULL COMMENT 'NULL = aplica a todos los barberos de la sucursal',
    `start_datetime` DATETIME NOT NULL,
    `end_datetime`   DATETIME NOT NULL,
    `type`           ENUM('lunch','vacation','permission','training','day_off','holiday','manual') NOT NULL DEFAULT 'manual',
    `reason`         VARCHAR(255) NULL,
    `is_recurring`   TINYINT(1) NOT NULL DEFAULT 0,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_blocked_barber_range` (`barber_id`, `start_datetime`, `end_datetime`),
    KEY `ix_blocked_branch_range` (`branch_id`, `start_datetime`),
    KEY `fk_blocked_user` (`created_by`),
    CONSTRAINT `fk_blocked_barber` FOREIGN KEY (`barber_id`)  REFERENCES `barbers` (`id`)   ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_blocked_branch` FOREIGN KEY (`branch_id`)  REFERENCES `branches` (`id`)  ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_blocked_user`   FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)     ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 8. RESERVAS  (núcleo del sistema — spec §51)
--    `active_slot` es una columna generada que vale NULL cuando la reserva
--    está cancelada o marcada no_show. Al ser UNIQUE, la base de datos
--    garantiza a nivel físico que NUNCA existan dos reservas activas con el
--    mismo barbero/fecha/hora de inicio (spec §25, doble reserva).
-- =====================================================================
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `public_code`         VARCHAR(24)  NOT NULL COMMENT 'FLV-260824-A7C2',
    `token`               CHAR(64)     NOT NULL COMMENT 'Token seguro para gestión sin cuenta',
    `branch_id`           INT UNSIGNED NOT NULL,
    `customer_id`         BIGINT UNSIGNED NOT NULL,
    `barber_id`           INT UNSIGNED NOT NULL,
    `service_id`          INT UNSIGNED NOT NULL,
    `booking_date`        DATE NOT NULL,
    `start_time`          TIME NOT NULL,
    `end_time`            TIME NOT NULL,
    `duration_minutes`    SMALLINT UNSIGNED NOT NULL,
    `service_name`        VARCHAR(120) NOT NULL COMMENT 'Snapshot: el servicio puede renombrarse después',
    `subtotal`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total`               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status`              ENUM('pending','confirmed','checked_in','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
    `payment_status`      ENUM('pending','paid','failed','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
    `payment_method`      ENUM('cash','debit','credit','transfer','webpay','mercadopago','other') NULL,
    `source`              ENUM('website','reception','admin','whatsapp','phone','walk_in') NOT NULL DEFAULT 'website',
    `coupon_id`           INT UNSIGNED NULL,
    `customer_notes`      TEXT NULL COMMENT 'Comentario que deja el cliente',
    `internal_notes`      TEXT NULL COMMENT 'Sólo visible para el personal',
    `cancellation_reason` VARCHAR(255) NULL,
    `reminder_24h_sent`   TINYINT(1) NOT NULL DEFAULT 0,
    `reminder_2h_sent`    TINYINT(1) NOT NULL DEFAULT 0,
    `confirmed_at`        DATETIME NULL,
    `checked_in_at`       DATETIME NULL,
    `started_at`          DATETIME NULL,
    `completed_at`        DATETIME NULL,
    `cancelled_at`        DATETIME NULL,
    `created_by`          INT UNSIGNED NULL COMMENT 'NULL = reserva creada por el cliente en la web',
    `cancelled_by`        INT UNSIGNED NULL,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `active_slot`         VARCHAR(48) GENERATED ALWAYS AS (
                              IF(`status` IN ('cancelled','no_show'), NULL,
                                 CONCAT(`barber_id`, '|', `booking_date`, '|', `start_time`))
                          ) STORED,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bookings_code` (`public_code`),
    UNIQUE KEY `uq_bookings_active_slot` (`active_slot`),
    KEY `ix_bookings_agenda` (`barber_id`, `booking_date`, `start_time`),
    KEY `ix_bookings_date_status` (`booking_date`, `status`),
    KEY `ix_bookings_customer` (`customer_id`, `booking_date`),
    KEY `ix_bookings_branch_date` (`branch_id`, `booking_date`),
    KEY `ix_bookings_payment` (`payment_status`),
    KEY `ix_bookings_reminders` (`booking_date`, `reminder_24h_sent`, `reminder_2h_sent`),
    KEY `fk_bookings_service` (`service_id`),
    KEY `fk_bookings_created_by` (`created_by`),
    CONSTRAINT `fk_bookings_branch`   FOREIGN KEY (`branch_id`)   REFERENCES `branches` (`id`)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_bookings_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    -- `barber_id` es columna base de `active_slot` (generada STORED): MySQL prohíbe
    -- CASCADE/SET NULL sobre columnas base, por eso esta FK usa RESTRICT en ambas.
    CONSTRAINT `fk_bookings_barber`   FOREIGN KEY (`barber_id`)   REFERENCES `barbers` (`id`)   ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_bookings_service`  FOREIGN KEY (`service_id`)  REFERENCES `services` (`id`)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_bookings_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)     ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 9. HISTORIAL DE ESTADOS DE RESERVA (spec §61)
-- =====================================================================
DROP TABLE IF EXISTS `booking_status_history`;
CREATE TABLE `booking_status_history` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` BIGINT UNSIGNED NOT NULL,
    `old_status` VARCHAR(20) NULL,
    `new_status` VARCHAR(20) NOT NULL,
    `note`       VARCHAR(255) NULL,
    `changed_by` INT UNSIGNED NULL COMMENT 'NULL = acción del cliente o del sistema',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_history_booking` (`booking_id`, `created_at`),
    KEY `fk_history_user` (`changed_by`),
    CONSTRAINT `fk_history_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_history_user`    FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 10. NOTAS DE CLIENTE (administrativas vs. técnicas de servicio — spec §32)
-- =====================================================================
DROP TABLE IF EXISTS `customer_notes`;
CREATE TABLE `customer_notes` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `booking_id`  BIGINT UNSIGNED NULL,
    `author_id`   INT UNSIGNED NULL,
    `type`        ENUM('service','admin') NOT NULL DEFAULT 'service',
    `note`        TEXT NOT NULL,
    `is_pinned`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se muestra siempre al barbero',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_notes_customer` (`customer_id`, `type`, `created_at`),
    KEY `fk_notes_booking` (`booking_id`),
    KEY `fk_notes_author` (`author_id`),
    CONSTRAINT `fk_notes_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_notes_booking`  FOREIGN KEY (`booking_id`)  REFERENCES `bookings` (`id`)  ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_notes_author`   FOREIGN KEY (`author_id`)   REFERENCES `users` (`id`)     ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 11. PAGOS (spec §37)
-- =====================================================================
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id`     BIGINT UNSIGNED NULL,
    `order_id`       BIGINT UNSIGNED NULL COMMENT 'Para la futura tienda',
    `customer_id`    BIGINT UNSIGNED NULL,
    `amount`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('cash','debit','credit','transfer','webpay','mercadopago','other') NOT NULL DEFAULT 'cash',
    `status`         ENUM('pending','paid','failed','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
    `provider`       VARCHAR(40)  NULL COMMENT 'webpay | mercadopago | manual',
    `transaction_id` VARCHAR(120) NULL,
    `metadata`       TEXT NULL COMMENT 'JSON con la respuesta del proveedor (sin datos sensibles)',
    `notes`          VARCHAR(255) NULL,
    `registered_by`  INT UNSIGNED NULL,
    `paid_at`        DATETIME NULL,
    `refunded_at`    DATETIME NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_payments_booking` (`booking_id`),
    KEY `ix_payments_customer` (`customer_id`),
    KEY `ix_payments_status_date` (`status`, `paid_at`),
    KEY `ix_payments_transaction` (`transaction_id`),
    KEY `fk_payments_user` (`registered_by`),
    CONSTRAINT `fk_payments_booking`  FOREIGN KEY (`booking_id`)    REFERENCES `bookings` (`id`)  ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`)   REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_user`     FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`)     ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 12. COLA DE NOTIFICACIONES (spec §42) — permite agregar un cron después
-- =====================================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`   BIGINT UNSIGNED NULL,
    `booking_id`    BIGINT UNSIGNED NULL,
    `type`          VARCHAR(50) NOT NULL COMMENT 'booking_created, booking_reminder_24h, ...',
    `channel`       ENUM('email','whatsapp','sms') NOT NULL DEFAULT 'email',
    `recipient`     VARCHAR(150) NOT NULL,
    `subject`       VARCHAR(200) NULL,
    `payload`       TEXT NULL COMMENT 'JSON con las variables del mensaje',
    `status`        ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `scheduled_at`  DATETIME NOT NULL,
    `sent_at`       DATETIME NULL,
    `error_message` VARCHAR(255) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_notifications_queue` (`status`, `scheduled_at`),
    KEY `ix_notifications_booking` (`booking_id`),
    KEY `fk_notifications_customer` (`customer_id`),
    CONSTRAINT `fk_notifications_booking`  FOREIGN KEY (`booking_id`)  REFERENCES `bookings` (`id`)  ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_notifications_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 13. TIENDA (preparada — Etapa 2/3)
-- =====================================================================
DROP TABLE IF EXISTS `product_categories`;
CREATE TABLE `product_categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `slug`       VARCHAR(120) NOT NULL,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `branch_id`   INT UNSIGNED NULL,
    `category_id` INT UNSIGNED NULL,
    `name`        VARCHAR(150) NOT NULL,
    `slug`        VARCHAR(170) NOT NULL,
    `sku`         VARCHAR(60)  NULL,
    `description` TEXT NULL,
    `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `sale_price`  DECIMAL(10,2) NULL,
    `cost`        DECIMAL(10,2) NULL,
    `stock`       INT NOT NULL DEFAULT 0,
    `image`       VARCHAR(255) NULL,
    `status`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    UNIQUE KEY `uq_products_sku` (`sku`),
    KEY `fk_products_category` (`category_id`),
    KEY `fk_products_branch` (`branch_id`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_products_branch`   FOREIGN KEY (`branch_id`)   REFERENCES `branches` (`id`)          ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `public_code`    VARCHAR(24) NOT NULL,
    `branch_id`      INT UNSIGNED NOT NULL,
    `customer_id`    BIGINT UNSIGNED NULL,
    `booking_id`     BIGINT UNSIGNED NULL COMMENT 'Venta asociada a una atención',
    `subtotal`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status`         ENUM('pending','paid','cancelled','refunded') NOT NULL DEFAULT 'pending',
    `payment_method` ENUM('cash','debit','credit','transfer','webpay','mercadopago','other') NULL,
    `notes`          VARCHAR(255) NULL,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_code` (`public_code`),
    KEY `ix_orders_customer` (`customer_id`),
    KEY `fk_orders_branch` (`branch_id`),
    KEY `fk_orders_booking` (`booking_id`),
    CONSTRAINT `fk_orders_branch`   FOREIGN KEY (`branch_id`)   REFERENCES `branches` (`id`)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_orders_booking`  FOREIGN KEY (`booking_id`)  REFERENCES `bookings` (`id`)  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`    BIGINT UNSIGNED NOT NULL,
    `product_id`  INT UNSIGNED NULL,
    `service_id`  INT UNSIGNED NULL,
    `name`        VARCHAR(150) NOT NULL COMMENT 'Snapshot del nombre al momento de la venta',
    `unit_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `quantity`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `total`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_order_items_order` (`order_id`),
    KEY `fk_order_items_product` (`product_id`),
    KEY `fk_order_items_service` (`service_id`),
    CONSTRAINT `fk_order_items_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders` (`id`)   ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 14. FIDELIZACIÓN — movimientos, nunca un simple saldo (spec §46)
-- =====================================================================
DROP TABLE IF EXISTS `loyalty_transactions`;
CREATE TABLE `loyalty_transactions` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`    BIGINT UNSIGNED NOT NULL,
    `points`         INT NOT NULL COMMENT 'Positivo = suma, negativo = canje',
    `type`           ENUM('earn','redeem','adjustment','expired') NOT NULL,
    `reference_type` VARCHAR(40) NULL COMMENT 'booking | order | manual',
    `reference_id`   BIGINT UNSIGNED NULL,
    `description`    VARCHAR(255) NULL,
    `created_by`     INT UNSIGNED NULL,
    `expires_at`     DATE NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_loyalty_customer` (`customer_id`, `created_at`),
    CONSTRAINT `fk_loyalty_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 15. PROMOCIONES (preparado — spec §47)
-- =====================================================================
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`         VARCHAR(40) NOT NULL,
    `description`  VARCHAR(255) NULL,
    `type`         ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    `value`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `min_amount`   DECIMAL(10,2) NULL,
    `start_date`   DATE NULL,
    `end_date`     DATE NULL,
    `usage_limit`  INT UNSIGNED NULL,
    `used_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `status`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_coupons_code` (`code`),
    KEY `ix_coupons_status_dates` (`status`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 16. CONFIGURACIÓN EDITABLE DESDE EL PANEL (spec §7)
-- =====================================================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_name`  VARCHAR(40)  NOT NULL DEFAULT 'general',
    `key_name`    VARCHAR(80)  NOT NULL,
    `value`       TEXT NULL,
    `type`        ENUM('string','integer','boolean','json','secret','text') NOT NULL DEFAULT 'string',
    `label`       VARCHAR(150) NULL,
    `description` VARCHAR(255) NULL,
    `is_public`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = puede exponerse al sitio público',
    `updated_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key_name`),
    KEY `ix_settings_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 17. AUDITORÍA (spec §60)
-- =====================================================================
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NULL,
    `action`      VARCHAR(80) NOT NULL COMMENT 'booking.created, price.updated, deploy.executed ...',
    `entity_type` VARCHAR(50) NULL,
    `entity_id`   BIGINT UNSIGNED NULL,
    `description` VARCHAR(255) NULL,
    `old_values`  TEXT NULL,
    `new_values`  TEXT NULL,
    `ip_address`  VARCHAR(45) NULL,
    `user_agent`  VARCHAR(255) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_logs_user_date` (`user_id`, `created_at`),
    KEY `ix_logs_entity` (`entity_type`, `entity_id`),
    KEY `ix_logs_action` (`action`, `created_at`),
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 18. MIGRACIONES (spec §123) — cada archivo se ejecuta UNA sola vez
-- =====================================================================
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration`   VARCHAR(180) NOT NULL,
    `batch`       INT UNSIGNED NOT NULL DEFAULT 1,
    `checksum`    CHAR(40) NULL,
    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_migrations_name` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 19. DESPLIEGUES (panel SUPER_ADMIN — spec §128)
-- =====================================================================
DROP TABLE IF EXISTS `deployments`;
CREATE TABLE `deployments` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version`         VARCHAR(20)  NULL,
    `commit_hash`     VARCHAR(40)  NULL,
    `previous_commit` VARCHAR(40)  NULL,
    `branch`          VARCHAR(80)  NULL,
    `strategy`        ENUM('git','zip','manual') NOT NULL DEFAULT 'git',
    `status`          ENUM('pending','running','success','failed','rolled_back') NOT NULL DEFAULT 'pending',
    `backup_path`     VARCHAR(255) NULL,
    `migrations_run`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `notes`           TEXT NULL,
    `error_message`   TEXT NULL,
    `started_by`      INT UNSIGNED NULL,
    `started_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finished_at`     DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `ix_deployments_status` (`status`, `started_at`),
    KEY `fk_deployments_user` (`started_by`),
    CONSTRAINT `fk_deployments_user` FOREIGN KEY (`started_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
--  DATOS INICIALES MÍNIMOS
-- =====================================================================

-- Sucursal principal
INSERT INTO `branches` (`id`, `name`, `slug`, `address`, `commune`, `city`, `phone`, `whatsapp`, `email`, `is_default`, `status`)
VALUES (1, 'Flava Studio Principal', 'flava-studio-principal', 'Av. Principal 1234', 'Santiago', 'Santiago', '+56912345678', '+56912345678', 'hola@flava.cl', 1, 1);

-- Usuario SUPER_ADMIN inicial.
--   email:    admin@flava.cl
--   password: Flava2026!
-- ⚠ El sistema OBLIGA a cambiarla en el primer ingreso (must_change_password = 1).
INSERT INTO `users` (`id`, `branch_id`, `first_name`, `last_name`, `email`, `password`, `role`, `status`, `must_change_password`)
VALUES (1, 1, 'Súper', 'Administrador', 'admin@flava.cl',
        '$2y$12$wMirDtMZ.qqOX2UDD9kJHuwJbIKYspLZUuaY4K047xvSk8oMovrde',
        'SUPER_ADMIN', 1, 1);

-- Categorías de servicio
INSERT INTO `service_categories` (`id`, `name`, `slug`, `sort_order`, `status`) VALUES
(1, 'Cortes',   'cortes',   1, 1),
(2, 'Barba',    'barba',    2, 1),
(3, 'Combos',   'combos',   3, 1),
(4, 'Premium',  'premium',  4, 1);

-- Servicios base
INSERT INTO `services` (`category_id`, `name`, `slug`, `description`, `price`, `duration_minutes`, `sort_order`, `is_featured`, `status`) VALUES
(1, 'Corte Fade',     'corte-fade',     'Degradado clásico o moderno, terminación con navaja.',        15000.00, 45, 1, 1, 1),
(1, 'Corte Clásico',  'corte-clasico',  'Corte tradicional a tijera y máquina.',                       13000.00, 40, 2, 0, 1),
(1, 'Corte Niño',     'corte-nino',     'Corte para menores de 12 años.',                              10000.00, 30, 3, 0, 1),
(2, 'Barba',          'barba',          'Perfilado, toalla caliente y aceite.',                         8000.00, 25, 4, 0, 1),
(3, 'Corte + Barba',  'corte-barba',    'El combo completo: corte fade y barba trabajada.',            18000.00, 60, 5, 1, 1),
(4, 'Servicio Premium','servicio-premium','Corte, barba, ritual de toalla caliente y styling.',        25000.00, 75, 6, 1, 1);

-- Configuración inicial editable desde el panel
INSERT INTO `settings` (`group_name`, `key_name`, `value`, `type`, `label`, `is_public`) VALUES
('general',  'business_name',            'Flava Studio',                     'string',  'Nombre del negocio', 1),
('general',  'business_tagline',         'Tu estilo. Tu momento.',           'string',  'Eslogan', 1),
('general',  'business_email',           'hola@flava.cl',                    'string',  'Email de contacto', 1),
('general',  'business_phone',           '+56912345678',                     'string',  'Teléfono', 1),
('general',  'business_whatsapp',        '+56912345678',                     'string',  'WhatsApp', 1),
('general',  'business_address',         'Av. Principal 1234, Santiago',     'string',  'Dirección', 1),
('general',  'business_instagram',       'flavastudio',                      'string',  'Instagram', 1),
('general',  'business_maps_url',        '',                                 'string',  'URL Google Maps', 1),
('general',  'business_logo',            '',                                 'string',  'Logo', 1),
('booking',  'slot_interval',            '15',                               'integer', 'Intervalo entre horarios (min)', 0),
('booking',  'min_advance_minutes',      '60',                               'integer', 'Anticipación mínima (min)', 0),
('booking',  'max_advance_days',         '60',                               'integer', 'Máxima anticipación (días)', 0),
('booking',  'buffer_minutes',           '0',                                'integer', 'Colchón entre citas (min)', 0),
('booking',  'cancel_limit_hours',       '2',                                'integer', 'Horas mínimas para cancelar', 1),
('booking',  'reschedule_limit_hours',   '2',                                'integer', 'Horas mínimas para reprogramar', 1),
('booking',  'auto_confirm',             '1',                                'boolean', 'Confirmar reservas automáticamente', 0),
('booking',  'allow_any_barber',         '1',                                'boolean', 'Permitir "cualquier barbero disponible"', 0),
('booking',  'require_rut',              '1',                                'boolean', 'Exigir RUT en el checkout', 0),
('booking',  'booking_policy',           'Te pedimos llegar 5 minutos antes. Las cancelaciones deben realizarse con al menos 2 horas de anticipación.', 'text', 'Políticas de reserva', 1),
('payment',  'payment_methods_public',   '["cash","debit","credit","transfer"]', 'json', 'Métodos de pago en el checkout', 1),
('notify',   'booking_reminder_hours_1', '24',                               'integer', 'Primer recordatorio (horas antes)', 0),
('notify',   'booking_reminder_hours_2', '2',                                'integer', 'Segundo recordatorio (horas antes)', 0),
('notify',   'email_enabled',            '0',                                'boolean', 'Envío de emails activo', 0),
('notify',   'whatsapp_enabled',         '0',                                'boolean', 'Envío de WhatsApp activo', 0),
('github',   'github_enabled',           '0',                                'boolean', 'Integración GitHub activa', 0),
('github',   'github_owner',             '',                                 'string',  'GitHub owner', 0),
('github',   'github_repository',        '',                                 'string',  'Repositorio', 0),
('github',   'github_branch',            'main',                             'string',  'Rama de producción', 0),
('github',   'github_token',             '',                                 'secret',  'Token cifrado', 0),
('github',   'github_token_hint',        '',                                 'string',  'Últimos caracteres del token', 0),
('github',   'deploy_auto_backup',       '1',                                'boolean', 'Respaldar antes de actualizar', 0),
('github',   'deploy_maintenance',       '1',                                'boolean', 'Activar mantención al desplegar', 0),
('github',   'github_last_check',        '',                                 'string',  'Última verificación', 0),
('github',   'github_last_sync',         '',                                 'string',  'Última sincronización', 0);

-- Registro de la instalación inicial
INSERT INTO `migrations` (`migration`, `batch`, `executed_at`) VALUES
('00000000_000_baseline_flava_1_0_0', 1, NOW());

-- =====================================================================
--  FIN — Flava Studio v1.0.0
--  Siguiente paso: ingresar a /login con admin@flava.cl / Flava2026!
--  y cambiar la contraseña (el sistema lo exigirá).
-- =====================================================================
