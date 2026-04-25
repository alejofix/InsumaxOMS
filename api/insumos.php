<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
error_log("API insumos - Action: $action, GET: " . print_r($_GET, true) . ", POST: " . print_r($_POST, true));

if ($action === 'unidades') {
    if ($_SESSION['rol'] !== 'admon') {
        echo json_encode(['success' => false, 'error' => 'Acceso prohibido']);
        exit;
    }
    $stmt = $pdo->query("SELECT * FROM unidades_medida WHERE activo = 1 ORDER BY tipo, codigo");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'list') {
    $grupo = $_GET['grupo'] ?? null;
    $ciudad_id = isset($_GET['ciudad_id']) ? intval($_GET['ciudad_id']) : null;
    
    $sql = "
        SELECT 
            i.*, 
            ip.precio_compra, 
            ip.precio_venta,
            ip.updated_at,
            u.unidad_compra,
            u.unidad_base,
            u.factor_conversion,
            u.presentacion,
            ROUND(ip.precio_compra / NULLIF(u.factor_conversion, 0) * 1000, 2) AS precio_kg,
            ROUND(ip.precio_compra / NULLIF(u.factor_conversion, 0), 2) AS precio_por_unidad
        FROM insumos i
        LEFT JOIN insumos_precios ip ON i.id = ip.insumo_id AND ip.ciudad_id = ?
        LEFT JOIN insumos_unidades u ON i.id = u.insumo_id
    ";
    
    $params = [$ciudad_id];
    
    if ($grupo) {
        $sql .= " WHERE i.grupo = ?";
        $params[] = $grupo;
    }
    
    $sql .= " ORDER BY i.activo DESC, FIELD(i.grupo, 'carnes', 'quesos', 'plaza', 'salsas', 'varios', 'aseo'), i.descripcion";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    $ciudad_id = isset($_GET['ciudad_id']) ? intval($_GET['ciudad_id']) : null;
    
    $stmt = $pdo->prepare("
        SELECT i.*, 
            u.unidad_compra,
            u.unidad_base,
            u.factor_conversion,
            u.presentacion
        FROM insumos i
        LEFT JOIN insumos_unidades u ON i.id = u.insumo_id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $insumo = $stmt->fetch();
    
    if ($insumo && $ciudad_id) {
        $stmt = $pdo->prepare("SELECT precio_compra, precio_venta FROM insumos_precios WHERE insumo_id = ? AND ciudad_id = ?");
        $stmt->execute([$id, $ciudad_id]);
        $precios = $stmt->fetch();
        if ($precios) {
            $insumo['precio_compra'] = $precios['precio_compra'];
            $insumo['precio_venta'] = $precios['precio_venta'];
            $insumo['precio_kg'] = $insumo['factor_conversion'] > 0 
                ? round($precios['precio_compra'] / $insumo['factor_conversion'] * 1000, 2) 
                : null;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $insumo]);
    exit;
}

if ($action === 'save') {
    if ($_SESSION['rol'] !== 'admon') {
        echo json_encode(['success' => false, 'error' => 'Acceso prohibido']);
        exit;
    }
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
        exit;
    }
    
    $id = $_POST['id'] ?? null;
    $codigo = trim($_POST['codigo'] ?? '');
    $grupo = $_POST['grupo'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $unidad_compra = trim($_POST['unidad_compra'] ?? '');
    $unidad_base = trim($_POST['unidad_base'] ?? 'G');
    $factor_conversion = floatval($_POST['factor_conversion'] ?? 1);
    $presentacion = trim($_POST['presentacion'] ?? '');
    $precio_compra = $_POST['precio_compra'] ?? null;
    $precio_venta = $_POST['precio_venta'] ?? null;
    $ciudad_id = isset($_POST['ciudad_id']) ? intval($_POST['ciudad_id']) : null;
    
    if (empty($grupo) || empty($descripcion) || empty($unidad_compra)) {
        echo json_encode(['success' => false, 'error' => 'Datos requeridos: grupo, descripcion, unidad_compra']);
        exit;
    }
    
    if (!$ciudad_id) {
        echo json_encode(['success' => false, 'error' => 'Ciudad es requerida']);
        exit;
    }
    
    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE insumos SET codigo=?, grupo=?, descripcion=? WHERE id=?");
            $stmt->execute([$codigo, $grupo, $descripcion, $id]);
            
            $stmt = $pdo->prepare("
                UPDATE insumos_unidades 
                SET unidad_compra=?, unidad_base=?, factor_conversion=?, presentacion=?
                WHERE insumo_id=?
            ");
            $stmt->execute([$unidad_compra, $unidad_base, $factor_conversion, $presentacion ?: $descripcion, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO insumos (codigo, grupo, descripcion, activo) VALUES (?, ?, ?, 1)");
            $stmt->execute([$codigo, $grupo, $descripcion]);
            $id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("
                INSERT INTO insumos_unidades (insumo_id, unidad_compra, unidad_base, factor_conversion, presentacion)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id, $unidad_compra, $unidad_base, $factor_conversion, $presentacion ?: $descripcion]);
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
    if ($_SESSION['rol'] !== 'admon') {
        echo json_encode(['success' => false, 'error' => 'Acceso prohibido']);
        exit;
    }
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
        exit;
    }
    
    $id = $_POST['id'] ?? 0;
    $stmt = $pdo->prepare("UPDATE insumos SET activo = 0 WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'toggle') {
    error_log("TOGGLE action - POST: " . print_r($_POST, true));
    
    if ($_SESSION['rol'] !== 'admon') {
        echo json_encode(['success' => false, 'error' => 'Acceso prohibido']);
        exit;
    }
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
        exit;
    }
    
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID requerido']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE insumos SET activo = NOT activo WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'guardar_precio_compra') {
    if ($_SESSION['rol'] !== 'dist') {
        echo json_encode(['success' => false, 'error' => 'Acceso prohibido']);
        exit;
    }
    
    $insumo_id = intval($_POST['insumo_id'] ?? 0);
    $ciudad_id = intval($_POST['ciudad_id'] ?? 0);
    $precio_compra = floatval($_POST['precio_compra'] ?? 0);
    $ciudad_dist = $_SESSION['ciudad'] ?? '';
    
    if (!$insumo_id || !$ciudad_id) {
        echo json_encode(['success' => false, 'error' => 'Insumo y ciudad requeridos']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT nombre FROM ciudades WHERE id = ?");
    $stmt->execute([$ciudad_id]);
    $ciudad = $stmt->fetch();
    
    if ($ciudad['nombre'] !== $ciudad_dist) {
        echo json_encode(['success' => false, 'error' => 'Solo puedes modificar precios de tu ciudad']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO insumos_precios (insumo_id, ciudad_id, precio_compra)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE precio_compra = VALUES(precio_compra)
        ");
        $stmt->execute([$insumo_id, $ciudad_id, $precio_compra]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'ciudades') {
    if ($_SESSION['rol'] !== 'admon') {
        echo json_encode(['success' => false, 'error' => 'Acceso prohibido']);
        exit;
    }
    $stmt = $pdo->query("SELECT id, nombre FROM ciudades WHERE activa = 1 ORDER BY nombre");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'conversion') {
    $insumo_id = intval($_GET['insumo_id'] ?? 0);
    $cantidad = floatval($_GET['cantidad'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT u.factor_conversion, u.unidad_base, u.unidad_compra, i.descripcion
        FROM insumos_unidades u
        JOIN insumos i ON u.insumo_id = i.id
        WHERE u.insumo_id = ?
    ");
    $stmt->execute([$insumo_id]);
    $data = $stmt->fetch();
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Insumo no encontrado']);
        exit;
    }
    
    $gramos = $cantidad * $data['factor_conversion'];
    $kg = round($gramos / 1000, 3);
    $ml = round($gramos, 2);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'insumo' => $data['descripcion'],
            'cantidad_original' => $cantidad,
            'unidad_compra' => $data['unidad_compra'],
            'factor' => $data['factor_conversion'],
            'gramos' => $gramos,
            'kg' => $kg,
            'ml' => $ml,
            'unidad_base' => $data['unidad_base']
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);
