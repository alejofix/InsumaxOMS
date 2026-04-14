<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
requireAuth();

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $grupo = $_GET['grupo'] ?? null;
    
    if ($grupo) {
        $stmt = $pdo->prepare("SELECT * FROM insumos WHERE activo = 1 AND grupo = ? ORDER BY descripcion");
        $stmt->execute([$grupo]);
    } else {
        $stmt = $pdo->query("SELECT * FROM insumos WHERE activo = 1 ORDER BY FIELD(grupo, 'carnes', 'quesos', 'plaza', 'salsas', 'varios', 'aseo'), descripcion");
    }
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'save') {
    requireCsrf();
    requireRole('admon');
    
    $id = $_POST['id'] ?? null;
    $codigo = trim($_POST['codigo'] ?? '');
    $grupo = $_POST['grupo'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $unidad_medida = trim($_POST['unidad_medida'] ?? '');
    $precio_compra = $_POST['precio_compra'] ?? null;
    $precio_venta = $_POST['precio_venta'] ?? null;
    
    if (empty($grupo) || empty($descripcion) || empty($unidad_medida)) {
        echo json_encode(['success' => false, 'error' => 'Datos requeridos']);
        exit;
    }
    
    if ($id) {
        $stmt = $pdo->prepare("UPDATE insumos SET codigo=?, grupo=?, descripcion=?, unidad_medida=?, precio_compra=?, precio_venta=? WHERE id=?");
        $stmt->execute([$codigo, $grupo, $descripcion, $unidad_medida, $precio_compra, $precio_venta, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO insumos (codigo, grupo, descripcion, unidad_medida, precio_compra, precio_venta, activo) 
            VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$codigo, $grupo, $descripcion, $unidad_medida, $precio_compra, $precio_venta]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    requireCsrf();
    requireRole('admon');
    
    $id = $_POST['id'] ?? 0;
    $stmt = $pdo->prepare("UPDATE insumos SET activo = 0 WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);