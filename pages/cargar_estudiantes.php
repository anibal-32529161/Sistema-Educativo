<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'docente') {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$docente_id = $_SESSION['user_id'];
$error      = "";
$resultados = [];
$paso       = 1; // 1 = seleccionar curso, 2 = cargar archivo

// Cursos del docente
$stmtCursos = $pdo->prepare("SELECT id, nombre, codigo FROM cursos WHERE docente_id = ? AND activo = 1 ORDER BY nombre");
$stmtCursos->execute([$docente_id]);
$cursos = $stmtCursos->fetchAll();

$curso_id       = (int)($_POST['curso_id'] ?? $_GET['curso_id'] ?? 0);
$curso_sel      = null;

if ($curso_id) {
    // Verificar que le pertenece
    $chk = $pdo->prepare("SELECT * FROM cursos WHERE id = ? AND docente_id = ?");
    $chk->execute([$curso_id, $docente_id]);
    $curso_sel = $chk->fetch();
    if ($curso_sel) $paso = 2;
}

// ── PROCESAR CARGA ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['procesar']) && $curso_sel) {

    $lineas = [];

    // Opción A: archivo CSV
    if (!empty($_FILES['archivo']['tmp_name'])) {
        $handle = fopen($_FILES['archivo']['tmp_name'], 'r');
        while (($linea = fgets($handle)) !== false) {
            $lineas[] = trim($linea);
        }
        fclose($handle);
    }

    // Opción B: texto pegado
    if (empty($lineas) && !empty($_POST['texto'])) {
        $lineas = explode("\n", trim($_POST['texto']));
    }

    if (empty($lineas)) {
        $error = "No se recibieron datos. Sube un archivo o pega el listado.";
    } else {
        $creados    = 0;
        $inscritos  = 0;
        $yaInscritos = 0;
        $errores    = 0;

        foreach ($lineas as $i => $linea) {
            $linea = trim($linea);
            if ($linea === '') continue;

            // Detectar separador (coma o punto y coma)
            $sep = strpos($linea, ';') !== false ? ';' : ',';
            $cols = array_map('trim', explode($sep, $linea));

            // Ignorar encabezado
            if ($i === 0 && !is_numeric(preg_replace('/[^0-9]/', '', $cols[0]))) continue;

            $cedula   = $cols[0] ?? '';
            $nombre   = $cols[1] ?? '';
            $apellido = $cols[2] ?? '';
            $email    = !empty($cols[3]) ? $cols[3] : null;

            if ($cedula === '' || $nombre === '' || $apellido === '') {
                $resultados[] = ['estado' => 'error', 'linea' => $i + 1,
                    'msg' => "Fila " . ($i+1) . ": faltan datos (cédula, nombre o apellido)."];
                $errores++;
                continue;
            }

            // ¿El estudiante ya existe?
            $existe = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ?");
            $existe->execute([$cedula]);
            $est = $existe->fetch();

            if (!$est) {
                // Crear cuenta: contraseña = cédula por defecto
                $passHash = password_hash($cedula, PASSWORD_DEFAULT);
                try {
                    $ins = $pdo->prepare(
                        "INSERT INTO usuarios (rol, nombre, apellido, cedula, email, password)
                         VALUES ('estudiante', ?, ?, ?, ?, ?)"
                    );
                    $ins->execute([$nombre, $apellido, $cedula, $email, $passHash]);
                    $est_id = $pdo->lastInsertId();
                    $creados++;
                } catch (PDOException $ex) {
                    $resultados[] = ['estado' => 'error', 'linea' => $i + 1,
                        'msg' => "Fila " . ($i+1) . ": error al crear estudiante ($cedula)."];
                    $errores++;
                    continue;
                }
            } else {
                $est_id = $est['id'];
            }

            // Inscribir en el curso
            try {
                $mat = $pdo->prepare("INSERT INTO matriculas (estudiante_id, curso_id) VALUES (?, ?)");
                $mat->execute([$est_id, $curso_id]);
                $inscritos++;
                $resultados[] = ['estado' => 'ok', 'linea' => $i + 1,
                    'msg' => "$nombre $apellido ($cedula) inscrito correctamente."];
            } catch (PDOException $ex) {
                if ($ex->getCode() == 23000) {
                    $yaInscritos++;
                    $resultados[] = ['estado' => 'dup', 'linea' => $i + 1,
                        'msg' => "$nombre $apellido ($cedula) ya estaba inscrito."];
                } else {
                    $errores++;
                    $resultados[] = ['estado' => 'error', 'linea' => $i + 1,
                        'msg' => "Error al inscribir $cedula."];
                }
            }
        }

        $paso = 3; // mostrar resultados
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar Listado — GestiónAcad</title>
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

    <main class="dash-main" style="max-width:720px;">
        <div class="dash-welcome">
            <a href="dashboard_docente.php" style="color:#5c9cf5;font-size:.88rem;text-decoration:none;">
                &#8592; Volver al panel
            </a>
            <h2 style="margin-top:10px;">&#128196; Cargar Listado de Estudiantes</h2>
            <p>Importa un listado CSV para inscribir estudiantes en un curso de forma masiva.</p>
        </div>

        <!-- PASO 1: Seleccionar curso -->
        <?php if ($paso === 1): ?>
        <div class="section-card">
            <h3>Paso 1 — Selecciona el curso de destino</h3>
            <?php if (empty($cursos)): ?>
                <p class="empty-state">No tienes cursos. <a href="crear_curso.php" style="color:#5c9cf5;">Crea uno primero.</a></p>
            <?php else: ?>
            <form method="POST">
                <div class="field-group">
                    <label class="field-label">Curso <span class="required">*</span></label>
                    <select name="curso_id" required class="select-input">
                        <option value="">— Elige un curso —</option>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['nombre']); ?>
                                <?php if ($c['codigo']): ?>(<?php echo htmlspecialchars($c['codigo']); ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Continuar &#8594;</button>
            </form>
            <?php endif; ?>
        </div>

        <!-- PASO 2: Cargar archivo -->
        <?php elseif ($paso === 2): ?>
        <div class="section-card">
            <h3>Paso 2 — Carga el listado para:
                <span style="color:#7eb8f7;"><?php echo htmlspecialchars($curso_sel['nombre']); ?></span>
            </h3>

            <?php if ($error): ?>
                <div class="alert-error" style="margin-bottom:14px;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="info-box" style="margin-bottom:18px;">
                <strong style="color:#94a3b8;">Formato esperado (CSV o texto):</strong>
                <div class="link-box" style="margin-top:6px; font-size:.82rem; line-height:1.8;">
                    cedula;nombre;apellido;email<br>
                    V-12345678;Juan;Pérez;juan@correo.com<br>
                    V-87654321;María;Gómez;<br>
                    E-11223344;Luis;Torres;luis@correo.com
                </div>
                <p style="font-size:.78rem; color:#475569; margin-top:6px;">
                    &#8226; Separador: punto y coma (<code>;</code>) o coma (<code>,</code>)<br>
                    &#8226; Email es opcional &nbsp;&#8226; La primera fila puede ser encabezado<br>
                    &#8226; La contraseña inicial del estudiante será su número de cédula
                </p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="curso_id" value="<?php echo $curso_id; ?>">
                <input type="hidden" name="procesar" value="1">

                <div class="field-group">
                    <label class="field-label">Opción A — Subir archivo .csv o .txt</label>
                    <input type="file" name="archivo" accept=".csv,.txt" class="file-input">
                </div>

                <div class="divider-text">— o —</div>

                <div class="field-group">
                    <label class="field-label">Opción B — Pegar datos directamente</label>
                    <textarea name="texto" rows="8"
                              placeholder="V-12345678;Juan;Pérez;juan@correo.com&#10;V-87654321;María;Gómez;"></textarea>
                </div>

                <div style="display:flex;gap:12px;margin-top:8px;">
                    <button type="submit">&#128196; Procesar listado</button>
                    <a href="cargar_estudiantes.php" class="btn-cancel">&#8592; Cambiar curso</a>
                </div>
            </form>
        </div>

        <!-- PASO 3: Resultados -->
        <?php elseif ($paso === 3): ?>
        <?php
            $ok   = array_filter($resultados, fn($r) => $r['estado'] === 'ok');
            $dup  = array_filter($resultados, fn($r) => $r['estado'] === 'dup');
            $errs = array_filter($resultados, fn($r) => $r['estado'] === 'error');
        ?>
        <div class="section-card">
            <h3>&#9989; Procesamiento completado</h3>

            <div class="stats-grid" style="margin-bottom:20px;">
                <div class="stat-card">
                    <div class="stat-num" style="color:#86efac;"><?php echo count($ok); ?></div>
                    <div class="stat-label">Inscritos nuevos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num" style="color:#fbbf24;"><?php echo count($dup); ?></div>
                    <div class="stat-label">Ya inscritos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num" style="color:#fca5a5;"><?php echo count($errs); ?></div>
                    <div class="stat-label">Errores</div>
                </div>
            </div>

            <table class="info-table">
                <thead>
                    <tr><th>Fila</th><th>Estado</th><th>Detalle</th></tr>
                </thead>
                <tbody>
                <?php foreach ($resultados as $r): ?>
                    <?php
                    $color = $r['estado'] === 'ok'    ? '#86efac'
                           : ($r['estado'] === 'dup'  ? '#fbbf24' : '#fca5a5');
                    $icono = $r['estado'] === 'ok'    ? '&#10003;'
                           : ($r['estado'] === 'dup'  ? '&#9888;'  : '&#10005;');
                    ?>
                    <tr>
                        <td><?php echo $r['linea']; ?></td>
                        <td style="color:<?php echo $color; ?>; font-weight:600;">
                            <?php echo $icono; ?>
                            <?php echo $r['estado'] === 'ok' ? 'Inscrito' : ($r['estado'] === 'dup' ? 'Duplicado' : 'Error'); ?>
                        </td>
                        <td><?php echo htmlspecialchars($r['msg']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div style="display:flex;gap:12px;margin-top:20px;">
                <a href="ver_curso.php?id=<?php echo $curso_id; ?>" class="btn-primary">
                    Ver curso completo
                </a>
                <a href="cargar_estudiantes.php?curso_id=<?php echo $curso_id; ?>" class="btn-cancel">
                    Cargar otro listado
                </a>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
