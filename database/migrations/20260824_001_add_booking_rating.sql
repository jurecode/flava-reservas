-- Ruta: /database/migrations/20260824_001_add_booking_rating.sql
-- Agrega la calificación opcional del cliente tras la atención.
--
-- Reglas:
--   · Debe poder ejecutarse UNA sola vez.
--   · Nunca eliminar datos existentes sin respaldo previo.

ALTER TABLE `bookings`
    ADD COLUMN `rating` TINYINT UNSIGNED NULL COMMENT 'Calificación 1-5 dejada por el cliente' AFTER `internal_notes`,
    ADD COLUMN `rating_comment` VARCHAR(500) NULL AFTER `rating`,
    ADD COLUMN `rated_at` DATETIME NULL AFTER `rating_comment`;

ALTER TABLE `bookings`
    ADD INDEX `ix_bookings_rating` (`rating`);
