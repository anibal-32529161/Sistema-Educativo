-- ============================================================
--  SISTEMA DE GESTIÓN ACADÉMICA — GestiónAcad
--  Universidad Nacional Experimental Rómulo Gallegos (UNERG)
--  Proyecto de Grado — Ingeniería en Informática
--  Base de datos: sistema_academico
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `sistema_academico`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sistema_academico`;


-- ============================================================
-- TABLA 1: usuarios
-- Almacena tanto docentes como estudiantes en una sola tabla.
-- La columna 'rol' diferencia quién es quién.
-- Los estudiantes se identifican por su cédula; los docentes
-- por su correo electrónico institucional.
-- ============================================================
CREATE TABLE `usuarios` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `rol`        ENUM('docente','estudiante') NOT NULL,
    `nombre`     VARCHAR(100) NOT NULL,
    `apellido`   VARCHAR(100) NOT NULL,
    `cedula`     VARCHAR(20)  DEFAULT NULL COMMENT 'Solo requerida para estudiantes',
    `email`      VARCHAR(150) DEFAULT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cedula` (`cedula`),
    UNIQUE KEY `uq_email`  (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLA 2: cursos
-- Representa una materia o asignatura dictada por un docente.
-- Un docente puede tener varios cursos; un curso pertenece
-- a un único docente.
-- ============================================================
CREATE TABLE `cursos` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `docente_id`  INT          NOT NULL,
    `nombre`      VARCHAR(150) NOT NULL,
    `codigo`      VARCHAR(30)  DEFAULT NULL COMMENT 'Ej: PROG-101',
    `descripcion` TEXT         DEFAULT NULL,
    `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_codigo` (`codigo`),
    CONSTRAINT `fk_curso_docente`
        FOREIGN KEY (`docente_id`) REFERENCES `usuarios`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLA 3: evaluaciones
-- Cada evaluación pertenece a un curso.
-- 'porcentaje' indica cuánto vale esa evaluación sobre el
-- total de la nota del curso (la suma no debe superar 100).
-- ============================================================
CREATE TABLE `evaluaciones` (
    `id`          INT            NOT NULL AUTO_INCREMENT,
    `curso_id`    INT            NOT NULL,
    `nombre`      VARCHAR(150)   NOT NULL COMMENT 'Ej: Primer Parcial',
    `descripcion` TEXT           DEFAULT NULL,
    `porcentaje`  DECIMAL(5,2)   NOT NULL COMMENT '% del total de la nota',
    `fecha`       DATE           DEFAULT NULL,
    `created_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_eval_curso`
        FOREIGN KEY (`curso_id`) REFERENCES `cursos`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLA 4: matriculas
-- Registra qué estudiantes están inscritos en qué cursos.
-- La restricción UNIQUE evita matrículas duplicadas.
-- ============================================================
CREATE TABLE `matriculas` (
    `id`               INT       NOT NULL AUTO_INCREMENT,
    `estudiante_id`    INT       NOT NULL,
    `curso_id`         INT       NOT NULL,
    `fecha_matricula`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_matricula` (`estudiante_id`, `curso_id`),
    CONSTRAINT `fk_mat_estudiante`
        FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_mat_curso`
        FOREIGN KEY (`curso_id`) REFERENCES `cursos`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLA 5: calificaciones
-- Almacena la nota de un estudiante en una evaluación.
-- Se vincula a través de la matrícula para garantizar que
-- solo se califique a estudiantes inscritos en el curso.
-- ============================================================
CREATE TABLE `calificaciones` (
    `id`              INT           NOT NULL AUTO_INCREMENT,
    `matricula_id`    INT           NOT NULL,
    `evaluacion_id`   INT           NOT NULL,
    `nota`            DECIMAL(5,2)  DEFAULT NULL COMMENT 'Escala 0–20',
    `observacion`     TEXT          DEFAULT NULL,
    `fecha_registro`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_calificacion` (`matricula_id`, `evaluacion_id`),
    CONSTRAINT `fk_cal_matricula`
        FOREIGN KEY (`matricula_id`) REFERENCES `matriculas`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cal_evaluacion`
        FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLA 6: enlaces_acceso
-- El docente genera un token único por curso.
-- El estudiante usa ese enlace para inscribirse sin necesidad
-- de que el docente lo agregue manualmente.
-- ============================================================
CREATE TABLE `enlaces_acceso` (
    `id`               INT          NOT NULL AUTO_INCREMENT,
    `curso_id`         INT          NOT NULL,
    `token`            VARCHAR(64)  NOT NULL COMMENT 'UUID o hash aleatorio',
    `activo`           TINYINT(1)   NOT NULL DEFAULT 1,
    `fecha_expiracion` DATETIME     DEFAULT NULL COMMENT 'NULL = sin expiración',
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`token`),
    CONSTRAINT `fk_enlace_curso`
        FOREIGN KEY (`curso_id`) REFERENCES `cursos`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- DIAGRAMA RELACIONAL (referencia rápida)
--
--  usuarios (docente) ──< cursos ──< evaluaciones
--                                         │
--  usuarios (estudiante) ──< matriculas ──┘
--                                │
--                          calificaciones
--
--  cursos ──< enlaces_acceso
-- ============================================================
