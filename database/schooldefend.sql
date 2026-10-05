-- SchoolDefend: estructura y datos ficticios para una instalación nueva.
-- Importar una sola vez. No contiene DROP, TRUNCATE ni DELETE.
-- Si schooldefend ya existe, detenerse y revisar antes de importar.
CREATE DATABASE schooldefend CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE schooldefend;
SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- 1. Cuentas. El rol es una lista fija, no un módulo adicional.
CREATE TABLE usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    nombre_usuario VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('administrador','tecnico','profesor') NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activo TINYINT NOT NULL DEFAULT 1,
    fecha_baja DATETIME NULL,
    id_usuario_baja INT UNSIGNED NULL,
    motivo_baja VARCHAR(255) NULL,
    FOREIGN KEY (id_usuario_baja) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 2. Sectores de la escuela.
CREATE TABLE ubicaciones (
    id_ubicacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    activo TINYINT NOT NULL DEFAULT 1,
    fecha_baja DATETIME NULL,
    id_usuario_baja INT UNSIGNED NULL,
    motivo_baja VARCHAR(255) NULL,
    FOREIGN KEY (id_usuario_baja) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 3. Inventario: estado técnico y activo son conceptos diferentes.
CREATE TABLE dispositivos (
    id_dispositivo INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    tipo ENUM('pc','notebook','impresora','proyector','router','access_point','otro') NOT NULL,
    marca VARCHAR(60) NULL,
    modelo VARCHAR(100) NULL,
    numero_serie VARCHAR(100) NULL,
    sistema_operativo VARCHAR(100) NULL,
    ip VARCHAR(45) NULL,
    mac VARCHAR(17) NULL,
    id_ubicacion INT UNSIGNED NOT NULL,
    id_responsable INT UNSIGNED NULL,
    estado ENUM('operativo','en_reparacion','fuera_de_servicio') NOT NULL DEFAULT 'operativo',
    observaciones TEXT NULL,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activo TINYINT NOT NULL DEFAULT 1,
    fecha_baja DATETIME NULL,
    id_usuario_baja INT UNSIGNED NULL,
    motivo_baja VARCHAR(255) NULL,
    FOREIGN KEY (id_ubicacion) REFERENCES ubicaciones(id_ubicacion) ON DELETE RESTRICT,
    FOREIGN KEY (id_responsable) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario_baja) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 4. Problemas de soporte. Un ticket puede no identificar un equipo.
CREATE TABLE tickets (
    id_ticket INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    id_ubicacion INT UNSIGNED NOT NULL,
    id_dispositivo INT UNSIGNED NULL,
    id_creador INT UNSIGNED NOT NULL,
    id_tecnico INT UNSIGNED NULL,
    prioridad ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
    estado ENUM('abierto','en_proceso','en_espera_repuesto','resuelto','cancelado') NOT NULL DEFAULT 'abierto',
    diagnostico TEXT NULL,
    solucion TEXT NULL,
    motivo_cancelacion TEXT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre DATETIME NULL,
    FOREIGN KEY (id_ubicacion) REFERENCES ubicaciones(id_ubicacion) ON DELETE RESTRICT,
    FOREIGN KEY (id_dispositivo) REFERENCES dispositivos(id_dispositivo) ON DELETE RESTRICT,
    FOREIGN KEY (id_creador) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    FOREIGN KEY (id_tecnico) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 5. Una fila por novedad, sin reemplazar las anteriores.
CREATE TABLE seguimiento_tickets (
    id_seguimiento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ticket INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_ticket) REFERENCES tickets(id_ticket) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 6. Registro manual de seguridad, separado del soporte técnico.
CREATE TABLE incidentes (
    id_incidente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    tipo ENUM('malware','phishing','acceso_no_autorizado','perdida_equipo','otro') NOT NULL,
    descripcion TEXT NOT NULL,
    severidad ENUM('baja','media','alta') NOT NULL,
    estado ENUM('reportado','en_investigacion','cerrado') NOT NULL DEFAULT 'reportado',
    id_dispositivo INT UNSIGNED NULL,
    id_ticket INT UNSIGNED NULL,
    id_creador INT UNSIGNED NOT NULL,
    id_responsable INT UNSIGNED NOT NULL,
    acciones_realizadas TEXT NULL,
    conclusion TEXT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre DATETIME NULL,
    FOREIGN KEY (id_dispositivo) REFERENCES dispositivos(id_dispositivo) ON DELETE RESTRICT,
    FOREIGN KEY (id_ticket) REFERENCES tickets(id_ticket) ON DELETE RESTRICT,
    FOREIGN KEY (id_creador) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    FOREIGN KEY (id_responsable) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Datos ficticios. Las tablas se crean primero; los datos se cargan juntos.
START TRANSACTION;

-- Cuentas de demostración: admin, tecnico, profesor.
-- Contraseña de las tres: DemoEscolar2026! (almacenada como hash de PHP).
INSERT INTO usuarios (id_usuario, nombre, nombre_usuario, password_hash, rol, fecha_creacion) VALUES
(1, 'Administración Demo', 'admin', '$2y$10$jglK4gFu.nX3yn1cZBFCCux0ACmOh.vH7qZt.iK4uu0p1FbxdLL/K', 'administrador', '2026-09-01 08:00:00'),
(2, 'Técnico Demo', 'tecnico', '$2y$10$jglK4gFu.nX3yn1cZBFCCux0ACmOh.vH7qZt.iK4uu0p1FbxdLL/K', 'tecnico', '2026-09-01 08:00:00'),
(3, 'Docente Demo', 'profesor', '$2y$10$jglK4gFu.nX3yn1cZBFCCux0ACmOh.vH7qZt.iK4uu0p1FbxdLL/K', 'profesor', '2026-09-01 08:00:00');

INSERT INTO ubicaciones (id_ubicacion, nombre, descripcion) VALUES
(1, 'Laboratorio 1', 'Sala de informática'),
(2, 'Aula 3', 'Aula con proyector'),
(3, 'Secretaría', 'Sector administrativo');

INSERT INTO dispositivos (id_dispositivo, codigo, tipo, marca, modelo, id_ubicacion, id_responsable, estado, fecha_alta) VALUES
(1, 'PC-LAB-01', 'pc', 'Marca Demo', 'Escritorio A', 1, 2, 'operativo', '2026-09-01 09:00:00'),
(2, 'PC-LAB-02', 'pc', 'Marca Demo', 'Escritorio B', 1, 2, 'en_reparacion', '2026-09-01 09:00:00'),
(3, 'IMP-SEC-01', 'impresora', 'Marca Demo', 'Láser A', 3, 1, 'en_reparacion', '2026-09-01 09:00:00'),
(4, 'PRO-A03-01', 'proyector', 'Marca Demo', 'Proyector A', 2, 3, 'operativo', '2026-09-01 09:00:00'),
(5, 'PC-LAB-03', 'pc', 'Marca Demo', 'Escritorio C', 1, NULL, 'fuera_de_servicio', '2026-09-01 09:00:00');

INSERT INTO tickets (id_ticket, titulo, descripcion, id_ubicacion, id_dispositivo, id_creador, id_tecnico, prioridad, estado, diagnostico, solucion, motivo_cancelacion, fecha_creacion, fecha_cierre) VALUES
(1, 'Sin conexión en el aula', 'El aula 3 no tiene acceso a la red.', 2, NULL, 3, NULL, 'media', 'abierto', NULL, NULL, NULL, '2026-09-27 08:00:00', NULL),
(2, 'Ventanas extrañas', 'La PC muestra mensajes inesperados.', 1, 2, 3, 2, 'alta', 'en_proceso', 'Se investiga software sospechoso.', NULL, NULL, '2026-09-24 09:00:00', NULL),
(3, 'Impresora no toma papel', 'La impresora de Secretaría no permite imprimir.', 3, 3, 1, 2, 'media', 'en_proceso', 'Rodillo de alimentación deteriorado.', NULL, NULL, '2026-09-23 09:00:00', NULL),
(4, 'Proyector sin imagen', 'No aparece la imagen de la computadora.', 2, 4, 3, 2, 'media', 'resuelto', 'Conector interno deteriorado.', 'El taller reemplazó el conector y se verificó la imagen.', NULL, '2026-09-10 09:00:00', '2026-09-15 12:00:00'),
(5, 'Equipo antiguo no enciende', 'No responde al botón de encendido.', 1, 5, 3, 2, 'baja', 'cancelado', 'Falla de placa; se decidió retirar el equipo.', NULL, 'Reparación inviable; se gestionará la baja.', '2026-09-05 09:00:00', '2026-09-06 12:00:00');

INSERT INTO seguimiento_tickets (id_ticket, id_usuario, descripcion, fecha) VALUES
(2, 2, 'Abierto a en proceso. Asignado al Técnico Demo; prioridad alta.', '2026-09-24 10:00:00'),
(3, 2, 'Abierto a en proceso. Se revisó el mecanismo de papel.', '2026-09-23 10:00:00'),
(3, 2, 'Se llevó a reparar a Taller Demo para reemplazar el rodillo de alimentación.', '2026-09-24 11:00:00'),
(4, 2, 'Abierto a en proceso. Se revisaron cable y entrada de imagen.', '2026-09-10 10:00:00'),
(4, 2, 'Se llevó a reparar a Taller Demo para revisar el conector de imagen.', '2026-09-11 09:00:00'),
(4, 2, 'El equipo volvió del taller con el conector reemplazado; pendiente de prueba.', '2026-09-15 10:00:00'),
(4, 2, 'En proceso a resuelto. Imagen comprobada en el aula.', '2026-09-15 12:00:00'),
(5, 2, 'Abierto a en proceso. Se revisó la alimentación y la placa.', '2026-09-05 10:00:00'),
(5, 2, 'En proceso a cancelado. Se decidió retirar el equipo.', '2026-09-06 12:00:00');

INSERT INTO incidentes (titulo, tipo, descripcion, severidad, estado, id_dispositivo, id_ticket, id_creador, id_responsable, acciones_realizadas, conclusion, fecha_creacion, fecha_cierre) VALUES
('Posible malware en laboratorio', 'malware', 'Mensajes sospechosos en PC-LAB-02.', 'alta', 'en_investigacion', 2, 2, 2, 2, 'Se desconectó el equipo de la red para revisarlo.', NULL, '2026-09-24 11:00:00', NULL),
('Correo sospechoso reportado', 'phishing', 'Se recibió un mensaje solicitando una contraseña.', 'media', 'cerrado', NULL, NULL, 2, 2, 'Se verificó el mensaje y se informó al docente.', 'Mensaje descartado sin abrir enlaces ni entregar datos.', '2026-09-18 09:00:00', '2026-09-18 10:00:00');

-- Ejemplo de baja lógica: el ticket 5 continúa relacionado con este equipo.
UPDATE dispositivos SET activo = 0, fecha_baja = '2026-09-07 09:00:00',
    id_usuario_baja = 1, motivo_baja = 'Equipo retirado por falla no reparable.'
WHERE id_dispositivo = 5;

COMMIT;
