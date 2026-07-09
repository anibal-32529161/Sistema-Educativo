<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$user_id = $_SESSION['user_id'];
$rol     = $_SESSION['rol'];
$msg_ok  = "";
$msg_err = "";

// ── Cargar datos actuales ───────────────────────────────────
$stUser = $pdo->prepare("SELECT nombre, apellido, email, cedula FROM usuarios WHERE id = ?");
$stUser->execute([$user_id]);
$user = $stUser->fetch();

// ── Guardar cambios ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $cedula   = trim($_POST['cedula']   ?? '');

    if ($nombre === '' || $apellido === '') {
        $msg_err = "Nombre y apellido son obligatorios.";
    } else {
        if ($rol === 'docente') {
            // Verificar email único (excepto el propio)
            $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $chk->execute([$email, $user_id]);
            if ($chk->fetch()) {
                $msg_err = "Ese correo electrónico ya está en uso por otra cuenta.";
            } else {
                $pdo->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, email = ? WHERE id = ?")
                    ->execute([$nombre, $apellido, $email, $user_id]);
                $_SESSION['nombre'] = $nombre . ' ' . $apellido;
                $msg_ok = "Perfil actualizado correctamente.";
                $user['nombre']   = $nombre;
                $user['apellido'] = $apellido;
                $user['email']    = $email;
            }
        } else {
            // Estudiante: puede editar nombre, apellido y cédula
            $chk = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ? AND id != ?");
            $chk->execute([$cedula, $user_id]);
            if ($chk->fetch()) {
                $msg_err = "Esa cédula ya está registrada en otra cuenta.";
            } else {
                $pdo->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, cedula = ? WHERE id = ?")
                    ->execute([$nombre, $apellido, $cedula, $user_id]);
                $_SESSION['nombre'] = $nombre . ' ' . $apellido;
                $msg_ok = "Perfil actualizado correctamente.";
                $user['nombre']   = $nombre;
                $user['apellido'] = $apellido;
                $user['cedula']   = $cedula;
            }
        }
    }
}

$dashLink = $rol === 'docente' ? 'dashboard_docente.php' : 'dashboard_estudiante.php';
$inicial  = mb_strtoupper(mb_substr($user['nombre'], 0, 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil — GestiónAcad</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper <?php echo $rol === 'estudiante' ? 'dash-est' : ''; ?>">

    <header class="dash-header">
        <span class="logo">&#127979; GestiónAcad</span>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                <span class="badge-rol badge-<?php echo $rol; ?>"><?php echo ucfirst($rol); ?></span>
            </span>
            <a href="<?php echo $dashLink; ?>" class="btn-logout" style="background:#1e3a5f; margin-right:6px;">&#127968; Dashboard</a>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <main class="dash-main">

        <a href="<?php echo $dashLink; ?>" style="color:#5c9cf5; font-size:.88rem; text-decoration:none; display:inline-block; margin-bottom:20px;">
            &#8592; Volver al panel
        </a>

        <div class="perfil-card">
            <div class="perfil-avatar"><?php echo $inicial; ?></div>
            <p class="perfil-title"><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido']); ?></p>
            <p class="perfil-sub">
                <span class="badge-rol badge-<?php echo $rol; ?>"><?php echo ucfirst($rol); ?></span>
            </p>

            <?php if ($msg_ok): ?>
                <div class="alert-success" style="margin-bottom:16px;">&#10003; <?php echo $msg_ok; ?></div>
            <?php endif; ?>
            <?php if ($msg_err): ?>
                <div class="alert-error" style="margin-bottom:16px;"><?php echo htmlspecialchars($msg_err); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="perfil-field">
                    <label>Nombre</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>"
                           required maxlength="80">
                </div>
                <div class="perfil-field">
                    <label>Apellido</label>
                    <input type="text" name="apellido" value="<?php echo htmlspecialchars($user['apellido']); ?>"
                           required maxlength="80">
                </div>

                <?php if ($rol === 'docente'): ?>
                <div class="perfil-field">
                    <label>Correo electrónico</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                           maxlength="150">
                </div>
                <?php else: ?>
                <div class="perfil-field">
                    <label>Cédula</label>
                    <input type="text" name="cedula" value="<?php echo htmlspecialchars($user['cedula'] ?? ''); ?>"
                           maxlength="20">
                </div>
                <?php endif; ?>

                <div class="perfil-actions">
                    <button type="submit" class="btn-primary" style="flex:1;">
                        &#128190; Guardar cambios
                    </button>
                    <a href="cambiar_contrasena.php" class="btn-perfil-sec">
                        &#128274; Cambiar contraseña
                    </a>
                </div>
            </form>
        </div>

    </main>
</div>
</body>
</html>
