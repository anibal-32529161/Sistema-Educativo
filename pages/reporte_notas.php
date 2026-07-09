<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$rol        = $_SESSION['rol'];
$user_id    = $_SESSION['user_id'];
$curso_id   = (int)($_GET['curso_id'] ?? 0);
$solo_propio = isset($_GET['estudiante']) && $_GET['estudiante'] === 'propio';

// ── Verificar acceso al curso ───────────────────────────────
if ($rol === 'docente') {
    $stCurso = $pdo->prepare("SELECT * FROM cursos WHERE id = ? AND docente_id = ? AND activo = 1");
    $stCurso->execute([$curso_id, $user_id]);
} elseif ($rol === 'estudiante') {
    $stCurso = $pdo->prepare(
        "SELECT c.* FROM cursos c
         INNER JOIN matriculas m ON m.curso_id = c.id
         WHERE c.id = ? AND m.estudiante_id = ?"
    );
    $stCurso->execute([$curso_id, $user_id]);
    $solo_propio = true;
} else {
    header("Location: login.php");
    exit();
}

$curso = $stCurso->fetch();
if (!$curso) {
    header("Location: " . ($rol === 'docente' ? 'dashboard_docente.php' : 'dashboard_estudiante.php'));
    exit();
}

// ── Docente del curso ───────────────────────────────────────
$stDoc = $pdo->prepare("SELECT nombre, apellido FROM usuarios WHERE id = ?");
$stDoc->execute([$curso['docente_id']]);
$docente = $stDoc->fetch();

// ── Evaluaciones del curso ──────────────────────────────────
$stEval = $pdo->prepare(
    "SELECT id, nombre, porcentaje FROM evaluaciones WHERE curso_id = ? ORDER BY fecha ASC, id ASC"
);
$stEval->execute([$curso_id]);
$evaluaciones = $stEval->fetchAll();

// ── Estudiantes y sus notas ─────────────────────────────────
if ($solo_propio) {
    $stEst = $pdo->prepare(
        "SELECT m.id AS mat_id, u.nombre, u.apellido, u.cedula
         FROM matriculas m INNER JOIN usuarios u ON m.estudiante_id = u.id
         WHERE m.curso_id = ? AND m.estudiante_id = ?"
    );
    $stEst->execute([$curso_id, $user_id]);
} else {
    $stEst = $pdo->prepare(
        "SELECT m.id AS mat_id, u.nombre, u.apellido, u.cedula
         FROM matriculas m INNER JOIN usuarios u ON m.estudiante_id = u.id
         WHERE m.curso_id = ?
         ORDER BY u.apellido, u.nombre"
    );
    $stEst->execute([$curso_id]);
}
$estudiantes = $stEst->fetchAll();

// ── Notas de todos los estudiantes ─────────────────────────
$stNotas = $pdo->prepare(
    "SELECT cal.matricula_id, cal.evaluacion_id, cal.nota
     FROM calificaciones cal
     INNER JOIN matriculas m ON cal.matricula_id = m.id
     WHERE m.curso_id = ?"
);
$stNotas->execute([$curso_id]);
$notasMap = [];
foreach ($stNotas->fetchAll() as $row) {
    $notasMap[$row['matricula_id']][$row['evaluacion_id']] = $row['nota'];
}

$fechaEmision = date('d/m/Y');
$meses = ['enero','febrero','marzo','abril','mayo','junio',
          'julio','agosto','septiembre','octubre','noviembre','diciembre'];
$fechaLarga = date('j') . ' de ' . $meses[(int)date('n')-1] . ' de ' . date('Y');

