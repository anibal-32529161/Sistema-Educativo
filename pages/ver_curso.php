<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'docente') {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$curso_id      = (int)($_GET['id'] ?? 0);
$docente_id    = $_SESSION['user_id'];
$msg_creado    = isset($_GET['creado']);
$editando_eval = (int)($_GET['editando_eval'] ?? 0);

// Verificar que el curso le pertenece al docente
$stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ? AND docente_id = ?");
$stmt->execute([$curso_id, $docente_id]);
$curso = $stmt->fetch();

if (!$curso) {
    header("Location: dashboard_docente.php");
    exit();
}

// --- Acciones POST ---
$error   = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // Agregar evaluación
    if ($accion === 'add_eval') {
        $nombre_eval  = trim($_POST['nombre_eval'] ?? '');
        $porcentaje   = (float)($_POST['porcentaje'] ?? 0);
        $fecha_eval   = $_POST['fecha_eval'] ?? null;
        $desc_eval    = trim($_POST['desc_eval'] ?? '') ?: null;

        if ($nombre_eval === '' || $porcentaje <= 0 || $porcentaje > 100) {
            $error = "Nombre y porcentaje válido (1–100) son obligatorios.";
        } else {
            // Verificar que la suma no supere 100
            $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(porcentaje),0) FROM evaluaciones WHERE curso_id = ?");
            $sumStmt->execute([$curso_id]);
            $sumaActual = (float)$sumStmt->fetchColumn();

            if ($sumaActual + $porcentaje > 100) {
                $error = "La suma de porcentajes supera el 100%. Disponible: " . (100 - $sumaActual) . "%.";
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO evaluaciones (curso_id, nombre, descripcion, porcentaje, fecha)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $ins->execute([$curso_id, $nombre_eval, $desc_eval, $porcentaje, $fecha_eval ?: null]);
                $success = "Evaluación \"$nombre_eval\" agregada.";
            }
        }
    }

    // Eliminar evaluación
    if ($accion === 'del_eval') {
        $eval_id = (int)($_POST['eval_id'] ?? 0);
        $del = $pdo->prepare(
            "DELETE e FROM evaluaciones e
             INNER JOIN cursos c ON e.curso_id = c.id
             WHERE e.id = ? AND c.docente_id = ?"
        );
        $del->execute([$eval_id, $docente_id]);
        $success = "Evaluación eliminada.";
    }

    // Agregar estudiante manualmente
    if ($accion === 'add_est') {
        $cedula_est = trim($_POST['cedula_est'] ?? '');
        if ($cedula_est === '') {
            $error = "Ingresa la cédula del estudiante.";
        } else {
            $est = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ? AND rol = 'estudiante'");
            $est->execute([$cedula_est]);
            $estudiante = $est->fetch();

            if (!$estudiante) {
                $error = "No se encontró un estudiante con esa cédula. Debe registrarse primero.";
            } else {
                try {
                    $mat = $pdo->prepare("INSERT INTO matriculas (estudiante_id, curso_id) VALUES (?, ?)");
                    $mat->execute([$estudiante['id'], $curso_id]);
                    $success = "Estudiante inscrito correctamente.";
                } catch (PDOException $e) {
                    $error = "Ese estudiante ya está inscrito en este curso.";
                }
            }
        }
    }

    // Eliminar estudiante del curso
    if ($accion === 'del_est') {
        $mat_id = (int)($_POST['mat_id'] ?? 0);
        $del = $pdo->prepare(
            "DELETE m FROM matriculas m
             INNER JOIN cursos c ON m.curso_id = c.id
             WHERE m.id = ? AND c.docente_id = ?"
        );
        $del->execute([$mat_id, $docente_id]);
        $success = "Estudiante removido del curso.";
    }

    // Editar datos del curso
    if ($accion === 'edit_curso') {
        $nuevo_nombre = trim($_POST['nuevo_nombre'] ?? '');
        $nuevo_codigo = trim($_POST['nuevo_codigo'] ?? '') ?: null;
        $nueva_desc   = trim($_POST['nueva_desc']   ?? '') ?: null;
        if ($nuevo_nombre === '') {
            $error = "El nombre del curso es obligatorio.";
        } else {
            $pdo->prepare(
                "UPDATE cursos SET nombre=?, codigo=?, descripcion=? WHERE id=? AND docente_id=?"
            )->execute([$nuevo_nombre, $nuevo_codigo, $nueva_desc, $curso_id, $docente_id]);
            $stmt = $pdo->prepare("SELECT * FROM cursos WHERE id=? AND docente_id=?");
            $stmt->execute([$curso_id, $docente_id]);
            $curso   = $stmt->fetch();
            $success = "Datos del curso actualizados.";
        }
    }

    // Editar evaluación
    if ($accion === 'edit_eval') {
        $eval_id      = (int)($_POST['eval_id']         ?? 0);
        $nomb_ev      = trim($_POST['nuevo_nombre_eval'] ?? '');
        $nuevo_pct    = (float)($_POST['nuevo_porcentaje'] ?? 0);
        $nueva_fecha  = $_POST['nueva_fecha_eval']       ?? null;
        $nueva_desc_e = trim($_POST['nueva_desc_eval']   ?? '') ?: null;
        if ($nomb_ev === '' || $nuevo_pct <= 0 || $nuevo_pct > 100) {
            $error = "Nombre y porcentaje válido (1–100) son obligatorios.";
        } else {
            $sumOtros = $pdo->prepare(
                "SELECT COALESCE(SUM(porcentaje),0) FROM evaluaciones WHERE curso_id=? AND id!=?"
            );
            $sumOtros->execute([$curso_id, $eval_id]);
            $sumaOtros = (float)$sumOtros->fetchColumn();
            if ($sumaOtros + $nuevo_pct > 100) {
                $error = "El porcentaje supera el 100%. Disponible: " . (100 - $sumaOtros) . "%.";
            } else {
                $pdo->prepare(
                    "UPDATE evaluaciones SET nombre=?, porcentaje=?, fecha=?, descripcion=?
                     WHERE id=? AND curso_id=?"
                )->execute([$nomb_ev, $nuevo_pct, $nueva_fecha ?: null, $nueva_desc_e, $eval_id, $curso_id]);
                $success = "Evaluación actualizada.";
                $editando_eval = 0;
            }
        }
    }

    // Guardar calificación
    if ($accion === 'guardar_nota') {
        $mat_id  = (int)($_POST['mat_id']  ?? 0);
        $eval_id = (int)($_POST['eval_id'] ?? 0);
        $nota    = $_POST['nota'] ?? '';
        $obs     = trim($_POST['obs'] ?? '') ?: null;

        if ($nota === '' || !is_numeric($nota) || (float)$nota < 0 || (float)$nota > 20) {
            $error = "La nota debe ser un número entre 0 y 20.";
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO calificaciones (matricula_id, evaluacion_id, nota, observacion)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE nota = VALUES(nota), observacion = VALUES(observacion)"
            );
            $ins->execute([$mat_id, $eval_id, (float)$nota, $obs]);
            $success = "Calificación guardada.";
        }
    }
}

