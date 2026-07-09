<?php
session_start();
include '../config/db.php';

$token    = trim($_GET['token'] ?? '');
$msg_ok   = '';
$msg_err  = '';
$enlace   = null;

if ($token !== '') {
    $stmt = $pdo->prepare(
        "SELECT ea.curso_id, ea.activo,
                c.nombre AS curso_nombre, c.codigo, c.descripcion,
                CONCAT(u.nombre, ' ', u.apellido) AS docente,
                COUNT(DISTINCT m.id)  AS num_inscritos,
                COUNT(DISTINCT e.id)  AS num_eval
         FROM enlaces_acceso ea
         INNER JOIN cursos   c  ON ea.curso_id   = c.id AND c.activo = 1
         INNER JOIN usuarios u  ON c.docente_id  = u.id
         LEFT  JOIN matriculas m ON m.curso_id   = c.id
         LEFT  JOIN evaluaciones e ON e.curso_id = c.id
         WHERE ea.token = ? AND ea.activo = 1
         GROUP BY ea.curso_id, c.nombre, c.codigo, c.descripcion, u.nombre, u.apellido"
    );
    $stmt->execute([$token]);
    $enlace = $stmt->fetch();
}

$ya_inscrito  = false;
$es_estudiante = isset($_SESSION['user_id']) && $_SESSION['rol'] === 'estudiante';

