<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['rol'] ?? '') !== 'admon') {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, nombre, ciudad, responsable, direccion, telefono FROM sedes WHERE id = ?");
    $stmt->execute([$id]);
    $sede = $stmt->fetch();
    
    if ($sede) {
        echo json_encode(['success' => true, 'data' => $sede]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Sede no encontrada']);
    }
    exit;
}

if ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    $responsable = trim($_POST['responsable'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID requerido']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE sedes SET responsable = ?, direccion = ?, telefono = ? WHERE id = ?");
    $stmt->execute([$responsable, $direccion, $telefono, $id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'toggle') {
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID requerido']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE sedes SET activa = NOT activa WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);
