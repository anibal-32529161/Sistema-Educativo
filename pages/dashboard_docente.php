<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'docente') {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$docente_id = $_SESSION['user_id'];

$totalCursos = $pdo->prepare("SELECT COUNT(*) FROM cursos WHERE docente_id = ? AND activo = 1");
$totalCursos->execute([$docente_id]);
$numCursos = $totalCursos->fetchColumn();

$totalEstudiantes = $pdo->prepare(
    "SELECT COUNT(DISTINCT m.estudiante_id)
     FROM matriculas m
     INNER JOIN cursos c ON m.curso_id = c.id
     WHERE c.docente_id = ?"
);
$totalEstudiantes->execute([$docente_id]);
$numEstudiantes = $totalEstudiantes->fetchColumn();

$totalEvaluaciones = $pdo->prepare(
    "SELECT COUNT(*)
     FROM evaluaciones e
     INNER JOIN cursos c ON e.curso_id = c.id
     WHERE c.docente_id = ?"
);
$totalEvaluaciones->execute([$docente_id]);
$numEvaluaciones = $totalEvaluaciones->fetchColumn();

$cursos = $pdo->prepare(
    "SELECT c.id, c.nombre, c.codigo,
            COUNT(DISTINCT m.estudiante_id) AS num_estudiantes,
            COUNT(DISTINCT e.id) AS num_evaluaciones
     FROM cursos c
     LEFT JOIN matriculas m ON m.curso_id = c.id
     LEFT JOIN evaluaciones e ON e.curso_id = c.id
     WHERE c.docente_id = ? AND c.activo = 1
     GROUP BY c.id
     ORDER BY c.created_at DESC"
);
$cursos->execute([$docente_id]);
$listaCursos = $cursos->fetchAll();

$diasSemana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$meses      = ['enero','febrero','marzo','abril','mayo','junio',
               'julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy = $diasSemana[date('w')] . ', ' . date('j') . ' de ' . $meses[(int)date('n')-1] . ' de ' . date('Y');

$nombreCorto = explode(' ', $_SESSION['nombre'])[0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Docente — GestiónAcad</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">

    <!-- ── Encabezado ── -->
    <header class="dash-header">
        <span class="logo">&#127979; GestiónAcad</span>
        <div class="user-info">
            <span>
                <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                <span class="badge-rol badge-docente">Docente</span>
            </span>
            <a href="perfil.php" class="btn-logout" style="background:#1e3a5f; margin-right:6px;">&#128100; Mi Perfil</a>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <main class="dash-main">

        <!-- ══════════════════════════════════════════
             HERO BANNER
        ══════════════════════════════════════════ -->
        <div class="hero-banner">

            <!-- Decoración de fondo: círculos y formas -->
            <div class="hero-deco hero-deco-1"></div>
            <div class="hero-deco hero-deco-2"></div>
            <div class="hero-deco hero-deco-3"></div>

            <!-- Contenido izquierdo -->
            <div class="hero-content">
                <span class="hero-tag">&#127979; Plataforma Académica · UNERG</span>

                <h1 class="hero-title">
                    Bienvenido, Prof.<br>
                    <span class="hero-name"><?php echo htmlspecialchars($nombreCorto); ?></span>
                </h1>

                <p class="hero-date">&#128197; <?php echo $hoy; ?></p>

                <!-- Mini stats dentro del hero -->
                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-num"><?php echo $numCursos; ?></span>
                        <span class="hero-stat-label">Cursos</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <span class="hero-stat-num"><?php echo $numEstudiantes; ?></span>
                        <span class="hero-stat-label">Estudiantes</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <span class="hero-stat-num"><?php echo $numEvaluaciones; ?></span>
                        <span class="hero-stat-label">Evaluaciones</span>
                    </div>
                </div>
            </div>

            <!-- Iconos decorativos lado derecho -->
            <div class="hero-icons">
                <div class="hero-icon-bubble hi-1">&#127891;</div>
                <div class="hero-icon-bubble hi-2">&#128218;</div>
                <div class="hero-icon-bubble hi-3">&#9997;</div>
                <div class="hero-icon-bubble hi-4">&#128203;</div>
                <div class="hero-icon-bubble hi-5">&#128200;</div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════
             ACCIONES RÁPIDAS (tarjetas)
        ══════════════════════════════════════════ -->
        <div class="quick-grid">

            <a href="crear_curso.php" class="quick-card qc-blue">
                <div class="qc-icon">&#128218;</div>
                <div class="qc-text">
                    <span class="qc-title">Nuevo Curso</span>
                    <span class="qc-sub">Crear asignatura</span>
                </div>
                <div class="qc-arrow">&#8594;</div>
            </a>

            <a href="cargar_estudiantes.php" class="quick-card qc-teal">
                <div class="qc-icon">&#128196;</div>
                <div class="qc-text">
                    <span class="qc-title">Cargar Listado</span>
                    <span class="qc-sub">Importar CSV</span>
                </div>
                <div class="qc-arrow">&#8594;</div>
            </a>

            <a href="crear_evaluacion.php" class="quick-card qc-violet">
                <div class="qc-icon">&#128221;</div>
                <div class="qc-text">
                    <span class="qc-title">Nueva Evaluación</span>
                    <span class="qc-sub">Examen o actividad</span>
                </div>
                <div class="qc-arrow">&#8594;</div>
            </a>

            <a href="generar_enlace.php" class="quick-card qc-amber">
                <div class="qc-icon">&#128279;</div>
                <div class="qc-text">
                    <span class="qc-title">Generar Enlace</span>
                    <span class="qc-sub">Acceso estudiantes</span>
                </div>
                <div class="qc-arrow">&#8594;</div>
            </a>
        </div>

        <!-- ══════════════════════════════════════════
             TABLA DE CURSOS
        ══════════════════════════════════════════ -->
        <div class="section-card">
            <h3>&#128218; Mis Cursos</h3>

            <?php if (count($listaCursos) > 0): ?>
            <table class="info-table">
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Código</th>
                        <th>Estudiantes</th>
                        <th>Evaluaciones</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listaCursos as $curso): ?>
                    <tr>
                        <td style="font-weight:500; color:#cbd5e1;">
                            <?php echo htmlspecialchars($curso['nombre']); ?>
                        </td>
                        <td>
                            <?php if ($curso['codigo']): ?>
                                <span class="code-badge"><?php echo htmlspecialchars($curso['codigo']); ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?php echo $curso['num_estudiantes']; ?></td>
                        <td><?php echo $curso['num_evaluaciones']; ?></td>
                        <td>
                            <a href="ver_curso.php?id=<?php echo $curso['id']; ?>" class="btn-primary"
                               style="padding:6px 16px; font-size:.82rem;">
                                Ver curso &#8594;
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div style="font-size:3rem; margin-bottom:12px;">&#128218;</div>
                <p style="color:#94a3b8; font-size:.95rem;">Aún no tienes cursos creados.</p>
                <br>
                <a href="crear_curso.php" class="btn-primary">+ Crear mi primer curso</a>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>
</body>
</html>
