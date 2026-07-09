<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$estudiante_id = $_SESSION['user_id'];
$curso_id      = (int)($_GET['curso_id'] ?? 0);

// Verificar que el estudiante está matriculado
$stMat = $pdo->prepare(
    "SELECT m.id AS matricula_id, c.nombre AS curso_nombre, c.codigo, c.descripcion,
            CONCAT(u.nombre, ' ', u.apellido) AS docente
     FROM matriculas m
     INNER JOIN cursos   c ON m.curso_id   = c.id
     INNER JOIN usuarios u ON c.docente_id = u.id
     WHERE m.estudiante_id = ? AND m.curso_id = ?"
);
$stMat->execute([$estudiante_id, $curso_id]);
$info = $stMat->fetch();

if (!$info) {
    header("Location: dashboard_estudiante.php");
    exit();
}

$matricula_id = $info['matricula_id'];

// Evaluaciones del curso
$stEval = $pdo->prepare(
    "SELECT e.id, e.nombre, e.porcentaje, e.fecha, e.descripcion,
            cal.nota, cal.observacion
     FROM evaluaciones e
     LEFT JOIN calificaciones cal
           ON cal.evaluacion_id = e.id AND cal.matricula_id = ?
     WHERE e.curso_id = ?
     ORDER BY e.fecha ASC, e.id ASC"
);
$stEval->execute([$matricula_id, $curso_id]);
$evaluaciones = $stEval->fetchAll();

// Calcular promedio ponderado (igual que el docente)
$sumaContrib = 0;
$sumaPct     = 0;
foreach ($evaluaciones as $ev) {
    if ($ev['nota'] !== null) {
        $sumaContrib += $ev['nota'] * $ev['porcentaje'] / 100;
        $sumaPct     += $ev['porcentaje'];
    }
}
$promedio    = $sumaPct > 0 ? $sumaContrib * 100 / $sumaPct : null;
$promedioFmt = $promedio !== null ? number_format($promedio, 2) : '—';
$aprobado    = $promedio !== null && $promedio >= 10;

