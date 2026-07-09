<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$msg_ok  = $_GET['ok']  ?? '';
$msg_err = $_GET['err'] ?? '';

// ── Estadísticas globales ───────────────────────────────────
$stats = [];
foreach ([
    'docentes'       => "SELECT COUNT(*) FROM usuarios WHERE rol = 'docente'",
    'estudiantes'    => "SELECT COUNT(*) FROM usuarios WHERE rol = 'estudiante'",
    'cursos'         => "SELECT COUNT(*) FROM cursos WHERE activo = 1",
    'evaluaciones'   => "SELECT COUNT(*) FROM evaluaciones",
    'calificaciones' => "SELECT COUNT(*) FROM calificaciones",
    'matriculas'     => "SELECT COUNT(*) FROM matriculas",
] as $key => $sql) {
    $stats[$key] = $pdo->query($sql)->fetchColumn();
}

// ── Lista de usuarios ───────────────────────────────────────
$usuarios = $pdo->query(
    "SELECT id, rol, nombre, apellido, cedula, email, activo, created_at
     FROM usuarios ORDER BY rol, apellido, nombre"
)->fetchAll();

// ── Lista de cursos ─────────────────────────────────────────
$cursos = $pdo->query(
    "SELECT c.id, c.nombre, c.codigo, c.activo,
            CONCAT(u.nombre, ' ', u.apellido) AS docente,
            COUNT(DISTINCT m.id) AS num_estudiantes,
            COUNT(DISTINCT e.id) AS num_evaluaciones
     FROM cursos c
     INNER JOIN usuarios u ON c.docente_id = u.id
     LEFT  JOIN matriculas m ON m.curso_id = c.id
     LEFT  JOIN evaluaciones e ON e.curso_id = c.id
     GROUP BY c.id, u.nombre, u.apellido
     ORDER BY c.activo DESC, c.nombre"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador — GestiónAcad</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">

    <header class="dash-header">
        <span class="logo">&#127979; GestiónAcad</span>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                <span class="admin-badge admin-badge-admin">Admin</span>
            </span>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <main class="dash-main">

        <h2 style="color:#f8fafc; margin-bottom:6px;">&#9881; Panel de Administración</h2>
        <p style="color:#64748b; font-size:.9rem; margin-bottom:24px;">
            Gestión global del sistema GestiónAcad — UNERG.
        </p>

        <?php if ($msg_ok): ?>
            <div class="alert-success" style="margin-bottom:20px;">&#10003; <?php echo htmlspecialchars($msg_ok); ?></div>
        <?php endif; ?>
        <?php if ($msg_err): ?>
            <div class="alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($msg_err); ?></div>
        <?php endif; ?>

        <!-- ── Estadísticas ── -->
        <div class="admin-grid">
            <div class="admin-stat-card">
                <span class="admin-stat-num"><?php echo $stats['docentes']; ?></span>
                <span class="admin-stat-label">Docentes</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-num"><?php echo $stats['estudiantes']; ?></span>
                <span class="admin-stat-label">Estudiantes</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-num"><?php echo $stats['cursos']; ?></span>
                <span class="admin-stat-label">Cursos activos</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-num"><?php echo $stats['evaluaciones']; ?></span>
                <span class="admin-stat-label">Evaluaciones</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-num"><?php echo $stats['matriculas']; ?></span>
                <span class="admin-stat-label">Matrículas</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-num"><?php echo $stats['calificaciones']; ?></span>
                <span class="admin-stat-label">Calificaciones</span>
            </div>
        </div>

        <!-- ── Usuarios ── -->
        <div class="section-card" style="margin-bottom:24px;">
            <h3>&#128100; Usuarios del sistema</h3>
            <div style="overflow-x:auto;">
            <table class="info-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Cédula / Email</th>
                        <th>Registro</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td style="color:#475569;"><?php echo $u['id']; ?></td>
                        <td style="font-weight:500;">
                            <?php echo htmlspecialchars($u['apellido'] . ', ' . $u['nombre']); ?>
                        </td>
                        <td>
                            <?php if ($u['rol'] === 'docente'): ?>
                                <span class="admin-badge admin-badge-doc">Docente</span>
                            <?php elseif ($u['rol'] === 'estudiante'): ?>
                                <span class="admin-badge admin-badge-est">Estudiante</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-admin">Admin</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.82rem; color:#94a3b8;">
                            <?php echo htmlspecialchars($u['cedula'] ?: ($u['email'] ?: '—')); ?>
                        </td>
                        <td style="font-size:.78rem; color:#475569;">
                            <?php echo date('d/m/Y', strtotime($u['created_at'])); ?>
                        </td>
                        <td>
                            <?php if ($u['activo']): ?>
                                <span class="admin-badge admin-badge-on">Activo</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-off">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['rol'] !== 'admin'): ?>
                            <form method="POST" action="admin_accion.php" style="display:inline;">
                                <input type="hidden" name="accion" value="toggle_user">
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <input type="hidden" name="activo" value="<?php echo $u['activo'] ? '0' : '1'; ?>">
                                <button type="submit" class="btn-primary"
                                        style="padding:4px 12px; font-size:.75rem;
                                               background:<?php echo $u['activo'] ? '#7f1d1d' : '#052e16'; ?>;">
                                    <?php echo $u['activo'] ? '&#128683; Desactivar' : '&#10003; Activar'; ?>
                                </button>
                            </form>
                            <?php else: ?>
                                <span style="color:#475569; font-size:.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- ── Cursos ── -->
        <div class="section-card">
            <h3>&#128218; Todos los cursos</h3>
            <div style="overflow-x:auto;">
            <table class="info-table">
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Código</th>
                        <th>Docente</th>
                        <th>Estudiantes</th>
                        <th>Evaluaciones</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursos as $c): ?>
                    <tr>
                        <td style="font-weight:500;">
                            <?php echo htmlspecialchars($c['nombre']); ?>
                        </td>
                        <td>
                            <?php if ($c['codigo']): ?>
                                <span class="code-badge"><?php echo htmlspecialchars($c['codigo']); ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td style="font-size:.85rem; color:#94a3b8;">
                            <?php echo htmlspecialchars($c['docente']); ?>
                        </td>
                        <td style="text-align:center;"><?php echo $c['num_estudiantes']; ?></td>
                        <td style="text-align:center;"><?php echo $c['num_evaluaciones']; ?></td>
                        <td>
                            <?php if ($c['activo']): ?>
                                <span class="admin-badge admin-badge-on">Activo</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-off">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="admin_accion.php" style="display:inline;">
                                <input type="hidden" name="accion" value="toggle_curso">
                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                <input type="hidden" name="activo" value="<?php echo $c['activo'] ? '0' : '1'; ?>">
                                <button type="submit" class="btn-primary"
                                        style="padding:4px 12px; font-size:.75rem;
                                               background:<?php echo $c['activo'] ? '#7f1d1d' : '#052e16'; ?>;">
                                    <?php echo $c['activo'] ? '&#128683; Desactivar' : '&#10003; Activar'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

    </main>
</div>
</body>
</html>
