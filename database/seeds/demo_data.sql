-- =====================================================================
--  Ruta: /database/seeds/demo_data.sql
--  Datos de DEMOSTRACIÓN para probar el sistema (barberos, horarios,
--  bloqueos y usuarios de cada rol).
--
--  ⚠ NO importar en producción: son datos ficticios.
--     Contraseña de todos los usuarios demo: Flava2026!
-- =====================================================================

SET NAMES utf8mb4;

-- ---- Usuarios de cada rol ----
INSERT INTO `users` (`branch_id`, `first_name`, `last_name`, `email`, `password`, `role`, `status`, `must_change_password`) VALUES
(1, 'Camila',    'Rojas',  'recepcion@flava.cl', '$2y$12$wMirDtMZ.qqOX2UDD9kJHuwJbIKYspLZUuaY4K047xvSk8oMovrde', 'RECEPTION', 1, 0),
(1, 'Sebastián', 'Vega',   'sebastian@flava.cl', '$2y$12$wMirDtMZ.qqOX2UDD9kJHuwJbIKYspLZUuaY4K047xvSk8oMovrde', 'BARBER',    1, 0),
(1, 'Matías',    'Fuentes','matias@flava.cl',    '$2y$12$wMirDtMZ.qqOX2UDD9kJHuwJbIKYspLZUuaY4K047xvSk8oMovrde', 'BARBER',    1, 0);

-- ---- Barberos ----
INSERT INTO `barbers` (`user_id`, `branch_id`, `first_name`, `last_name`, `display_name`, `slug`, `email`, `phone`, `specialty`, `bio`, `color`, `accepts_online`, `sort_order`, `status`) VALUES
((SELECT id FROM users WHERE email = 'sebastian@flava.cl'), 1, 'Sebastián', 'Vega', 'Sebastián', 'sebastian', 'sebastian@flava.cl', '+56911111111',
 'Fade · Barbería clásica · Barba', 'Ocho años trabajando el degradado. Especialista en fades y trabajos de barba con navaja.', '#FFC400', 1, 1, 1),
((SELECT id FROM users WHERE email = 'matias@flava.cl'), 1, 'Matías', 'Fuentes', 'Matías', 'matias', 'matias@flava.cl', '+56922222222',
 'Corte urbano · Diseños · Color', 'Cortes modernos, diseños a navaja y color. Le gusta proponer.', '#1C7ED6', 1, 2, 1);

-- ---- Servicios por barbero ----
-- Sebastián: todos los servicios
INSERT INTO `barber_services` (`barber_id`, `service_id`)
SELECT (SELECT id FROM barbers WHERE slug = 'sebastian'), id FROM services;

-- Matías: todo menos el corte de niño
INSERT INTO `barber_services` (`barber_id`, `service_id`)
SELECT (SELECT id FROM barbers WHERE slug = 'matias'), id FROM services WHERE slug <> 'corte-nino';

-- ---- Horarios semanales ----
-- Sebastián: lunes a viernes 10:00–20:00 (con corte de almuerzo vía bloqueo), sábado 10:00–17:00
INSERT INTO `barber_schedules` (`barber_id`, `weekday`, `start_time`, `end_time`, `status`) VALUES
((SELECT id FROM barbers WHERE slug = 'sebastian'), 1, '10:00:00', '20:00:00', 1),
((SELECT id FROM barbers WHERE slug = 'sebastian'), 2, '10:00:00', '20:00:00', 1),
((SELECT id FROM barbers WHERE slug = 'sebastian'), 3, '10:00:00', '20:00:00', 1),
((SELECT id FROM barbers WHERE slug = 'sebastian'), 4, '10:00:00', '20:00:00', 1),
((SELECT id FROM barbers WHERE slug = 'sebastian'), 5, '10:00:00', '20:00:00', 1),
((SELECT id FROM barbers WHERE slug = 'sebastian'), 6, '10:00:00', '17:00:00', 1);

-- Matías: dos bloques diarios (mañana y tarde), miércoles libre
INSERT INTO `barber_schedules` (`barber_id`, `weekday`, `start_time`, `end_time`, `status`) VALUES
((SELECT id FROM barbers WHERE slug = 'matias'), 1, '09:00:00', '13:00:00', 1),
((SELECT id FROM barbers WHERE slug = 'matias'), 1, '14:00:00', '19:00:00', 1),
((SELECT id FROM barbers WHERE slug = 'matias'), 2, '09:00:00', '13:00:00', 1),
((SELECT id FROM barbers WHERE slug = 'matias'), 2, '14:00:00', '19:00:00', 1),
((SELECT id FROM barbers WHERE slug = 'matias'), 4, '12:00:00', '21:00:00', 1),
((SELECT id FROM barbers WHERE slug = 'matias'), 5, '12:00:00', '21:00:00', 1),
((SELECT id FROM barbers WHERE slug = 'matias'), 6, '10:00:00', '17:00:00', 1);

-- ---- Clientes de ejemplo ----
INSERT INTO `customers` (`branch_id`, `first_name`, `last_name`, `rut`, `rut_normalized`, `email`, `phone`, `whatsapp_phone`, `status`) VALUES
(1, 'Rodrigo', 'Muñoz', '11.111.111-1', '11111111-1', 'rodrigo@example.cl', '+56933333333', '+56933333333', 1),
(1, 'Juan',    'Pérez', '12.345.678-5', '12345678-5', 'juan@example.cl',    '+56944444444', '+56944444444', 1),
(1, 'Carlos',  'Soto',  '13.579.246-2', '13579246-2', 'carlos@example.cl',  '+56955555555', '+56955555555', 1);

-- ---- Almuerzo recurrente de hoy (bloqueo de ejemplo) ----
INSERT INTO `blocked_times` (`branch_id`, `barber_id`, `start_datetime`, `end_datetime`, `type`, `reason`) VALUES
(1, (SELECT id FROM barbers WHERE slug = 'sebastian'),
 CONCAT(CURDATE(), ' 14:00:00'), CONCAT(CURDATE(), ' 15:00:00'), 'lunch', 'Almuerzo');

-- ---- Nota técnica de ejemplo ----
INSERT INTO `customer_notes` (`customer_id`, `author_id`, `type`, `note`, `is_pinned`) VALUES
((SELECT id FROM customers WHERE rut_normalized = '12345678-5'),
 (SELECT id FROM users WHERE email = 'sebastian@flava.cl'),
 'service',
 'Fade bajo. Máquina #1 en los costados, tijera arriba. Prefiere la barba corta y perfilada.',
 1);
