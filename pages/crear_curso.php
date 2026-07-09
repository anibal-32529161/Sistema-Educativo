<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'docente') {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$error   = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim($_POST['nombre'] ?? '');
    $codigo      = trim($_POST['codigo'] ?? '') ?: null;
    $descripcion = trim($_POST['descripcion'] ?? '') ?: null;

    if ($nombre === '') {
        $error = "El nombre del curso es obligatorio.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO cursos (docente_id, nombre, codigo, descripcion)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$_SESSION['user_id'], $nombre, $codigo, $descripcion]);
            $nuevo_id = $pdo->lastInsertId();
            header("Location: ver_curso.php?id=$nuevo_id&creado=1");
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Ya existe un curso con ese código. Elige otro.";
            } else {
                $error = "Error al crear el curso. Intente de nuevo.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Curso — GestiónAcad</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">

    <header class="dash-header">
        <span class="logo">&#127979; GestiónAcad</span>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                <span class="badge-rol badge-docente">Docente</span>
            </span>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <main class="dash-main" style="max-width:680px;">

        <div class="dash-welcome">
            <a href="dashboard_docente.php" style="color:#5c9cf5; font-size:0.88rem; text-decoration:none;">
                &#8592; Volver al panel
            </a>
            <h2 style="margin-top:10px;">Crear nuevo curso</h2>
            <p>Completa los datos de la materia o asignatura.</p>
        </div>

        <div class="section-card">

            <?php if ($error): ?>
                <div class="alert-error" style="margin-bottom:16px;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">

                <div class="field-group">
                    <label class="field-label">Nombre del curso <span class="required">*</span></label>
                    <input type="text" name="nombre" placeholder="Ej: Programación I"
                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                           maxlength="150" required>
                </div>

                <div class="field-group">
                    <label class="field-label">Código <span class="field-hint">(opcional)</span></label>
                    <input type="text" name="codigo" placeholder="Ej: PROG-101"
                           value="<?php echo htmlspecialchars($_POST['codigo'] ?? ''); ?>"
                           maxlength="30">
                </div>

                <div class="field-group">
                    <label class="field-label">Descripción <span class="field-hint">(opcional)</span></label>
                    <textarea name="descripcion" placeholder="Breve descripción del curso..."
                              rows="4"><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                </div>

                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit">&#10003; Crear Curso</button>
                    <a href="dashboard_docente.php" class="btn-cancel">Cancelar</a>
                </div>

            </form>
        </div>
    </main>
</div>
</body>
</html>