$paleta = ['#059669','#7c3aed','#0ea5e9','#d97706','#db2777','#0284c7'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($info['curso_nombre']); ?> — Mis Notas</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .vn-back { color:#5c9cf5; font-size:.88rem; text-decoration:none; display:inline-flex; align-items:center; gap:6px; margin-bottom:16px; }
        .vn-back:hover { color:#93c5fd; }

        .vn-header {
            background: linear-gradient(135deg,#0f172a,#1e293b);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .vn-curso-nombre { font-size: 1.35rem; font-weight: 700; color: #f8fafc; margin: 0 0 4px; }
        .vn-meta { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 6px; }
        .vn-docente { font-size: .85rem; color: #94a3b8; }

        .vn-promedio-box {
            text-align: center;
            min-width: 110px;
        }
        .vn-promedio-num {
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1;
        }
        .vn-promedio-num.aprobado { color: #10b981; }
        .vn-promedio-num.reprobado { color: #ef4444; }
        .vn-promedio-num.pendiente { color: #64748b; }
        .vn-promedio-label { font-size: .75rem; color: #64748b; margin-top: 4px; }
        .vn-promedio-status {
            font-size: .72rem;
            border-radius: 20px;
            padding: 3px 10px;
            margin-top: 6px;
            display: inline-block;
            font-weight: 600;
        }
        .vn-promedio-status.aprobado { background:#052e16; color:#4ade80; }
        .vn-promedio-status.reprobado { background:#450a0a; color:#f87171; }

        .vn-eval-list { display: flex; flex-direction: column; gap: 12px; }

        .vn-eval-card {
            background: rgba(15,23,42,.7);
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 14px;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .vn-eval-color {
            width: 6px;
            border-radius: 3px;
            align-self: stretch;
            flex-shrink: 0;
            min-height: 40px;
        }
        .vn-eval-body { flex: 1; min-width: 160px; }
        .vn-eval-nombre { font-size: 1rem; font-weight: 600; color: #e2e8f0; margin: 0 0 4px; }
        .vn-eval-sub { font-size: .8rem; color: #64748b; }
        .vn-eval-fecha { font-size: .78rem; color: #475569; margin-top: 3px; }
        .vn-eval-obs { font-size: .78rem; color: #94a3b8; margin-top: 4px; font-style: italic; }

        .vn-eval-pct {
            text-align: center;
            min-width: 60px;
        }
        .vn-eval-pct-num { font-size: 1.1rem; font-weight: 700; color: #f8fafc; }
        .vn-eval-pct-label { font-size: .72rem; color: #475569; }

        .vn-eval-nota {
            text-align: center;
            min-width: 80px;
        }
        .vn-eval-nota-num {
            font-size: 1.5rem;
            font-weight: 800;
        }
        .vn-eval-nota-num.nota-ok   { color: #10b981; }
        .vn-eval-nota-num.nota-mal  { color: #ef4444; }
        .vn-eval-nota-num.nota-pend { color: #475569; }
        .vn-eval-nota-label { font-size: .72rem; color: #475569; }

        .vn-eval-contrib {
            text-align: center;
            min-width: 70px;
            background: rgba(255,255,255,.04);
            border-radius: 10px;
            padding: 8px 12px;
        }
        .vn-eval-contrib-num { font-size: 1rem; font-weight: 700; color: #c4b5fd; }
        .vn-eval-contrib-label { font-size: .68rem; color: #6b7280; }

        .vn-summary {
            background: rgba(5,150,105,.08);
            border: 1px solid rgba(5,150,105,.2);
            border-radius: 14px;
            padding: 20px 24px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .vn-summary.reprobado-bg {
            background: rgba(239,68,68,.08);
            border-color: rgba(239,68,68,.2);
        }
        .vn-summary-left { font-size: .9rem; color: #94a3b8; }
        .vn-summary-left strong { color: #f8fafc; font-size: 1rem; display: block; margin-top: 2px; }
        .vn-summary-right { font-size: 2rem; font-weight: 800; }
        .vn-summary-right.aprobado { color: #10b981; }
        .vn-summary-right.reprobado { color: #ef4444; }
        .vn-summary-right.pendiente { color: #475569; }

        .vn-pendiente-chip {
            display: inline-block;
            background: rgba(71,85,105,.4);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: .75rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>
<div class="dash-wrapper dash-est">

    <header class="dash-header">
        <span class="logo">&#127979; GestiónAcad</span>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                <span class="badge-rol badge-estudiante">Estudiante</span>
            </span>
            <a href="cambiar_contrasena.php" class="btn-logout" style="background:#1e3a5f; margin-right:6px;">&#128274; Contraseña</a>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <main class="dash-main">

        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
            <a href="dashboard_estudiante.php" class="vn-back" style="margin-bottom:0;">&#8592; Volver a mi portal</a>
            <a href="reporte_notas.php?curso_id=<?php echo $curso_id; ?>&estudiante=propio" class="btn-print" style="font-size:.82rem; padding:8px 16px;">
                &#128438; Ver Reporte / Imprimir
            </a>
        </div>

        <!-- ── Encabezado del curso ── -->
        <div class="vn-header">
            <div>
                <p class="vn-curso-nombre"><?php echo htmlspecialchars($info['curso_nombre']); ?></p>
                <div class="vn-meta">
                    <?php if ($info['codigo']): ?>
                        <span class="code-badge"><?php echo htmlspecialchars($info['codigo']); ?></span>
                    <?php endif; ?>
                    <span class="vn-docente">&#128203; Prof. <?php echo htmlspecialchars($info['docente']); ?></span>
                </div>
            </div>
            <div class="vn-promedio-box">
                <?php
                $cls = $promedio === null ? 'pendiente' : ($aprobado ? 'aprobado' : 'reprobado');
                ?>
                <div class="vn-promedio-num <?php echo $cls; ?>"><?php echo $promedioFmt; ?></div>
                <div class="vn-promedio-label">/ 20 pts</div>
                <?php if ($promedio !== null): ?>
                <span class="vn-promedio-status <?php echo $cls; ?>">
                    <?php echo $aprobado ? '&#9989; Aprobado' : '&#10060; Reprobado'; ?>
                </span>
                <?php else: ?>
                <span class="vn-promedio-status" style="background:#1e293b; color:#64748b;">Pendiente</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Lista de evaluaciones ── -->
        <?php if (count($evaluaciones) === 0): ?>
        <div class="est-empty-state">
            <div class="est-empty-icon">&#128221;</div>
            <p class="est-empty-title">Sin evaluaciones</p>
            <p class="est-empty-sub">El docente aún no ha creado evaluaciones para este curso.</p>
        </div>

        <?php else: ?>
        <div class="vn-eval-list">
            <?php foreach ($evaluaciones as $i => $ev):
                $color    = $paleta[$i % count($paleta)];
                $nota     = $ev['nota'] !== null ? (float)$ev['nota'] : null;
                $contrib  = $nota !== null ? $nota * $ev['porcentaje'] / 100 : null;
                $notaFmt  = $nota !== null ? number_format($nota, 1) : null;
                $contFmt  = $contrib !== null ? number_format($contrib, 2) : null;
                $notaCls  = $nota === null ? 'nota-pend' : ($nota >= 10 ? 'nota-ok' : 'nota-mal');
            ?>
            <div class="vn-eval-card">
                <div class="vn-eval-color" style="background:<?php echo $color; ?>;"></div>

                <div class="vn-eval-body">
                    <p class="vn-eval-nombre"><?php echo htmlspecialchars($ev['nombre']); ?></p>
                    <?php if ($ev['descripcion']): ?>
                        <p class="vn-eval-sub"><?php echo htmlspecialchars($ev['descripcion']); ?></p>
                    <?php endif; ?>
                    <?php if ($ev['fecha']): ?>
                        <p class="vn-eval-fecha">&#128197; <?php echo date('d/m/Y', strtotime($ev['fecha'])); ?></p>
                    <?php endif; ?>
                    <?php if ($ev['observacion']): ?>
                        <p class="vn-eval-obs">&#128172; <?php echo htmlspecialchars($ev['observacion']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="vn-eval-pct">
                    <div class="vn-eval-pct-num"><?php echo number_format($ev['porcentaje'], 0); ?>%</div>
                    <div class="vn-eval-pct-label">peso</div>
                </div>

                <div class="vn-eval-nota">
                    <div class="vn-eval-nota-num <?php echo $notaCls; ?>">
                        <?php echo $notaFmt ?? '—'; ?>
                    </div>
                    <div class="vn-eval-nota-label">/ 20 pts</div>
                </div>

                <div class="vn-eval-contrib">
                    <?php if ($contFmt !== null): ?>
                        <div class="vn-eval-contrib-num"><?php echo $contFmt; ?></div>
                        <div class="vn-eval-contrib-label">contribución</div>
                    <?php else: ?>
                        <span class="vn-pendiente-chip">Pendiente</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Resumen final ── -->
        <div class="vn-summary <?php echo $promedio !== null && !$aprobado ? 'reprobado-bg' : ''; ?>">
            <div class="vn-summary-left">
                Promedio ponderado actual
                <strong>
                    <?php
                    $pendientes = count(array_filter($evaluaciones, fn($e) => $e['nota'] === null));
                    if ($pendientes > 0) echo "$pendientes evaluación(es) pendiente(s) de calificación";
                    else echo "Todas las evaluaciones calificadas";
                    ?>
                </strong>
            </div>
            <div class="vn-summary-right <?php echo $cls; ?>">
                <?php echo $promedioFmt; ?> <span style="font-size:1rem; font-weight:400; color:#475569;">/ 20</span>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