if ($enlace && $es_estudiante) {
    $chk = $pdo->prepare("SELECT id FROM matriculas WHERE estudiante_id = ? AND curso_id = ?");
    $chk->execute([$_SESSION['user_id'], $enlace['curso_id']]);
    $ya_inscrito = (bool)$chk->fetch();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscribirse']) && !$ya_inscrito) {
        try {
            $pdo->prepare("INSERT INTO matriculas (estudiante_id, curso_id) VALUES (?, ?)")
                ->execute([$_SESSION['user_id'], $enlace['curso_id']]);
            $ya_inscrito = true;
            $msg_ok = '¡Inscripción exitosa! Ya puedes ver el curso en tu portal.';
        } catch (PDOException $e) {
            $msg_err = 'Error al inscribirse. Intenta de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso a Curso — GestiónAcad</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .acceso-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
            position: relative;
            z-index: 1;
        }
        .acceso-card {
            background: rgba(15,23,42,.92);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 20px;
            padding: 40px 36px;
            width: 100%;
            max-width: 480px;
            backdrop-filter: blur(14px);
        }
        .acceso-brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .acceso-brand-icon { font-size: 2.2rem; }
        .acceso-brand h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #f8fafc;
            margin: 6px 0 2px;
        }
        .acceso-brand p { font-size: .82rem; color: #64748b; margin: 0; }

        .curso-card {
            background: linear-gradient(135deg,#0f4c2a,#065f46);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 22px;
        }
        .curso-card-nombre {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 4px;
        }
        .curso-card-codigo {
            display: inline-block;
            background: rgba(255,255,255,.15);
            border-radius: 6px;
            padding: 2px 10px;
            font-size: .75rem;
            color: #a7f3d0;
            margin-bottom: 12px;
        }
        .curso-card-desc {
            font-size: .85rem;
            color: #d1fae5;
            margin: 0 0 16px;
            line-height: 1.5;
        }
        .curso-card-chips {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .curso-card-chip {
            background: rgba(255,255,255,.12);
            border-radius: 20px;
            padding: 4px 14px;
            font-size: .78rem;
            color: #ecfdf5;
        }
        .docente-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 22px;
        }
        .docente-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg,#059669,#7c3aed);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; font-weight: 700; color: #fff;
            flex-shrink: 0;
        }
        .docente-info { display: flex; flex-direction: column; }
        .docente-info span:first-child {
            font-size: .82rem; color: #64748b;
        }
        .docente-info strong {
            font-size: .95rem; color: #e2e8f0;
        }
        .btn-inscribir {
            display: block; width: 100%;
            background: linear-gradient(135deg,#059669,#0d9488);
            color: #fff; border: none; border-radius: 12px;
            padding: 14px; font-size: 1rem; font-weight: 600;
            cursor: pointer; text-align: center;
            box-shadow: 0 4px 20px rgba(5,150,105,.35);
            transition: opacity .2s, transform .15s;
            text-decoration: none;
        }
        .btn-inscribir:hover { opacity: .88; transform: translateY(-1px); }
        .ya-inscrito-box {
            text-align: center;
            background: rgba(5,150,105,.12);
            border: 1px solid rgba(5,150,105,.3);
            border-radius: 12px;
            padding: 20px;
        }
        .ya-inscrito-box .check { font-size: 2rem; margin-bottom: 8px; }
        .ya-inscrito-box p { color: #6ee7b7; margin: 0 0 14px; font-size: .95rem; }
        .no-session-box {
            text-align: center;
            background: rgba(124,58,237,.1);
            border: 1px solid rgba(124,58,237,.25);
            border-radius: 12px;
            padding: 22px;
        }
        .no-session-box p { color: #c4b5fd; margin: 0 0 14px; font-size: .9rem; }
        .error-box {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .error-box .error-icon { font-size: 3rem; margin-bottom: 12px; }
        .error-box h2 { color: #f8fafc; margin: 0 0 8px; }
    </style>
</head>
<body>
    <video autoplay muted loop playsinline id="bg-video">
        <source src="../assets/images/6.mp4" type="video/mp4">
    </video>
    <div class="video-overlay"></div>

    <div class="acceso-wrap">
        <div class="acceso-card">

            <div class="acceso-brand">
                <div class="acceso-brand-icon">&#127979;</div>
                <h1>GestiónAcad</h1>
                <p>Acceso a curso por enlace</p>
            </div>

            <?php if (!$enlace): ?>
            <!-- Token inválido o expirado -->
            <div class="error-box">
                <div class="error-icon">&#128683;</div>
                <h2>Enlace inválido</h2>
                <p>Este enlace no existe, fue desactivado o ya expiró.<br>
                   Solicita uno nuevo a tu docente.</p>
                <a href="login.php" class="btn-inscribir" style="margin-top:20px; display:inline-block; width:auto; padding:12px 28px;">
                    Ir al inicio
                </a>
            </div>

            <?php else: ?>
            <!-- Información del curso -->
            <div class="curso-card">
                <p class="curso-card-nombre"><?php echo htmlspecialchars($enlace['curso_nombre']); ?></p>
                <?php if ($enlace['codigo']): ?>
                    <span class="curso-card-codigo"><?php echo htmlspecialchars($enlace['codigo']); ?></span>
                <?php endif; ?>
                <?php if ($enlace['descripcion']): ?>
                    <p class="curso-card-desc"><?php echo htmlspecialchars($enlace['descripcion']); ?></p>
                <?php endif; ?>
                <div class="curso-card-chips">
                    <span class="curso-card-chip">&#127891; <?php echo $enlace['num_inscritos']; ?> inscritos</span>
                    <span class="curso-card-chip">&#128221; <?php echo $enlace['num_eval']; ?> evaluaciones</span>
                </div>
            </div>

            <div class="docente-badge">
                <div class="docente-avatar">
                    <?php echo mb_strtoupper(mb_substr($enlace['docente'], 0, 1)); ?>
                </div>
                <div class="docente-info">
                    <span>Docente a cargo</span>
                    <strong>Prof. <?php echo htmlspecialchars($enlace['docente']); ?></strong>
                </div>
            </div>

            <?php if (!empty($msg_ok)): ?>
                <div class="alert-success" style="margin-bottom:18px;">&#10003; <?php echo $msg_ok; ?></div>
            <?php endif; ?>
            <?php if (!empty($msg_err)): ?>
                <div class="alert-error" style="margin-bottom:18px;"><?php echo htmlspecialchars($msg_err); ?></div>
            <?php endif; ?>

            <?php if ($es_estudiante): ?>
                <?php if ($ya_inscrito): ?>
                <div class="ya-inscrito-box">
                    <div class="check">&#9989;</div>
                    <p>Ya estás inscrito en este curso.</p>
                    <a href="dashboard_estudiante.php" class="btn-inscribir">
                        Ir a mi portal &#8594;
                    </a>
                </div>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="inscribirse" value="1">
                    <button type="submit" class="btn-inscribir">
                        &#43; Inscribirme en este curso
                    </button>
                </form>
                <?php endif; ?>

            <?php else: ?>
            <!-- No hay sesión de estudiante -->
            <div class="no-session-box">
                <p>Inicia sesión como estudiante para inscribirte en este curso.</p>
                <a href="login.php?rol=estudiante" class="btn-inscribir">
                    &#128274; Iniciar sesión
                </a>
                <div style="margin-top:12px;">
                    <a href="register.php" style="color:#a78bfa; font-size:.85rem;">
                        ¿No tienes cuenta? Regístrate
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</body>
</html>
