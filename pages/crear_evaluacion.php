<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'docente') {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$docente_id = $_SESSION['user_id'];
$error      = "";

// Cursos del docente
$stmtCursos = $pdo->prepare(
    "SELECT id, nombre, codigo FROM cursos WHERE docente_id = ? AND activo = 1 ORDER BY nombre"
);
$stmtCursos->execute([$docente_id]);
$cursos = $stmtCursos->fetchAll();

// Curso preseleccionado (puede venir desde ver_curso.php)
$curso_id_pre = (int)($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);

// Porcentaje ya usado en el curso seleccionado
$pctUsado = 0;
$curso_sel = null;
if ($curso_id_pre) {
    $chk = $pdo->prepare("SELECT * FROM cursos WHERE id = ? AND docente_id = ?");
    $chk->execute([$curso_id_pre, $docente_id]);
    $curso_sel = $chk->fetch();

    if ($curso_sel) {
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(porcentaje),0) FROM evaluaciones WHERE curso_id = ?");
        $sumStmt->execute([$curso_id_pre]);
        $pctUsado = (float)$sumStmt->fetchColumn();
    }
}

// ── PROCESAR FORMULARIO ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $curso_id    = (int)($_POST['curso_id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '') ?: null;
    $porcentaje  = (float)($_POST['porcentaje'] ?? 0);
    $fecha       = $_POST['fecha'] ?? null;

    // Validar que el curso le pertenece
    $chk = $pdo->prepare("SELECT id FROM cursos WHERE id = ? AND docente_id = ?");
    $chk->execute([$curso_id, $docente_id]);

    if (!$chk->fetch()) {
        $error = "Curso no válido.";
    } elseif ($nombre === '') {
        $error = "El nombre de la evaluación es obligatorio.";
    } elseif ($porcentaje <= 0 || $porcentaje > 100) {
        $error = "El porcentaje debe estar entre 1 y 100.";
    } else {
        // Verificar que no se supere 100%
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(porcentaje),0) FROM evaluaciones WHERE curso_id = ?");
        $sumStmt->execute([$curso_id]);
        $sumaActual = (float)$sumStmt->fetchColumn();

        if ($sumaActual + $porcentaje > 100) {
            $disponible = 100 - $sumaActual;
            $error = "La suma supera el 100%. Porcentaje disponible: {$disponible}%.";
            $curso_id_pre = $curso_id;
            $pctUsado = $sumaActual;
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO evaluaciones (curso_id, nombre, descripcion, porcentaje, fecha)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $ins->execute([$curso_id, $nombre, $descripcion, $porcentaje, $fecha ?: null]);
            header("Location: ver_curso.php?id=$curso_id");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Evaluación — GestiónAcad</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">
    <header class="dash-header">
        <span class="logo">&#127979; GestiónAcad</span>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                <span class="badge-rol badge-docente">Docente</span></span>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <main class="dash-main" style="max-width:620px;">
        <div class="dash-welcome">
            <?php if ($curso_sel): ?>
                <a href="ver_curso.php?id=<?php echo $curso_sel['id']; ?>"
                   style="color:#5c9cf5;font-size:.88rem;text-decoration:none;">
                    &#8592; Volver al curso
                </a>
            <?php else: ?>
                <a href="dashboard_docente.php" style="color:#5c9cf5;font-size:.88rem;text-decoration:none;">
                    &#8592; Volver al panel
                </a>
            <?php endif; ?>
            <h2 style="margin-top:10px;">&#128221; Nueva Evaluación</h2>
            <p>Crea un examen, taller o actividad y asígnale su peso en la nota final.</p>
        </div>

        <div class="section-card">

            <?php if ($error): ?>
                <div class="alert-error" style="margin-bottom:16px;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="guardar" value="1">

                <div class="field-group">
                    <label class="field-label">Curso <span class="required">*</span></label>
                    <select name="curso_id" id="sel-curso" required class="select-input"
                            onchange="actualizarPct(this)">
                        <option value="">— Selecciona un curso —</option>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?php echo $c['id']; ?>"
                                    data-pct="<?php
                                        // Consultar porcentaje usado para cada curso
                                        $sp = $pdo->prepare("SELECT COALESCE(SUM(porcentaje),0) FROM evaluaciones WHERE curso_id=?");
                                        $sp->execute([$c['id']]);
                                        echo (float)$sp->fetchColumn();
                                    ?>"
                                    <?php echo ($c['id'] == $curso_id_pre) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nombre']); ?>
                                <?php if ($c['codigo']): ?>(<?php echo htmlspecialchars($c['codigo']); ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Barra de porcentaje disponible -->
                <div id="pct-info" style="margin-bottom:14px; <?php echo !$curso_id_pre ? 'display:none;' : ''; ?>">
                    <div style="display:flex; justify-content:space-between; font-size:.8rem; color:#64748b; margin-bottom:4px;">
                        <span>Porcentaje ya asignado:</span>
                        <span id="pct-texto"><?php echo number_format($pctUsado,1); ?>% / 100%</span>
                    </div>
                    <div style="background:#1e293b; border-radius:6px; height:8px; overflow:hidden; border:1px solid #334155;">
                        <div id="pct-barra" style="height:100%; background:#1d4ed8; border-radius:6px;
                             width:<?php echo $pctUsado; ?>%; transition:width .3s;"></div>
                    </div>
                    <p id="pct-disponible" style="font-size:.8rem; color:#94a3b8; margin-top:4px;">
                        Disponible: <strong><?php echo number_format(100 - $pctUsado, 1); ?>%</strong>
                    </p>
                </div>

                <div class="field-group">
                    <label class="field-label">Nombre de la evaluación <span class="required">*</span></label>
                    <input type="text" name="nombre"
                           placeholder="Ej: Primer Parcial, Trabajo Práctico N°1, Examen Final"
                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                           maxlength="150" required>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="field-group">
                        <label class="field-label">Porcentaje (%) <span class="required">*</span></label>
                        <input type="number" name="porcentaje"
                               placeholder="Ej: 30"
                               min="1" max="100" step="0.5"
                               value="<?php echo htmlspecialchars($_POST['porcentaje'] ?? ''); ?>"
                               required>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Fecha <span class="field-hint">(opcional)</span></label>
                        <input type="date" name="fecha"
                               value="<?php echo htmlspecialchars($_POST['fecha'] ?? ''); ?>">
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label">Descripción <span class="field-hint">(opcional)</span></label>
                    <textarea name="descripcion" rows="3"
                              placeholder="Ej: Cubre los temas 1 al 4, incluye resolución de problemas..."><?php
                        echo htmlspecialchars($_POST['descripcion'] ?? '');
                    ?></textarea>
                </div>

                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit">&#10003; Guardar Evaluación</button>
                    <?php if ($curso_sel): ?>
                        <a href="ver_curso.php?id=<?php echo $curso_sel['id']; ?>" class="btn-cancel">Cancelar</a>
                    <?php else: ?>
                        <a href="dashboard_docente.php" class="btn-cancel">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
// Datos de porcentaje por curso (embebidos desde PHP)
const pctData = {};
document.querySelectorAll('#sel-curso option[data-pct]').forEach(opt => {
    pctData[opt.value] = parseFloat(opt.dataset.pct) || 0;
});

function actualizarPct(sel) {
    const id = sel.value;
    const info = document.getElementById('pct-info');
    if (!id) { info.style.display = 'none'; return; }

    const usado = pctData[id] || 0;
    const disp  = 100 - usado;

    info.style.display = '';
    document.getElementById('pct-barra').style.width = usado + '%';
    document.getElementById('pct-barra').style.background = usado >= 90 ? '#dc2626' : '#1d4ed8';
    document.getElementById('pct-texto').textContent = usado.toFixed(1) + '% / 100%';
    document.getElementById('pct-disponible').innerHTML =
        'Disponible: <strong>' + disp.toFixed(1) + '%</strong>';
}

// Ejecutar al cargar si ya hay un curso seleccionado
const selCurso = document.getElementById('sel-curso');
if (selCurso.value) actualizarPct(selCurso);
</script>
</body>
</html>
