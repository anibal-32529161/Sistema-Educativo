<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$estudiante_id = $_SESSION['user_id'];
$msg_ok    = "";
$msg_error = "";

// ── Inscribirse en un curso ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscribirse'])) {
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $chk = $pdo->prepare("SELECT id, nombre FROM cursos WHERE id = ? AND activo = 1");
    $chk->execute([$curso_id]);
    $cursoObj = $chk->fetch();

    if ($cursoObj) {
        try {
            $pdo->prepare("INSERT INTO matriculas (estudiante_id, curso_id) VALUES (?, ?)")
                ->execute([$estudiante_id, $curso_id]);
            $msg_ok = "¡Te inscribiste en \"" . htmlspecialchars($cursoObj['nombre']) . "\" exitosamente!";
        } catch (PDOException $e) {
            $msg_error = $e->getCode() == 23000
                ? "Ya estás inscrito en ese curso."
                : "Error al inscribirse. Intenta de nuevo.";
        }
    }
}

// ── Mis cursos inscritos ────────────────────────────────────
$stIns = $pdo->prepare(
    "SELECT c.id, c.nombre, c.codigo,
            m.id AS matricula_id,
            CONCAT(u.nombre, ' ', u.apellido) AS docente,
            COUNT(DISTINCT e.id) AS total_eval,
            CASE WHEN SUM(CASE WHEN cal.nota IS NOT NULL THEN e.porcentaje ELSE 0 END) > 0
                 THEN SUM(CASE WHEN cal.nota IS NOT NULL THEN cal.nota * e.porcentaje ELSE 0 END)
                      / SUM(CASE WHEN cal.nota IS NOT NULL THEN e.porcentaje ELSE 0 END)
                 ELSE NULL END AS promedio
     FROM matriculas m
     INNER JOIN cursos c   ON m.curso_id   = c.id
     INNER JOIN usuarios u ON c.docente_id = u.id
     LEFT  JOIN evaluaciones e  ON e.curso_id = c.id
     LEFT  JOIN calificaciones cal
           ON cal.matricula_id = m.id AND cal.evaluacion_id = e.id
     WHERE m.estudiante_id = ?
     GROUP BY m.id, c.id, u.id
     ORDER BY m.fecha_matricula DESC"
);
$stIns->execute([$estudiante_id]);
$listaCursos = $stIns->fetchAll();

$sumaProm = 0; $cnt = 0;
foreach ($listaCursos as $c) {
    if ($c['promedio'] !== null) { $sumaProm += $c['promedio']; $cnt++; }
}
$promedioGeneral = $cnt > 0 ? number_format($sumaProm / $cnt, 1) : '—';

// ── Cursos disponibles agrupados por docente ────────────────
$stDispo = $pdo->prepare(
    "SELECT u.id AS docente_id, u.nombre AS doc_nombre, u.apellido AS doc_apellido,
            c.id AS curso_id, c.nombre AS curso_nombre, c.codigo, c.descripcion,
            COUNT(DISTINCT m2.id) AS num_inscritos,
            COUNT(DISTINCT e.id)  AS num_eval
     FROM usuarios u
     INNER JOIN cursos c      ON c.docente_id = u.id  AND c.activo = 1
     LEFT  JOIN matriculas m2 ON m2.curso_id  = c.id
     LEFT  JOIN evaluaciones e ON e.curso_id  = c.id
     WHERE u.rol = 'docente' AND u.activo = 1
       AND c.id NOT IN (SELECT curso_id FROM matriculas WHERE estudiante_id = ?)
     GROUP BY u.id, u.nombre, u.apellido, c.id, c.nombre, c.codigo, c.descripcion
     ORDER BY u.apellido, u.nombre, c.nombre"
);
$stDispo->execute([$estudiante_id]);
$listaDispo = $stDispo->fetchAll();

$porDocente = [];
foreach ($listaDispo as $row) {
    $dk = $row['docente_id'];
    if (!isset($porDocente[$dk])) {
        $porDocente[$dk] = ['nombre' => $row['doc_nombre'],
                            'apellido' => $row['doc_apellido'], 'cursos' => []];
    }
    $porDocente[$dk]['cursos'][] = $row;
}

