<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

$msg_ok  = '';
$msg_err = '';
$rol     = $_SESSION['rol'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual   = $_POST['actual']   ?? '';
    $nueva    = $_POST['nueva']    ?? '';
    $confirma = $_POST['confirma'] ?? '';

    if ($actual === '' || $nueva === '' || $confirma === '') {
        $msg_err = 'Todos los campos son obligatorios.';
    } elseif (strlen($nueva) < 6) {
        $msg_err = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif ($nueva !== $confirma) {
        $msg_err = 'La nueva contraseña y la confirmación no coinciden.';
    } else {
        $stUser = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stUser->execute([$_SESSION['user_id']]);
        $hash = $stUser->fetchColumn();

        if (!password_verify($actual, $hash)) {
            $msg_err = 'La contraseña actual es incorrecta.';
        } else {
            $nuevo_hash = password_hash($nueva, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?")
                ->execute([$nuevo_hash, $_SESSION['user_id']]);
            $msg_ok = '¡Contraseña actualizada exitosamente!';
        }
    }
}

$dash = $rol === 'docente' ? 'dashboard_docente.php' : 'dashboard_estudiante.php';
$bgClass = $rol === 'estudiante' ? 'dash-wrapper dash-est' : 'dash-wrapper';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña — GestiónAcad</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .cp-card {
            max-width: 420px;
            margin: 0 auto;
            background: rgba(15,23,42,.85);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 20px;
            padding: 36px 32px;
            backdrop-filter: blur(10px);
        }
        .cp-icon { font-size: 2.5rem; text-align: center; margin-bottom: 8px; }
        .cp-title {
            font-size: 1.3rem; font-weight: 700; color: #f8fafc;
            text-align: center; margin: 0 0 4px;
        }
        .cp-sub { font-size: .85rem; color: #64748b; text-align: center; margin: 0 0 28px; }
        .cp-label {
            display: block; font-size: .8rem; color: #94a3b8;
            margin-bottom: 5px; font-weight: 500;
        }
        .cp-group { margin-bottom: 16px; }
        .cp-rules {
            font-size: .75rem; color: #475569; margin-top: 20px;
            background: rgba(255,255,255,.04);
            border-radius: 10px; padding: 12px 14px;
            line-height: 1.7;
        }
        .cp-back { color:#5c9cf5; font-size:.85rem; text-decoration:none; display:block; text-align:center; margin-top:16px; }
        .cp-back:hover { color:#93c5fd; }
    </style>
</head>
<body>
<div class="<?php echo $bgClass; ?>">

    <header class="dash-header">
        <span class="logo">&#127979; GestiónAcad</span>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                <span class="badge-rol badge-<?php echo $rol; ?>"><?php echo ucfirst($rol); ?></span>
            </span>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </header>

    <main class="dash-main">

        <div class="cp-card">
            <div class="cp-icon">&#128274;</div>
            <p class="cp-title">Cambiar Contraseña</p>
            <p class="cp-sub">Actualiza tu contraseña de acceso a GestiónAcad</p>

            <?php if ($msg_ok): ?>
                <div class="alert-success" style="margin-bottom:20px;">&#10003; <?php echo $msg_ok; ?></div>
            <?php endif; ?>
            <?php if ($msg_err): ?>
                <div class="alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($msg_err); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="cp-group">
                    <label class="cp-label">Contraseña actual</label>
                    <input type="password" name="actual"
                           placeholder="Tu contraseña vigente" required>
                </div>
                <div class="cp-group">
                    <label class="cp-label">Nueva contraseña</label>
                    <input type="password" name="nueva" id="nueva"
                           placeholder="Mínimo 6 caracteres" minlength="6" required>
                </div>
                <div class="cp-group">
                    <label class="cp-label">Confirmar nueva contraseña</label>
                    <input type="password" name="confirma"
                           placeholder="Repite la nueva contraseña" minlength="6" required>
                </div>

                <button type="submit" style="margin-top:8px;">Actualizar contraseña</button>
            </form>

            <div class="cp-rules">
                &#128161; <strong>Recomendaciones:</strong><br>
                &bull; Mínimo 6 caracteres.<br>
                &bull; Usa letras, números y símbolos.<br>
                &bull; No compartas tu contraseña con nadie.
            </div>

            <a href="<?php echo $dash; ?>" class="cp-back">&#8592; Volver al panel</a>
        </div>

    </main>
</div>
</body>
</html>