// --- Consultas de datos ---
$evaluaciones = $pdo->prepare("SELECT * FROM evaluaciones WHERE curso_id = ? ORDER BY fecha ASC, id ASC");
$evaluaciones->execute([$curso_id]);
$listaEval = $evaluaciones->fetchAll();

$sumaPct = array_sum(array_column($listaEval, 'porcentaje'));

$estudiantes = $pdo->prepare(
    "SELECT m.id AS mat_id, u.id AS est_id,
            u.nombre, u.apellido, u.cedula
     FROM matriculas m
     INNER JOIN usuarios u ON m.estudiante_id = u.id
     WHERE m.curso_id = ?
     ORDER BY u.apellido, u.nombre"
);
$estudiantes->execute([$curso_id]);
$listaEst = $estudiantes->fetchAll();

// Cargar todas las calificaciones del curso de una sola consulta
$notasStmt = $pdo->prepare(
    "SELECT cal.matricula_id, cal.evaluacion_id, cal.nota
     FROM calificaciones cal
     INNER JOIN matriculas m ON cal.matricula_id = m.id
     WHERE m.curso_id = ?"
);
$notasStmt->execute([$curso_id]);
$notas = [];
foreach ($notasStmt->fetchAll() as $row) {
    $notas[$row['matricula_id']][$row['evaluacion_id']] = $row['nota'];
}

