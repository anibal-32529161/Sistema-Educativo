<?php
session_start();
include '../config/db.php';
$error   = "";
$success = "";

// Pestaña activa: query param > POST > defecto docente
$tab = $_GET['rol'] ?? (isset($_POST['rol']) ? $_POST['rol'] : 'docente');

// Mensaje tras registro exitoso
if (isset($_GET['registered'])) {
    $rolReg  = $_GET['rol'] ?? 'docente';
    $success = $rolReg === 'estudiante'
        ? "✓ Cuenta creada. Ingresa tu cédula y contraseña abajo."
        : "✓ Cuenta creada. Ingresa tu correo y contraseña abajo.";
}

// Cédula pre-rellenada si viene desde register.php (cédula duplicada)
$cedula_pre = htmlspecialchars($_GET['cedula'] ?? '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rol      = $_POST['rol'] ?? 'docente';
    $password = $_POST['password'] ?? '';
    $tab      = $rol;

    if ($rol === 'docente') {
        $email = trim($_POST['email'] ?? '');
        $stmt  = $pdo->prepare(
            "SELECT * FROM usuarios
             WHERE email = ? AND rol IN ('docente','admin') AND activo = 1"
        );
        $stmt->execute([$email]);
    } else {
        $cedula = trim($_POST['cedula'] ?? '');
        $stmt   = $pdo->prepare(
            "SELECT * FROM usuarios
             WHERE cedula = ? AND rol = 'estudiante' AND activo = 1"
        );
        $stmt->execute([$cedula]);
    }

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['rol']     = $user['rol'];
        $_SESSION['nombre']  = $user['nombre'] . ' ' . $user['apellido'];

        $destino = match($user['rol']) {
            'docente'    => "dashboard_docente.php",
            'estudiante' => "dashboard_estudiante.php",
            'admin'      => "admin.php",
            default      => "login.php",
        };
        header("Location: $destino");
        exit();
    } else {
        if ($rol === 'docente') {
            $error = "Correo o contraseña incorrectos.";
        } else {
            $error = "Cédula o contraseña incorrectos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GestiónAcad — Acceso</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <video autoplay muted loop playsinline id="bg-video">
        <source src="../assets/images/6.mp4" type="video/mp4">
    </video>
    <div class="video-overlay"></div>

    <div class="form-container">
        <div class="brand">
            <span class="brand-icon">&#127979;</span>
            <h1>GestiónAcad</h1>
            <p class="brand-sub">Sistema de Gestión Académica</p>
        </div>

        <!-- Pestañas -->
        <div class="tab-group">
            <button type="button" id="tab-btn-docente"
                    class="tab-btn <?php echo $tab === 'docente' ? 'active' : ''; ?>"
                    onclick="switchTab('docente')">
                &#128203; Docente
            </button>
            <button type="button" id="tab-btn-estudiante"
                    class="tab-btn <?php echo $tab === 'estudiante' ? 'active' : ''; ?>"
                    onclick="switchTab('estudiante')">
                &#127891; Estudiante
            </button>
        </div>

        <!-- ── Formulario Docente ── -->
        <div id="panel-docente" <?php echo $tab !== 'docente' ? 'style="display:none"' : ''; ?>>
            <form method="POST">
                <input type="hidden" name="rol" value="docente">
                <input type="email" name="email" placeholder="Correo electrónico" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit">Ingresar</button>
            </form>
        </div>

        <!-- ── Formulario Estudiante ── -->
        <div id="panel-estudiante" <?php echo $tab !== 'estudiante' ? 'style="display:none"' : ''; ?>>

            <!-- Caja informativa siempre visible para estudiantes -->
            <div class="est-info-box">
                <p>&#128273; <strong>¿Primera vez?</strong>
                   Si tu docente te registró por listado, tu contraseña inicial
                   es tu número de cédula.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="rol" value="estudiante">
                <input type="text" name="cedula"
                       placeholder="Número de cédula (ej: V-12345678)" required
                       value="<?php echo $cedula_pre ?: htmlspecialchars($_POST['cedula'] ?? ''); ?>">
                <input type="password" name="password"
                       placeholder="Contraseña" required>
                <button type="submit">Ingresar</button>
            </form>
        </div>

        <!-- Alertas -->
        <?php if (!empty($success)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-footer">
            <a href="register.php">¿No tienes cuenta? Regístrate</a>
        </div>
    </div>

    <script>
    function switchTab(rol) {
        document.getElementById('panel-docente').style.display    = rol === 'docente'    ? '' : 'none';
        document.getElementById('panel-estudiante').style.display = rol === 'estudiante' ? '' : 'none';
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-btn-' + rol).classList.add('active');
    }
    </script>
</body>
</html>