// Fecha en español
$dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$meses = ['enero','febrero','marzo','abril','mayo','junio',
          'julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy   = $dias[date('w')] . ', ' . date('j') . ' de ' . $meses[(int)date('n')-1] . ' de ' . date('Y');

$nombreCorto = htmlspecialchars(explode(' ', $_SESSION['nombre'])[0]);

// Colores de los ciclos de tarjetas de materias
$paleta = ['#059669','#7c3aed','#0ea5e9','#d97706','#db2777','#0284c7'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Portal Académico — GestiónAcad</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper dash-est">

    <!-- ── Header ── -->
    <header class="dash-header">
        <span class="logo">&#127979; GestiónAcad</span>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                <span class="badge-rol badge-estudiante">Estudiante</span>
            </span>
            <a href="perfil.php" class="btn-logout" style="background:#1e3a5f; margin-right:6px;">&#128100; Mi Perfil</a>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <main class="dash-main">

        <!-- ══ HERO ESTUDIANTE ══ -->
        <div class="hero-banner hero-est">
            <div class="hero-deco he-d1"></div>
            <div class="hero-deco he-d2"></div>
            <div class="hero-deco he-d3"></div>

            <div class="hero-content">
                <span class="hero-tag">&#127891; Portal Estudiantil &middot; UNERG</span>
                <h1 class="hero-title">
                    &#161;Hola,<br>
                    <span class="hero-name-est"><?php echo $nombreCorto; ?>!</span>
                </h1>
                <p class="hero-date">&#128197; <?php echo $hoy; ?></p>

                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-num"><?php echo count($listaCursos); ?></span>
                        <span class="hero-stat-label">Materias</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <span class="hero-stat-num"><?php echo $promedioGeneral; ?></span>
                        <span class="hero-stat-label">Promedio</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <span class="hero-stat-num"><?php echo count($listaDispo); ?></span>
                        <span class="hero-stat-label">Disponibles</span>
                    </div>
                </div>
            </div>

            <div class="hero-icons">
                <div class="hero-icon-bubble he-b1">&#127891;</div>
                <div class="hero-icon-bubble he-b2">&#9997;&#65039;</div>
                <div class="hero-icon-bubble he-b3">&#11088;</div>
                <div class="hero-icon-bubble he-b4">&#127942;</div>
                <div class="hero-icon-bubble he-b5">&#128200;</div>
            </div>
        </div>

        <!-- Alertas -->
        <?php if ($msg_ok): ?>
            <div class="alert-success" style="margin-bottom:20px;">&#10003; <?php echo $msg_ok; ?></div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
            <div class="alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($msg_error); ?></div>
        <?php endif; ?>

        <!-- ══ MIS MATERIAS ══ -->
        <div class="est-section-title">
            <span class="est-section-icon" style="background:linear-gradient(135deg,#059669,#0d9488);">&#128218;</span>
            <h2>Mis Materias</h2>
        </div>

        <?php if (count($listaCursos) > 0): ?>
        <div class="materia-cards">
            <?php foreach ($listaCursos as $i => $c):
                $prom  = $c['promedio'] !== null ? (float)$c['promedio'] : null;
                $promFmt = $prom !== null ? number_format($prom, 1) : '—';
                $pct   = $prom !== null ? min(100, round($prom / 20 * 100)) : 0;
                $acento = $paleta[$i % count($paleta)];
                if ($prom === null)      $barColor = '#475569';
                elseif ($prom >= 15)     $barColor = '#10b981';
                elseif ($prom >= 10)     $barColor = '#f59e0b';
                else                     $barColor = '#ef4444';
                $badgeClass = $prom === null ? '' : ($prom >= 10 ? 'badge-aprobado' : 'badge-reprobado');
            ?>
            <div class="materia-card" style="--acento:<?php echo $acento; ?>;">
                <div class="mc-left">
                    <div class="mc-color-bar" style="background:<?php echo $acento; ?>;"></div>
                    <div class="mc-body">
                        <div class="mc-nombre"><?php echo htmlspecialchars($c['nombre']); ?></div>
                        <div class="mc-meta">
                            <?php if ($c['codigo']): ?>
                                <span class="code-badge"><?php echo htmlspecialchars($c['codigo']); ?></span>
                            <?php endif; ?>
                            <span class="mc-docente">&#128203; <?php echo htmlspecialchars($c['docente']); ?></span>
                        </div>
                        <!-- Barra de progreso -->
                        <div class="mc-progress-wrap">
                            <div class="mc-progress-bar">
                                <div class="mc-progress-fill"
                                     style="width:<?php echo $pct; ?>%; background:<?php echo $barColor; ?>;"></div>
                            </div>
                            <span class="mc-pct-txt" style="color:<?php echo $barColor; ?>;">
                                <?php echo $pct; ?>%
                            </span>
                        </div>
                        <div class="mc-eval-info">
                            &#128221; <?php echo $c['total_eval']; ?> evaluación(es)
                        </div>
                    </div>
                </div>
                <div class="mc-right">
                    <div class="mc-nota-badge <?php echo $badgeClass; ?>">
                        <?php echo $promFmt; ?>
                    </div>
                    <div class="mc-nota-label">/ 20 pts</div>
                    <a href="ver_notas_estudiante.php?curso_id=<?php echo $c['id']; ?>"
                       class="mc-ver-notas">Ver notas &#8594;</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="est-empty-state">
            <div class="est-empty-icon">&#128218;</div>
            <p class="est-empty-title">Sin materias inscritas</p>
            <p class="est-empty-sub">Explora los cursos disponibles abajo e inscríbete.</p>
        </div>
        <?php endif; ?>

        <!-- ══ CURSOS DISPONIBLES ══ -->
        <div class="est-section-title" style="margin-top:36px;">
            <span class="est-section-icon" style="background:linear-gradient(135deg,#7c3aed,#db2777);">&#128269;</span>
            <h2>Cursos Disponibles</h2>
        </div>

        <?php if (!empty($porDocente)): ?>
        <div class="buscador-wrap no-print">
            <input type="text" id="buscador-cursos" class="buscador-input"
                   placeholder="&#128269; Buscar por nombre, código o docente..."
                   oninput="filtrarCursos(this.value)">
        </div>
        <p id="sin-resultados" class="buscador-sin-res">Sin resultados para tu búsqueda.</p>
        <?php endif; ?>

        <?php if (empty($porDocente)): ?>
        <div class="est-empty-state">
            <div class="est-empty-icon">&#127979;</div>
            <p class="est-empty-title">Sin cursos disponibles</p>
            <p class="est-empty-sub">Cuando un docente publique un curso, aparecerá aquí.</p>
        </div>

        <?php else:
            $gradientes = [
                'linear-gradient(135deg,#0f4c2a,#059669)',
                'linear-gradient(135deg,#2d1b6e,#7c3aed)',
                'linear-gradient(135deg,#0c3053,#0ea5e9)',
                'linear-gradient(135deg,#78350f,#d97706)',
                'linear-gradient(135deg,#831843,#db2777)',
            ];
            $gi = 0;
            foreach ($porDocente as $dk => $doc): ?>

            <div class="prof-bloque"
                 data-search="<?php echo htmlspecialchars(mb_strtolower($doc['nombre'] . ' ' . $doc['apellido'])); ?>"
                 data-cursos="<?php echo htmlspecialchars(mb_strtolower(implode(' ', array_column($doc['cursos'], 'curso_nombre')) . ' ' . implode(' ', array_column($doc['cursos'], 'codigo')))); ?>">
                <div class="prof-header">
                    <div class="prof-avatar">
                        <?php echo mb_strtoupper(mb_substr($doc['nombre'], 0, 1)); ?>
                    </div>
                    <div class="prof-info">
                        <span class="prof-nombre">
                            Prof. <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                        </span>
                        <span class="prof-count">
                            <?php echo count($doc['cursos']); ?> curso(s) disponible(s)
                        </span>
                    </div>
                </div>

                <div class="cursos-est-grid">
                    <?php foreach ($doc['cursos'] as $cur):
                        $grad = $gradientes[$gi % count($gradientes)];
                        $gi++;
                    ?>
                    <div class="curso-est-card">
                        <div class="cec-header" style="background:<?php echo $grad; ?>;">
                            <div class="cec-header-icon">&#128218;</div>
                            <div class="cec-header-text">
                                <span class="cec-nombre">
                                    <?php echo htmlspecialchars($cur['curso_nombre']); ?>
                                </span>
                                <?php if ($cur['codigo']): ?>
                                    <span class="cec-codigo">
                                        <?php echo htmlspecialchars($cur['codigo']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="cec-body">
                            <?php if ($cur['descripcion']): ?>
                                <p class="cec-desc">
                                    <?php echo htmlspecialchars(mb_strimwidth($cur['descripcion'], 0, 85, '…')); ?>
                                </p>
                            <?php endif; ?>

                            <div class="cec-chips">
                                <span class="cec-chip">
                                    &#127891; <?php echo $cur['num_inscritos']; ?> inscritos
                                </span>
                                <span class="cec-chip">
                                    &#128221; <?php echo $cur['num_eval']; ?> evaluaciones
                                </span>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="inscribirse" value="1">
                                <input type="hidden" name="curso_id" value="<?php echo $cur['curso_id']; ?>">
                                <button type="submit" class="btn-inscribir-est">
                                    &#43; Inscribirme
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php endforeach; endif; ?>

    </main>
</div>
<script>
function filtrarCursos(q) {
    q = q.toLowerCase().trim();
    var bloques = document.querySelectorAll('.prof-bloque');
    var visible = 0;
    bloques.forEach(function(b) {
        var haystack = (b.dataset.search || '') + ' ' + (b.dataset.cursos || '');
        var mostrar = q === '' || haystack.indexOf(q) !== -1;
        b.style.display = mostrar ? '' : 'none';
        if (mostrar) visible++;
    });
    var sinRes = document.getElementById('sin-resultados');
    if (sinRes) sinRes.style.display = (q !== '' && visible === 0) ? 'block' : 'none';
}
</script>
</body>
</html>
