<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'create') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        exit;
    }
    
    $token = $input['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    if (empty($token) || $token !== $sessionToken) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
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
    
    // Verificar que el usuario esté activo
    $stmt_check = $pdo->prepare("SELECT activo FROM usuarios WHERE id = ?");
    $stmt_check->execute([$user_id]);
    $user = $stmt_check->fetch();
    
    if (!$user || $user['activo'] != 1) {
        echo json_encode(['success' => false, 'error' => 'Usuario inactivo. Contacte al administrador.']);
        exit;
    }
    
    // Generar código ticket: INS-{sede}-{YYYYMMDD}-{secuencial}
    $dateStr = date('Ymd');
    $sedeIdStr = str_pad($sede_id, 3, '0', STR_PAD_LEFT);
    $likePattern = 'INS-' . $sedeIdStr . '-' . $dateStr . '-%';
    $stmt = $pdo->prepare("SELECT codigo_ticket FROM tickets WHERE sede_id = ? AND codigo_ticket LIKE ?");
    $stmt->execute([$sede_id, $likePattern]);
    $results = $stmt->fetchAll();
    $seq = 1;
    if ($results) {
        $maxSeq = 0;
        foreach ($results as $row) {
            $parts = explode('-', $row['codigo_ticket']);
            $s = (int)end($parts);
            if ($s > $maxSeq) $maxSeq = $s;
        }
        $seq = $maxSeq + 1;
    }
    $codigo_ticket = 'INS-' . $sedeIdStr . '-' . $dateStr . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO tickets (codigo_ticket, sede_id, comprador_id, responsable, observaciones, estado, fecha_pedido) 
            VALUES (?, ?, ?, ?, ?, 'recibido', ?)");
        $stmt->execute([$codigo_ticket, $sede_id, $user_id, $responsable, $observaciones, $fecha_pedido]);
        $ticket_id = $pdo->lastInsertId();
        
        $stmt_ciudad = $pdo->prepare("SELECT ciudad_id FROM sedes WHERE id = ?");
        $stmt_ciudad->execute([$sede_id]);
        $sede = $stmt_ciudad->fetch();
        $ciudad_id = $sede['ciudad_id'] ?? null;

        $stmt_precio = null;
        if ($ciudad_id) {
            $stmt_precio = $pdo->prepare("SELECT precio_venta FROM insumos_precios WHERE insumo_id = ? AND ciudad_id = ?");
        }
        $stmt_ins = $pdo->prepare("INSERT INTO ticket_items (ticket_id, insumo_id, cantidad_pedida, precio_unitario, estado_item) 
            VALUES (?, ?, ?, ?, 'pendiente')");
        
        foreach ($items as $item) {
            $precio = 0;
            if ($stmt_precio) {
                $stmt_precio->execute([$item['insumo_id'], $ciudad_id]);
                $precio_row = $stmt_precio->fetch();
                if ($precio_row && $precio_row['precio_venta']) {
                    $precio = $precio_row['precio_venta'];
                }
            }
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
    
    try {
        if ($rol === 'comprador') {
            $stmt = $pdo->prepare("SELECT t.*, s.nombre as sede_nombre, s.ciudad,
                d.nombre as distribuidor_nombre, d.apellido as distribuidor_apellido
                FROM tickets t 
                JOIN sedes s ON t.sede_id = s.id 
                LEFT JOIN usuarios d ON t.distribuidor_id = d.id
                WHERE t.comprador_id = ? AND s.activa = 1
                ORDER BY t.created_at DESC");
            $stmt->execute([$user_id]);
        } elseif ($rol === 'dist') {
            $stmt = $pdo->prepare("SELECT t.*, s.nombre as sede_nombre, s.ciudad 
                FROM tickets t 
                JOIN sedes s ON t.sede_id = s.id 
                WHERE t.distribuidor_id = ? AND s.activa = 1
                ORDER BY t.created_at DESC");
            $stmt->execute([$user_id]);
        } else {
            $stmt = $pdo->prepare("SELECT t.*, s.nombre as sede_nombre, s.ciudad, 
                u.nombre as comprador_nombre, u.apellido as comprador_apellido,
                d.nombre as distribuidor_nombre, d.apellido as distribuidor_apellido
                FROM tickets t 
                JOIN sedes s ON t.sede_id = s.id
                JOIN usuarios u ON t.comprador_id = u.id
                LEFT JOIN usuarios d ON t.distribuidor_id = d.id
                ORDER BY t.created_at DESC");
            $stmt->execute();
        }
        
        $tickets = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $tickets]);
    } catch (PDOException $e) {
        error_log("Error fetching tickets: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error fetching tickets']);
    }
    exit;
}

if ($action === 'detail') {
    requireAuth();
    
    $ticket_id = $_GET['id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT t.*, s.nombre as sede_nombre, s.ciudad as ciudad, s.direccion,
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
    
    $stmt = $pdo->prepare("SELECT ti.*, i.codigo, i.descripcion, i.grupo, i.unidad_medida, ti.precio_unitario
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
    
    $stmt = $pdo->prepare("SELECT s.ciudad FROM tickets t JOIN sedes s ON t.sede_id = s.id WHERE t.id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        echo json_encode(['success' => false, 'error' => 'Ticket no encontrado']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT ciudad FROM usuarios WHERE id = ? AND rol = 'dist' AND activo = 1");
    $stmt->execute([$distribuidor_id]);
    $dist = $stmt->fetch();
    
    if (!$dist) {
        echo json_encode(['success' => false, 'error' => 'Distribuidor no válido']);
        exit;
    }
    
    if ($dist['ciudad'] !== $ticket['ciudad']) {
        echo json_encode(['success' => false, 'error' => 'El distribuidor no pertenece a la ciudad de la sede']);
        exit;
    }
    
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
    
    if ($estado === 'recibido') {
        $stmt = $pdo->prepare("UPDATE tickets SET estado = 'recibido', distribuidor_id = NULL WHERE id = ?");
        $stmt->execute([$ticket_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE tickets SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $ticket_id]);
    }
    
    if ($estado === 'finalizado') {
        $stmt = $pdo->prepare("UPDATE tickets SET fecha_entrega = NOW() WHERE id = ?");
        $stmt->execute([$ticket_id]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'update') {
    requireAuth();
    
    if ($_SESSION['rol'] !== 'comprador') {
        echo json_encode(['success' => false, 'error' => 'Sin acceso']);
        exit;
    }
    
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        exit;
    }
    
    $ticket_id = $input['ticket_id'] ?? 0;
    $items = $input['items'] ?? [];
    
    if (!$ticket_id || empty($items)) {
        echo json_encode(['success' => false, 'error' => 'Datos requeridos']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, comprador_id, estado FROM tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        echo json_encode(['success' => false, 'error' => 'Ticket no encontrado']);
        exit;
    }
    
    if ($ticket['comprador_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'Sin acceso']);
        exit;
    }
    
    if ($ticket['estado'] !== 'recibido') {
        echo json_encode(['success' => false, 'error' => 'Solo se pueden editar pedidos en estado recibido']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        foreach ($items as $item) {
            $item_id = $item['item_id'] ?? 0;
            $cantidad = floatval($item['cantidad']);
            
            if ($cantidad <= 0) {
                $stmt_del = $pdo->prepare("DELETE FROM ticket_items WHERE id = ? AND ticket_id = ?");
                $stmt_del->execute([$item_id, $ticket_id]);
            } else {
                $stmt_upd = $pdo->prepare("UPDATE ticket_items SET cantidad_pedida = ? WHERE id = ? AND ticket_id = ?");
                $stmt_upd->execute([$cantidad, $item_id, $ticket_id]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Error al actualizar: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);