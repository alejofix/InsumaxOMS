<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
requireAuth();

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $grupo = $_GET['grupo'] ?? null;
    $ciudad_id = isset($_GET['ciudad_id']) ? intval($_GET['ciudad_id']) : null;
    
    if ($ciudad_id) {
        if ($grupo) {
            $stmt = $pdo->prepare("
                SELECT i.*, ip.precio_compra, ip.precio_venta
                FROM insumos i
                LEFT JOIN insumos_precios ip ON i.id = ip.insumo_id AND ip.ciudad_id = ?
                WHERE i.activo = 1 AND i.grupo = ?
                ORDER BY FIELD(i.grupo, 'carnes', 'quesos', 'plaza', 'salsas', 'varios', 'aseo'), i.descripcion
            ");
            $stmt->execute([$ciudad_id, $grupo]);
        } else {
            $stmt = $pdo->prepare("
                SELECT i.*, ip.precio_compra, ip.precio_venta
                FROM insumos i
                LEFT JOIN insumos_precios ip ON i.id = ip.insumo_id AND ip.ciudad_id = ?
                WHERE i.activo = 1
                ORDER BY FIELD(i.grupo, 'carnes', 'quesos', 'plaza', 'salsas', 'varios', 'aseo'), i.descripcion
            ");
            $stmt->execute([$ciudad_id]);
        }
    } else {
        if ($grupo) {
            $stmt = $pdo->prepare("SELECT * FROM insumos WHERE activo = 1 AND grupo = ? ORDER BY descripcion");
            $stmt->execute([$grupo]);
        } else {
            $stmt = $pdo->query("SELECT * FROM insumos WHERE activo = 1 ORDER BY FIELD(grupo, 'carnes', 'quesos', 'plaza', 'salsas', 'varios', 'aseo'), descripcion");
        }
    }
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    $ciudad_id = isset($_GET['ciudad_id']) ? intval($_GET['ciudad_id']) : null;
    
    $stmt = $pdo->prepare("SELECT * FROM insumos WHERE id = ?");
    $stmt->execute([$id]);
    $insumo = $stmt->fetch();
    
    if ($insumo && $ciudad_id) {
        $stmt = $pdo->prepare("SELECT precio_compra, precio_venta FROM insumos_precios WHERE insumo_id = ? AND ciudad_id = ?");
        $stmt->execute([$id, $ciudad_id]);
        $precios = $stmt->fetch();
        if ($precios) {
            $insumo['precio_compra'] = $precios['precio_compra'];
            $insumo['precio_venta'] = $precios['precio_venta'];
        }
    }
    
    echo json_encode(['success' => true, 'data' => $insumo]);
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
    $ciudad_id = isset($_POST['ciudad_id']) ? intval($_POST['ciudad_id']) : null;
    
    if (empty($grupo) || empty($descripcion) || empty($unidad_medida)) {
        echo json_encode(['success' => false, 'error' => 'Datos requeridos']);
        exit;
    }
    
    if (!$ciudad_id) {
        echo json_encode(['success' => false, 'error' => 'Ciudad es requerida']);
        exit;
    }
    
    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE insumos SET codigo=?, grupo=?, descripcion=?, unidad_medida=? WHERE id=?");
            $stmt->execute([$codigo, $grupo, $descripcion, $unidad_medida, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO insumos (codigo, grupo, descripcion, unidad_medida, activo) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$codigo, $grupo, $descripcion, $unidad_medida]);
            $id = $pdo->lastInsertId();
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO insumos_precios (insumo_id, ciudad_id, precio_compra, precio_venta)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE precio_compra = VALUES(precio_compra), precio_venta = VALUES(precio_venta)
        ");
        $stmt->execute([$id, $ciudad_id, $precio_compra, $precio_venta]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
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

if ($action === 'ciudades') {
    requireRole('admon');
    $stmt = $pdo->query("SELECT id, nombre FROM ciudades WHERE activa = 1 ORDER BY nombre");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);
