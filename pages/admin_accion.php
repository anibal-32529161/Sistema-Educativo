<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin.php");
    exit();
}

$accion = $_POST['accion'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$activo = (int)($_POST['activo'] ?? 0);

if ($accion === 'toggle_user' && $id > 0) {
    // No permitir desactivar al propio admin
    if ($id === (int)$_SESSION['user_id']) {
        header("Location: admin.php?err=No+puedes+desactivarte+a+ti+mismo");
        exit();
    }
    $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ? AND rol != 'admin'")
        ->execute([$activo, $id]);
    $msg = $activo ? "Usuario+activado+correctamente" : "Usuario+desactivado+correctamente";
    header("Location: admin.php?ok=$msg");
    exit();
}

if ($accion === 'toggle_curso' && $id > 0) {
    $pdo->prepare("UPDATE cursos SET activo = ? WHERE id = ?")
        ->execute([$activo, $id]);
    $msg = $activo ? "Curso+activado+correctamente" : "Curso+desactivado+correctamente";
    header("Location: admin.php?ok=$msg");
    exit();
}

header("Location: admin.php?err=Accion+no+reconocida");
exit();
