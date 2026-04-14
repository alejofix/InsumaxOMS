<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/csrf.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'create') {
    requireCsrf();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        exit;
    }
    
    $responsable = trim($input['responsable'] ?? '');
    $fecha_pedido = $input['fecha_pedido'] ?? date('Y-m-d');
    $observaciones = trim($input['observaciones'] ?? '');
    $items = $input['items'] ?? [];
    
    if (empty($responsable) || empty($items)) {
        echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos']);
        exit;
    }
    
    $sede_id = $_SESSION['sede_id'] ?? 1;
    $user_id = $_SESSION['user_id'];
    
    // Generar código ticket: INS-YYYYMMDD-XXX
    $dateStr = date('Ymd');
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(codigo_ticket, 13, 3) AS UNSIGNED)) as max_num 
        FROM tickets WHERE fecha_pedido = CURDATE()");
    $stmt->execute();
    $result = $stmt->fetch();
    $seq = ($result['max_num'] ?? 0) + 1;
    $codigo_ticket = 'INS-' . $dateStr . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO tickets (codigo_ticket, sede_id, comprador_id, responsable, observaciones, estado, fecha_pedido) 
            VALUES (?, ?, ?, ?, ?, 'recibido', ?)");
        $stmt->execute([$codigo_ticket, $sede_id, $user_id, $responsable, $observaciones, $fecha_pedido]);
        $ticket_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("SELECT precio_venta FROM insumos WHERE id = ?");
        $stmt_ins = $pdo->prepare("INSERT INTO ticket_items (ticket_id, insumo_id, cantidad_pedida, precio_unitario, estado_item) 
            VALUES (?, ?, ?, ?, 'pendiente')");
        
        foreach ($items as $item) {
            $stmt->execute([$item['insumo_id']]);
            $insumo = $stmt->fetch();
            $precio = $insumo['precio_venta'] ?? 0;
            $stmt_ins->execute([$ticket_id, $item['insumo_id'], $item['cantidad'], $precio]);
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'ticket_id' => $ticket_id, 'codigo_ticket' => $codigo_ticket]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Error al crear ticket: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'list') {
    requireAuth();
    
    $user_id = $_SESSION['user_id'];
    $rol = $_SESSION['rol'];
    
    if ($rol === 'comprador') {
        $stmt = $pdo->prepare("SELECT t.*, s.nombre as sede_nombre, s.ciudad 
            FROM tickets t 
            JOIN sedes s ON t.sede_id = s.id 
            WHERE t.comprador_id = ? 
            ORDER BY t.created_at DESC");
        $stmt->execute([$user_id]);
    } elseif ($rol === 'dist') {
        $stmt = $pdo->prepare("SELECT t.*, s.nombre as sede_nombre, s.ciudad 
            FROM tickets t 
            JOIN sedes s ON t.sede_id = s.id 
            WHERE t.distribuidor_id = ? 
            ORDER BY t.created_at DESC");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT t.*, s.nombre as sede_nombre, s.ciudad, 
            u.nombre as comprador_nombre, u.apellido as comprador_apellido,
            d.nombre as distribuidor_nombre
            FROM tickets t 
            JOIN sedes s ON t.sede_id = s.id
            JOIN usuarios u ON t.comprador_id = u.id
            LEFT JOIN usuarios d ON t.distribuidor_id = d.id
            ORDER BY t.created_at DESC");
        $stmt->execute();
    }
    
    $tickets = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $tickets]);
    exit;
}

if ($action === 'detail') {
    requireAuth();
    
    $ticket_id = $_GET['id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT t.*, s.nombre as sede_nombre, s.ciudad, s.direccion,
        u.nombre as comprador_nombre, u.apellido as comprador_apellido, u.celular,
        d.nombre as distribuidor_nombre, d.apellido as distribuidor_apellido
        FROM tickets t
        JOIN sedes s ON t.sede_id = s.id
        JOIN usuarios u ON t.comprador_id = u.id
        LEFT JOIN usuarios d ON t.distribuidor_id = d.id
        WHERE t.id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        echo json_encode(['success' => false, 'error' => 'Ticket no encontrado']);
        exit;
    }
    
    // Validar acceso
    if ($_SESSION['rol'] === 'comprador' && $ticket['comprador_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'Sin acceso']);
        exit;
    }
    if ($_SESSION['rol'] === 'dist' && $ticket['distribuidor_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'Sin acceso']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT ti.*, i.descripcion, i.grupo, i.unidad_medida, i.precio_venta
        FROM ticket_items ti
        JOIN insumos i ON ti.insumo_id = i.id
        WHERE ti.ticket_id = ?");
    $stmt->execute([$ticket_id]);
    $items = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'ticket' => $ticket, 'items' => $items]);
    exit;
}

if ($action === 'assign') {
    requireCsrf();
    requireRole('admon');
    
    $ticket_id = $_POST['ticket_id'] ?? 0;
    $distribuidor_id = $_POST['distribuidor_id'] ?? 0;
    
    $stmt = $pdo->prepare("UPDATE tickets SET distribuidor_id = ?, estado = 'proceso' WHERE id = ?");
    $stmt->execute([$distribuidor_id, $ticket_id]);
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'status') {
    requireCsrf();
    requireRole('admon');
    
    $ticket_id = $_POST['ticket_id'] ?? 0;
    $estado = $_POST['estado'] ?? '';
    
    if (!in_array($estado, ['recibido', 'proceso', 'pendientes', 'finalizado'])) {
        echo json_encode(['success' => false, 'error' => 'Estado inválido']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE tickets SET estado = ? WHERE id = ?");
    $stmt->execute([$estado, $ticket_id]);
    
    if ($estado === 'finalizado') {
        $stmt = $pdo->prepare("UPDATE tickets SET fecha_entrega = NOW() WHERE id = ?");
        $stmt->execute([$ticket_id]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);