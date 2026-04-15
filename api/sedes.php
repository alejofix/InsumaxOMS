<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
requireAuth();

$action = $_GET['action'] ?? '';

if ($action === 'ciudades') {
    $stmt = $pdo->query("SELECT id, nombre FROM ciudades WHERE activa = 1 ORDER BY nombre");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT s.*, c.nombre as ciudad_nombre 
        FROM sedes s 
        LEFT JOIN ciudades c ON s.ciudad_id = c.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $sede = $stmt->fetch();
    
    if ($sede) {
        echo json_encode(['success' => true, 'data' => $sede]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Sede no encontrada']);
    }
    exit;
}

if ($action === 'save') {
    requireCsrf();
    requireRole('admon');
    
    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');
    $ciudad_id = intval($_POST['ciudad_id'] ?? 0);
    $responsable = trim($_POST['responsable'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    
    if (empty($nombre) || !$ciudad_id) {
        echo json_encode(['success' => false, 'error' => 'Nombre y ciudad son requeridos']);
        exit;
    }
    
    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE sedes SET nombre=?, ciudad_id=?, responsable=?, direccion=?, telefono=? WHERE id=?");
            $stmt->execute([$nombre, $ciudad_id, $responsable, $direccion, $telefono, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO sedes (nombre, ciudad_id, responsable, direccion, telefono, activa) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$nombre, $ciudad_id, $responsable, $direccion, $telefono]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'update') {
    requireRole('admon');
    
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
    requireRole('admon');
    
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
