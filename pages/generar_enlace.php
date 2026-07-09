<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'docente') {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$docente_id = $_SESSION['user_id'];
$curso_id   = (int)($_POST['curso_id'] ?? $_GET['curso_id'] ?? 0);

// Cursos del docente (para el selector)
$stmtCursos = $pdo->prepare(
    "SELECT id, nombre, codigo FROM cursos WHERE docente_id = ? AND activo = 1 ORDER BY nombre"
);
$stmtCursos->execute([$docente_id]);
$cursos = $stmtCursos->fetchAll();

// ── Si ya tenemos curso_id y es POST → generar enlace ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $curso_id) {

    // Verificar que el curso le pertenece
    $chk = $pdo->prepare("SELECT id FROM cursos WHERE id = ? AND docente_id = ?");
    $chk->execute([$curso_id, $docente_id]);

    if ($chk->fetch()) {
        // Desactivar enlaces anteriores
        $pdo->prepare("UPDATE enlaces_acceso SET activo = 0 WHERE curso_id = ?")
            ->execute([$curso_id]);

        // Generar token único
        $token = bin2hex(random_bytes(24));
        $pdo->prepare("INSERT INTO enlaces_acceso (curso_id, token, activo) VALUES (?, ?, 1)")
            ->execute([$curso_id, $token]);

        header("Location: ver_curso.php?id=$curso_id&enlace=1");
        exit();
    }
}

// ── Vista: selector de curso ────────────────────────────────────
// Mostrar información de enlace existente si ya hay curso_id (GET)
$enlaceActual = null;
if ($curso_id) {
    $chk = $pdo->prepare("SELECT id FROM cursos WHERE id = ? AND docente_id = ?");
    $chk->execute([$curso_id, $docente_id]);
    if ($chk->fetch()) {
        $eStmt = $pdo->prepare(
            "SELECT token FROM enlaces_acceso
             WHERE curso_id = ? AND activo = 1
             ORDER BY created_at DESC LIMIT 1"
        );
        $eStmt->execute([$curso_id]);
        $tok = $eStmt->fetchColumn();
        if ($tok) {
            $enlaceActual = "http://{$_SERVER['HTTP_HOST']}/Login/pages/acceso.php?token=$tok";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Enlace de Acceso — GestiónAcad</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">
    <header class="dash-header">
        <span class="logo">&#127979; GestiónAcad</span>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                <span class="badge-rol badge-docente">Docente</span></span>
            <a href="perfil.php" class="btn-logout" style="background:#1e3a5f; margin-right:6px;">&#128100; Mi Perfil</a>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <main class="dash-main" style="max-width:620px;">
        <div class="dash-welcome">
            <a href="dashboard_docente.php" style="color:#5c9cf5;font-size:.88rem;text-decoration:none;">
                &#8592; Volver al panel
            </a>
            <h2 style="margin-top:10px;">&#128279; Generar Enlace de Acceso</h2>
            <p>Genera un enlace único para que tus estudiantes se inscriban en un curso.</p>
        </div>

        <?php if (empty($cursos)): ?>
            <div class="section-card">
                <p class="empty-state">No tienes cursos activos.
                    <a href="crear_curso.php" style="color:#5c9cf5;">Crea un curso primero.</a>
                </p>
            </div>

        <?php else: ?>

        <!-- Formulario selector + generar -->
        <div class="section-card">
            <h3>Selecciona el curso</h3>

            <form method="POST" id="form-enlace">
                <div class="field-group">
                    <label class="field-label">Curso <span class="required">*</span></label>
                    <select name="curso_id" required class="select-input"
                            onchange="mostrarEnlaceActual(this.value)">
                        <option value="">— Elige un curso —</option>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?php echo $c['id']; ?>"
                                    <?php echo ($c['id'] == $curso_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nombre']); ?>
                                <?php if ($c['codigo']): ?>(<?php echo htmlspecialchars($c['codigo']); ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="info-box" style="margin-bottom:14px;">
                    <p style="font-size:.83rem; color:#64748b;">
                        &#8226; Al generar un nuevo enlace, el anterior quedará desactivado.<br>
                        &#8226; El estudiante usará el enlace para registrarse e inscribirse automáticamente.<br>
                        &#8226; Los enlaces no tienen fecha de expiración por defecto.
                    </p>
                </div>

                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <button type="submit">&#8635; Generar nuevo enlace</button>
                    <a href="dashboard_docente.php" class="btn-cancel">Cancelar</a>
                </div>
            </form>
        </div>

        <!-- Enlace actual del curso seleccionado (si existe) -->
        <?php if ($enlaceActual): ?>
        <div class="section-card" id="enlace-actual-card">
            <h3>&#128279; Enlace activo actual</h3>
            <p style="font-size:.83rem; color:#64748b; margin-bottom:8px;">
                Este es el enlace vigente para el curso seleccionado:
            </p>
            <div class="link-box" id="enlace-box">
                <?php echo htmlspecialchars($enlaceActual); ?>
            </div>
            <button type="button" onclick="copiarEnlace()" class="btn-primary"
                    style="margin-top:10px; width:auto; padding:8px 18px;">
                &#128203; Copiar enlace
            </button>
        </div>
        <?php endif; ?>

        <!-- Panel de todos los cursos con sus enlaces -->
        <div class="section-card">
            <h3>&#128218; Estado de enlaces por curso</h3>
            <table class="info-table">
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Enlace activo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cursos as $c):
                    $eS = $pdo->prepare(
                        "SELECT token FROM enlaces_acceso
                         WHERE curso_id = ? AND activo = 1
                         ORDER BY created_at DESC LIMIT 1"
                    );
                    $eS->execute([$c['id']]);
                    $t = $eS->fetchColumn();
                    $url = $t ? "http://{$_SERVER['HTTP_HOST']}/Login/pages/acceso.php?token=$t" : null;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['nombre']); ?>
                        <?php if ($c['codigo']): ?>
                            <span style="color:#475569;font-size:.8rem;">(<?php echo htmlspecialchars($c['codigo']); ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($url): ?>
                            <span style="color:#86efac; font-size:.8rem;">&#10003; Activo</span>
                        <?php else: ?>
                            <span style="color:#475569; font-size:.8rem;">Sin enlace</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="curso_id" value="<?php echo $c['id']; ?>">
                            <button type="submit" class="btn-primary"
                                    style="width:auto;padding:5px 12px;font-size:.78rem;">
                                <?php echo $url ? '&#8635; Renovar' : '+ Crear'; ?>
                            </button>
                        </form>
                        <?php if ($url): ?>
                            <button type="button" class="btn-cancel"
                                    style="padding:5px 10px;font-size:.78rem;"
                                    onclick="copiarTexto('<?php echo htmlspecialchars($url, ENT_QUOTES); ?>')">
                                &#128203; Copiar
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </main>
</div>

<script>
function copiarEnlace() {
    const txt = document.getElementById('enlace-box').innerText.trim();
    navigator.clipboard.writeText(txt).then(() => alert('¡Enlace copiado!'));
}

function copiarTexto(txt) {
    navigator.clipboard.writeText(txt).then(() => alert('¡Enlace copiado!'));
}

function mostrarEnlaceActual(cursoId) {
    // Recargar para mostrar el enlace activo del curso seleccionado
    if (cursoId) {
        window.location.href = 'generar_enlace.php?curso_id=' + cursoId;
    }
}
</script>
</body>
</html>
