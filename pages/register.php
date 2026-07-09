<?php
include '../config/db.php';
$success      = "";
$error        = "";
$cedula_ya_existe = false;
$rol          = $_POST['rol'] ?? 'docente';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rol      = $_POST['rol'] ?? 'docente';
    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $password_raw = $_POST['password']  ?? '';

    // Convertir vacío a NULL siempre
    $email  = trim($_POST['email']  ?? '') ?: null;
    $cedula = trim($_POST['cedula'] ?? '') ?: null;

    // ── Validaciones ─────────────────────────────────
    if ($nombre === '' || $apellido === '') {
        $error = "Nombre y apellido son obligatorios.";
    } elseif ($rol === 'docente' && $email === null) {
        $error = "El correo electrónico es obligatorio para docentes.";
    } elseif ($rol === 'estudiante' && $cedula === null) {
        $error = "La cédula de identidad es obligatoria.";
    } elseif (strlen($password_raw) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        $password = password_hash($password_raw, PASSWORD_DEFAULT);

        try {
            if ($rol === 'docente') {
                $stmt = $pdo->prepare(
                    "INSERT INTO usuarios (rol, nombre, apellido, email, password)
                     VALUES ('docente', ?, ?, ?, ?)"
                );
                $stmt->execute([$nombre, $apellido, $email, $password]);
            } else {
                // Estudiantes: sin campo email para evitar conflictos de UNIQUE
                $stmt = $pdo->prepare(
                    "INSERT INTO usuarios (rol, nombre, apellido, cedula, password)
                     VALUES ('estudiante', ?, ?, ?, ?)"
                );
                $stmt->execute([$nombre, $apellido, $cedula, $password]);
            }
            header("Location: login.php?registered=1&rol=" . urlencode($rol));
            exit();

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $msg = $e->getMessage();
                // Detectar cuál campo está duplicado según el nombre de la clave
                if (strpos($msg, 'uq_cedula') !== false || strpos($msg, 'cedula') !== false) {
                    $cedula_ya_existe = true;
                    $error = "dup_cedula";
                } elseif (strpos($msg, 'uq_email') !== false || strpos($msg, 'email') !== false) {
                    $error = "Ese correo ya está registrado en el sistema. "
                           . "Usa uno diferente o inicia sesión.";
                } else {
                    $error = "Algunos de tus datos ya están registrados.";
                }
            } else {
                $error = "Error al registrar. Intenta de nuevo.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro — GestiónAcad</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <video autoplay muted loop playsinline id="bg-video">
        <source src="../assets/images/6.mp4" type="video/mp4">
    </video>
    <div class="video-overlay"></div>

    <div class="form-container" style="width:360px;">
        <div class="brand">
            <span class="brand-icon">&#127979;</span>
            <h1>GestiónAcad</h1>
            <p class="brand-sub">Crear cuenta nueva</p>
        </div>

        <div class="tab-group">
            <button type="button" id="btn-docente"
                    class="tab-btn <?php echo $rol === 'docente' ? 'active' : ''; ?>"
                    onclick="switchRol('docente')">&#128203; Docente</button>
            <button type="button" id="btn-estudiante"
                    class="tab-btn <?php echo $rol === 'estudiante' ? 'active' : ''; ?>"
                    onclick="switchRol('estudiante')">&#127891; Estudiante</button>
        </div>

        <?php if ($error === 'dup_cedula'): ?>
        <!-- ── Cédula ya existe: ofrecer ir al login ── -->
        <div class="dup-cedula-box">
            <div class="dup-cedula-icon">&#128100;</div>
            <p class="dup-cedula-title">Ya tienes una cuenta</p>
            <p class="dup-cedula-msg">
                La cédula <strong><?php echo htmlspecialchars($_POST['cedula'] ?? ''); ?></strong>
                ya está registrada en el sistema.
                Tu docente puede haberte cargado automáticamente.
            </p>
            <p class="dup-cedula-hint">
                &#128273; Tu contraseña inicial es tu número de cédula.
            </p>
            <a href="login.php?rol=estudiante&cedula=<?php echo urlencode($_POST['cedula'] ?? ''); ?>"
               class="btn-primary" style="display:block; text-align:center; margin-top:14px;">
                Ir a Iniciar Sesión &#8594;
            </a>
            <div class="form-footer" style="margin-top:12px;">
                <a href="register.php">Registrarme con otra cédula</a>
            </div>
        </div>

        <?php else: ?>
        <!-- ── Formulario normal ── -->
        <form method="POST" id="form-registro" onsubmit="return validar()">
            <input type="hidden" name="rol" id="input-rol"
                   value="<?php echo htmlspecialchars($rol); ?>">

            <input type="text" name="nombre" placeholder="Nombre" required
                   value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">

            <input type="text" name="apellido" placeholder="Apellido" required
                   value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>">

            <!-- SOLO docentes -->
            <div id="campo-email"
                 <?php echo $rol === 'estudiante' ? 'style="display:none"' : ''; ?>>
                <input type="email" name="email" id="input-email"
                       placeholder="Correo electrónico"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <!-- SOLO estudiantes -->
            <div id="campo-cedula"
                 <?php echo $rol !== 'estudiante' ? 'style="display:none"' : ''; ?>>
                <input type="text" name="cedula" id="input-cedula"
                       placeholder="Cédula (ej: V-12345678)"
                       value="<?php echo htmlspecialchars($_POST['cedula'] ?? ''); ?>">
            </div>

            <input type="password" name="password"
                   placeholder="Contraseña (mín. 6 caracteres)"
                   minlength="6" required>

            <button type="submit">Crear Cuenta</button>
        </form>

        <?php if (!empty($error) && $error !== 'dup_cedula'): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="form-footer">
            <a href="login.php">¿Ya tienes cuenta? Inicia sesión</a>
        </div>
    </div>

    <script>
    function switchRol(rol) {
        document.getElementById('input-rol').value = rol;

        const divEmail  = document.getElementById('campo-email');
        const divCedula = document.getElementById('campo-cedula');
        const inEmail   = document.getElementById('input-email');
        const inCedula  = document.getElementById('input-cedula');

        if (rol === 'estudiante') {
            divEmail.style.display  = 'none';
            divCedula.style.display = '';
            inEmail.required  = false;
            inCedula.required = true;
        } else {
            divEmail.style.display  = '';
            divCedula.style.display = 'none';
            inEmail.required  = true;
            inCedula.required = false;
        }

        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('btn-' + rol).classList.add('active');
    }

    function validar() {
        const rol = document.getElementById('input-rol').value;
        if (rol === 'estudiante') {
            const ced = document.getElementById('input-cedula').value.trim();
            if (!ced) { alert('Ingresa tu número de cédula.'); return false; }
        } else {
            const mail = document.getElementById('input-email').value.trim();
            if (!mail) { alert('Ingresa tu correo electrónico.'); return false; }
        }
        return true;
    }

    // Aplicar `required` correcto al cargar
    (function() {
        const rol = document.getElementById('input-rol').value;
        if (rol === 'estudiante') {
            document.getElementById('input-cedula').required = true;
            document.getElementById('input-email').required  = false;
        } else {
            document.getElementById('input-email').required  = true;
            document.getElementById('input-cedula').required = false;
        }
    })();
    </script>
</body>
</html>