// Enlace de acceso vigente
$enlaceStmt = $pdo->prepare(
    "SELECT token FROM enlaces_acceso
     WHERE curso_id = ? AND activo = 1
     ORDER BY created_at DESC LIMIT 1"
);
$enlaceStmt->execute([$curso_id]);
$token = $enlaceStmt->fetchColumn();
$enlaceURL = $token ? "http://{$_SERVER['HTTP_HOST']}/Login/pages/acceso.php?token=$token" : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($curso['nombre']); ?> — GestiónAcad</title>
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
            <a href="cambiar_contrasena.php" class="btn-logout" style="background:#1e3a5f; margin-right:6px;">&#128274; Contraseña</a>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <main class="dash-main">

        <div class="dash-welcome">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <a href="dashboard_docente.php" style="color:#5c9cf5; font-size:0.88rem; text-decoration:none;">
                    &#8592; Volver al panel
                </a>
                <a href="reporte_notas.php?curso_id=<?php echo $curso_id; ?>" class="btn-print" style="font-size:.82rem; padding:8px 16px;">
                    &#128438; Reporte de Notas
                </a>
            </div>
            <h2 style="margin-top:10px;">
                <?php echo htmlspecialchars($curso['nombre']); ?>
                <?php if ($curso['codigo']): ?>
                    <span style="font-size:0.9rem; color:#475569; font-weight:400;">
                        (<?php echo htmlspecialchars($curso['codigo']); ?>)
                    </span>
                <?php endif; ?>
            </h2>
            <?php if ($curso['descripcion']): ?>
                <p><?php echo htmlspecialchars($curso['descripcion']); ?></p>
            <?php endif; ?>

            <!-- Editar datos del curso -->
            <details class="add-form" style="margin-top:14px;">
                <summary>&#9998; Editar datos del curso</summary>
                <form method="POST" style="margin-top:12px;">
                    <input type="hidden" name="accion" value="edit_curso">
                    <input type="text" name="nuevo_nombre" placeholder="Nombre del curso" required
                           value="<?php echo htmlspecialchars($curso['nombre']); ?>">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <input type="text" name="nuevo_codigo" placeholder="Código (opcional)"
                               value="<?php echo htmlspecialchars($curso['codigo'] ?? ''); ?>">
                        <span></span>
                    </div>
                    <input type="text" name="nueva_desc" placeholder="Descripción (opcional)"
                           value="<?php echo htmlspecialchars($curso['descripcion'] ?? ''); ?>">
                    <button type="submit" style="margin-top:6px;">Guardar cambios</button>
                </form>
            </details>
        </div>

        <?php if ($msg_creado): ?>
            <div class="alert-success" style="margin-bottom:20px;">
                &#10003; Curso creado exitosamente. Ya puedes agregar evaluaciones y estudiantes.
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-success" style="margin-bottom:20px;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="two-col">

            <!-- ── COLUMNA IZQUIERDA: Evaluaciones ── -->
            <div>
                <div class="section-card">
                    <h3>&#128221; Evaluaciones
                        <span style="float:right; font-size:0.8rem; color:#475569; font-weight:400;">
                            Suma: <?php echo number_format($sumaPct, 1); ?>% / 100%
                        </span>
                    </h3>

                    <?php if (count($listaEval) > 0): ?>
                    <table class="info-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>%</th>
                                <th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listaEval as $ev): ?>
                        <?php if ($editando_eval === $ev['id']): ?>
                            <!-- Fila de edición inline -->
                            <tr style="background:rgba(92,156,245,.07);">
                                <td colspan="4">
                                    <form method="POST" style="display:grid; gap:6px; padding:4px 0;">
                                        <input type="hidden" name="accion"  value="edit_eval">
                                        <input type="hidden" name="eval_id" value="<?php echo $ev['id']; ?>">
                                        <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:6px;">
                                            <input type="text" name="nuevo_nombre_eval"
                                                   value="<?php echo htmlspecialchars($ev['nombre']); ?>" required
                                                   placeholder="Nombre">
                                            <input type="number" name="nuevo_porcentaje"
                                                   value="<?php echo $ev['porcentaje']; ?>"
                                                   min="1" max="100" step="0.5" required placeholder="%">
                                            <input type="date" name="nueva_fecha_eval"
                                                   value="<?php echo $ev['fecha'] ?? ''; ?>">
                                        </div>
                                        <input type="text" name="nueva_desc_eval"
                                               value="<?php echo htmlspecialchars($ev['descripcion'] ?? ''); ?>"
                                               placeholder="Descripción (opcional)">
                                        <div style="display:flex; gap:8px;">
                                            <button type="submit" style="width:auto; padding:8px 18px; font-size:.85rem;">
                                                &#10003; Guardar
                                            </button>
                                            <a href="ver_curso.php?id=<?php echo $curso_id; ?>"
                                               style="padding:8px 14px; border-radius:8px; background:rgba(255,255,255,.06); color:#94a3b8; font-size:.85rem; text-decoration:none; display:inline-flex; align-items:center;">
                                                Cancelar
                                            </a>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ev['nombre']); ?></td>
                                <td><?php echo number_format($ev['porcentaje'], 1); ?>%</td>
                                <td><?php echo $ev['fecha'] ? date('d/m/Y', strtotime($ev['fecha'])) : '—'; ?></td>
                                <td style="display:flex; gap:6px;">
                                    <a href="ver_curso.php?id=<?php echo $curso_id; ?>&editando_eval=<?php echo $ev['id']; ?>"
                                       class="btn-danger-sm" style="background:rgba(92,156,245,.15); color:#5c9cf5;">
                                        &#9998;
                                    </a>
                                    <form method="POST" onsubmit="return confirm('¿Eliminar esta evaluación y todas sus notas?');">
                                        <input type="hidden" name="accion"  value="del_eval">
                                        <input type="hidden" name="eval_id" value="<?php echo $ev['id']; ?>">
                                        <button type="submit" class="btn-danger-sm">&#10005;</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p class="empty-state">Sin evaluaciones aún.</p>
                    <?php endif; ?>

                    <!-- Formulario nueva evaluación -->
                    <?php if ($sumaPct < 100): ?>
                    <details class="add-form" <?php echo (isset($_POST['accion']) && $_POST['accion'] === 'add_eval') ? 'open' : ''; ?>>
                        <summary>+ Agregar evaluación</summary>
                        <form method="POST" style="margin-top:12px;">
                            <input type="hidden" name="accion" value="add_eval">
                            <input type="text" name="nombre_eval" placeholder="Nombre (ej: Primer Parcial)"
                                   value="<?php echo htmlspecialchars($_POST['nombre_eval'] ?? ''); ?>" required>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                <input type="number" name="porcentaje" placeholder="% (ej: 30)"
                                       min="1" max="100" step="0.5"
                                       value="<?php echo htmlspecialchars($_POST['porcentaje'] ?? ''); ?>" required>
                                <input type="date" name="fecha_eval"
                                       value="<?php echo htmlspecialchars($_POST['fecha_eval'] ?? ''); ?>">
                            </div>
                            <input type="text" name="desc_eval" placeholder="Descripción (opcional)"
                                   value="<?php echo htmlspecialchars($_POST['desc_eval'] ?? ''); ?>">
                            <button type="submit" style="margin-top:6px;">Agregar</button>
                        </form>
                    </details>
                    <?php else: ?>
                        <p style="font-size:0.8rem; color:#64748b; margin-top:10px;">
                            &#9989; 100% del curso ya está distribuido.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Enlace de acceso -->
                <div class="section-card">
                    <h3>&#128279; Enlace de acceso para estudiantes</h3>
                    <?php if ($enlaceURL): ?>
                        <p style="font-size:0.82rem; color:#64748b; margin-bottom:8px;">
                            Comparte este enlace con tus estudiantes para que se inscriban:
                        </p>
                        <div class="link-box" id="enlace-box">
                            <?php echo htmlspecialchars($enlaceURL); ?>
                        </div>
                        <button type="button" onclick="copiarEnlace()" class="btn-primary" style="margin-top:10px; width:auto; padding:8px 18px;">
                            &#128203; Copiar enlace
                        </button>
                    <?php else: ?>
                        <p class="empty-state" style="padding:12px 0;">No hay enlace activo.</p>
                    <?php endif; ?>
                    <form method="POST" action="generar_enlace.php" style="margin-top:10px;">
                        <input type="hidden" name="curso_id" value="<?php echo $curso_id; ?>">
                        <button type="submit" class="btn-primary" style="width:auto; padding:8px 18px;">
                            &#8635; <?php echo $enlaceURL ? 'Generar nuevo enlace' : 'Generar enlace'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── COLUMNA DERECHA: Estudiantes y notas ── -->
            <div>
                <div class="section-card">
                    <h3>&#127891; Estudiantes (<?php echo count($listaEst); ?>)</h3>

                    <?php if (count($listaEst) > 0 && count($listaEval) > 0): ?>
                    <!-- Tabla de calificaciones -->
                    <div style="overflow-x:auto;">
                    <table class="info-table grades-table">
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <th>Cédula</th>
                                <?php foreach ($listaEval as $ev): ?>
                                    <th title="<?php echo htmlspecialchars($ev['nombre']); ?>">
                                        <?php echo htmlspecialchars(mb_strimwidth($ev['nombre'], 0, 12, '…')); ?>
                                        <span style="display:block; font-weight:400; color:#475569;">
                                            <?php echo number_format($ev['porcentaje'], 0); ?>%
                                        </span>
                                    </th>
                                <?php endforeach; ?>
                                <th>Prom.</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listaEst as $est): ?>
                            <?php
                            $notasPonderadas = 0;
                            $pctAcum = 0;
                            foreach ($listaEval as $ev) {
                                $n = $notas[$est['mat_id']][$ev['id']] ?? null;
                                if ($n !== null) {
                                    $notasPonderadas += ($n * $ev['porcentaje'] / 100);
                                    $pctAcum += $ev['porcentaje'];
                                }
                            }
                            $promEstudiante = $pctAcum > 0
                                ? number_format($notasPonderadas * 100 / $pctAcum, 1)
                                : '—';
                            $colorProm = is_numeric($promEstudiante)
                                ? ($promEstudiante >= 10 ? 'color:#86efac;' : 'color:#fca5a5;')
                                : '';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($est['apellido'] . ', ' . $est['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($est['cedula'] ?? '—'); ?></td>

                                <?php foreach ($listaEval as $ev): ?>
                                <td>
                                    <form method="POST" class="nota-form">
                                        <input type="hidden" name="accion"   value="guardar_nota">
                                        <input type="hidden" name="mat_id"   value="<?php echo $est['mat_id']; ?>">
                                        <input type="hidden" name="eval_id"  value="<?php echo $ev['id']; ?>">
                                        <input type="number" name="nota"
                                               class="nota-input"
                                               min="0" max="20" step="0.5"
                                               value="<?php echo $notas[$est['mat_id']][$ev['id']] ?? ''; ?>"
                                               placeholder="—"
                                               onchange="this.form.submit()">
                                    </form>
                                </td>
                                <?php endforeach; ?>

                                <td style="font-weight:600; <?php echo $colorProm; ?>">
                                    <?php echo $promEstudiante; ?>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('¿Remover a este estudiante del curso?');">
                                        <input type="hidden" name="accion" value="del_est">
                                        <input type="hidden" name="mat_id" value="<?php echo $est['mat_id']; ?>">
                                        <button type="submit" class="btn-danger-sm">&#10005;</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <?php elseif (count($listaEst) > 0): ?>
                        <p style="font-size:0.85rem; color:#64748b; margin-bottom:12px;">
                            Crea evaluaciones para poder registrar notas.
                        </p>
                        <?php foreach ($listaEst as $est): ?>
                        <div style="padding:8px 0; border-top:1px solid #334155; font-size:0.88rem; color:#94a3b8; display:flex; justify-content:space-between; align-items:center;">
                            <span><?php echo htmlspecialchars($est['apellido'] . ', ' . $est['nombre']); ?>
                                <span style="color:#475569;"> — <?php echo htmlspecialchars($est['cedula'] ?? ''); ?></span>
                            </span>
                            <form method="POST">
                                <input type="hidden" name="accion" value="del_est">
                                <input type="hidden" name="mat_id" value="<?php echo $est['mat_id']; ?>">
                                <button type="submit" class="btn-danger-sm"
                                        onclick="return confirm('¿Remover estudiante?');">&#10005;</button>
                            </form>
                        </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <p class="empty-state">Sin estudiantes inscritos aún.</p>
                    <?php endif; ?>

                    <!-- Agregar estudiante por cédula -->
                    <details class="add-form" style="margin-top:12px;">
                        <summary>+ Agregar estudiante por cédula</summary>
                        <form method="POST" style="margin-top:10px; display:flex; gap:8px;">
                            <input type="hidden" name="accion" value="add_est">
                            <input type="text" name="cedula_est" placeholder="Cédula (ej: V-12345678)"
                                   style="flex:1;" required>
                            <button type="submit" style="width:auto; padding:10px 16px;">Agregar</button>
                        </form>
                    </details>
                </div>
            </div>

        </div><!-- /.two-col -->
    </main>
</div>

<script>
function copiarEnlace() {
    const texto = document.getElementById('enlace-box').innerText.trim();
    navigator.clipboard.writeText(texto).then(() => {
        alert('¡Enlace copiado al portapapeles!');
    });
}
</script>
</body>
</html>
