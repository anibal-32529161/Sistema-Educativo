<?php
$host = 'localhost';
$db   = 'sistema_academico';
$user = 'root';
$pass = '';

try {
    // Conectar sin especificar base de datos para poder crearla si no existe
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear la base de datos si no existe
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");

    // Crear tablas si no existen
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `usuarios` (
            `id`         INT          NOT NULL AUTO_INCREMENT,
            `rol`        ENUM('docente','estudiante','admin') NOT NULL,
            `nombre`     VARCHAR(100) NOT NULL,
            `apellido`   VARCHAR(100) NOT NULL,
            `cedula`     VARCHAR(20)  DEFAULT NULL,
            `email`      VARCHAR(150) DEFAULT NULL,
            `password`   VARCHAR(255) NOT NULL,
            `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_cedula` (`cedula`),
            UNIQUE KEY `uq_email`  (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cursos` (
            `id`          INT          NOT NULL AUTO_INCREMENT,
            `docente_id`  INT          NOT NULL,
            `nombre`      VARCHAR(150) NOT NULL,
            `codigo`      VARCHAR(30)  DEFAULT NULL,
            `descripcion` TEXT         DEFAULT NULL,
            `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
            `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_codigo` (`codigo`),
            CONSTRAINT `fk_curso_docente`
                FOREIGN KEY (`docente_id`) REFERENCES `usuarios`(`id`)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `evaluaciones` (
            `id`          INT           NOT NULL AUTO_INCREMENT,
            `curso_id`    INT           NOT NULL,
            `nombre`      VARCHAR(150)  NOT NULL,
            `descripcion` TEXT          DEFAULT NULL,
            `porcentaje`  DECIMAL(5,2)  NOT NULL,
            `fecha`       DATE          DEFAULT NULL,
            `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk_eval_curso`
                FOREIGN KEY (`curso_id`) REFERENCES `cursos`(`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `matriculas` (
            `id`              INT       NOT NULL AUTO_INCREMENT,
            `estudiante_id`   INT       NOT NULL,
            `curso_id`        INT       NOT NULL,
            `fecha_matricula` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_matricula` (`estudiante_id`, `curso_id`),
            CONSTRAINT `fk_mat_estudiante`
                FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios`(`id`)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_mat_curso`
                FOREIGN KEY (`curso_id`) REFERENCES `cursos`(`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `calificaciones` (
            `id`             INT          NOT NULL AUTO_INCREMENT,
            `matricula_id`   INT          NOT NULL,
            `evaluacion_id`  INT          NOT NULL,
            `nota`           DECIMAL(5,2) DEFAULT NULL,
            `observacion`    TEXT         DEFAULT NULL,
            `fecha_registro` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_calificacion` (`matricula_id`, `evaluacion_id`),
            CONSTRAINT `fk_cal_matricula`
                FOREIGN KEY (`matricula_id`) REFERENCES `matriculas`(`id`)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_cal_evaluacion`
                FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones`(`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `enlaces_acceso` (
            `id`               INT         NOT NULL AUTO_INCREMENT,
            `curso_id`         INT         NOT NULL,
            `token`            VARCHAR(64) NOT NULL,
            `activo`           TINYINT(1)  NOT NULL DEFAULT 1,
            `fecha_expiracion` DATETIME    DEFAULT NULL,
            `created_at`       TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_token` (`token`),
            CONSTRAINT `fk_enlace_curso`
                FOREIGN KEY (`curso_id`) REFERENCES `cursos`(`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