$dashLink = $rol === 'docente' ? 'ver_curso.php?id=' . $curso_id : 'ver_notas_estudiante.php?curso_id=' . $curso_id;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Notas — <?php echo htmlspecialchars($curso['nombre']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">

    <header class="dash-header no-print">
        <span class="logo">&#127979; GestiónAcad</span>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                <span class="badge-rol badge-<?php echo $rol; ?>"><?php echo ucfirst($rol); ?></span>
            </span>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <main class="dash-main">

        <div class="no-print" style="margin-bottom:16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
            <a href="<?php echo $dashLink; ?>" style="color:#5c9cf5; font-size:.88rem; text-decoration:none;">
                &#8592; Volver
            </a>
            <button class="btn-print" onclick="window.print()">
                &#128438; Imprimir / Guardar PDF
            </button>
        </div>

        <!-- Encabezado del reporte -->
        <div class="reporte-header">
            <p class="reporte-title">&#128203; Reporte de Calificaciones</p>
            <div class="reporte-meta">
                <span>&#127979; UNERG</span>
                <span>&#128218; <?php echo htmlspecialchars($curso['nombre']); ?><?php if ($curso['codigo']): ?> (<?php echo htmlspecialchars($curso['codigo']); ?>)<?php endif; ?></span>
                <span>&#128203; Prof. <?php echo htmlspecialchars($docente['nombre'] . ' ' . $docente['apellido']); ?></span>
                <span>&#128197; Emitido: <?php echo $fechaLarga; ?></span>
            </div>
        </div>

        <!-- Tabla de notas -->
        <div class="section-card">
            <?php if (count($evaluaciones) === 0): ?>
            <p style="color:#64748b; text-align:center; padding:20px;">Sin evaluaciones registradas en este curso.</p>
            <?php elseif (count($estudiantes) === 0): ?>
            <p style="color:#64748b; text-align:center; padding:20px;">Sin estudiantes inscritos.</p>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="info-table" style="min-width:600px;">
                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Estudiante</th>
                        <?php foreach ($evaluaciones as $ev): ?>
                        <th title="<?php echo htmlspecialchars($ev['nombre']); ?>">
                            <?php echo htmlspecialchars(mb_strimwidth($ev['nombre'], 0, 14, '…')); ?>
                            <br><small style="font-weight:400; color:#64748b;"><?php echo number_format($ev['porcentaje'], 0); ?>%</small>
                        </th>
                        <?php endforeach; ?>
                        <th>Promedio</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estudiantes as $est):
                        $sumaContrib = 0; $sumaPct = 0;
                        foreach ($evaluaciones as $ev) {
                            $nota = $notasMap[$est['mat_id']][$ev['id']] ?? null;
                            if ($nota !== null) {
                                $sumaContrib += (float)$nota * (float)$ev['porcentaje'] / 100;
                                $sumaPct     += (float)$ev['porcentaje'];
                            }
                        }
                        $prom    = $sumaPct > 0 ? $sumaContrib * 100 / $sumaPct : null;
                        $promFmt = $prom !== null ? number_format($prom, 2) : '—';
                        $aprobado = $prom !== null && $prom >= 10;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($est['cedula']); ?></td>
                        <td style="font-weight:500;">
                            <?php echo htmlspecialchars($est['apellido'] . ', ' . $est['nombre']); ?>
                        </td>
                        <?php foreach ($evaluaciones as $ev):
                            $nota = $notasMap[$est['mat_id']][$ev['id']] ?? null;
                            $notaFmt = $nota !== null ? number_format((float)$nota, 1) : '—';
                            $color = $nota === null ? '#475569' : ($nota >= 10 ? '#10b981' : '#ef4444');
                        ?>
                        <td style="text-align:center; color:<?php echo $color; ?>; font-weight:600;">
                            <?php echo $notaFmt; ?>
                        </td>
                        <?php endforeach; ?>
                        <td style="text-align:center; font-weight:800;
                                   color:<?php echo $prom === null ? '#475569' : ($aprobado ? '#10b981' : '#ef4444'); ?>;">
                            <?php echo $promFmt; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($prom === null): ?>
                                <span style="color:#475569; font-size:.8rem;">Pendiente</span>
                            <?php elseif ($aprobado): ?>
                                <span style="color:#10b981; font-weight:600;">&#10003; Aprobado</span>
                            <?php else: ?>
                                <span style="color:#ef4444; font-weight:600;">&#10005; Reprobado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <!-- Firma -->
            <div class="firma-bloque">
                <p style="color:#94a3b8; font-size:.82rem; margin-bottom:32px;">
                    Documento generado el <?php echo $fechaLarga; ?> por GestiónAcad — UNERG
                </p>
                <div style="display:flex; gap:60px; flex-wrap:wrap;">
                    <div>
                        <div style="border-top:1px solid #475569; padding-top:8px; width:200px; color:#94a3b8; font-size:.82rem;">
                            Prof. <?php echo htmlspecialchars($docente['nombre'] . ' ' . $docente['apellido']); ?><br>
                            Firma del Docente
                        </div>
                    </div>
                    <div>
                        <div style="border-top:1px solid #475569; padding-top:8px; width:200px; color:#94a3b8; font-size:.82rem;">
                            Sello / Visto Bueno
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>
</body>
</html>
