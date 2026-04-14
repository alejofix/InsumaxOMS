<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
requireAuth();

$action = $_GET['action'] ?? '';

if ($action === 'check') {
    $item_id = $_POST['item_id'] ?? 0;
    $cantidad_entregada = $_POST['cantidad_entregada'] ?? null;
    $estado_item = $_POST['estado_item'] ?? 'pendiente';
    $observacion = $_POST['observacion'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE ticket_items SET cantidad_entregada = ?, estado_item = ?, observacion = ? WHERE id = ?");
    $stmt->execute([$cantidad_entregada, $estado_item, $observacion, $item_id]);
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'save') {
    $ticket_id = $_POST['ticket_id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT id, cantidad_pedida, cantidad_entregada, estado_item FROM ticket_items WHERE ticket_id = ?");
    $stmt->execute([$ticket_id]);
    $items = $stmt->fetchAll();
    
    $has_faltantes = false;
    foreach ($items as $item) {
        if ($item['estado_item'] === 'faltante' || ($item['cantidad_entregada'] ?? 0) < $item['cantidad_pedida']) {
            $has_faltantes = true;
            break;
        }
    }
    
    $new_estado = $has_faltantes ? 'pendientes' : 'proceso';
    $stmt = $pdo->prepare("UPDATE tickets SET estado = ? WHERE id = ?");
    $stmt->execute([$new_estado, $ticket_id]);
    
    echo json_encode(['success' => true, 'estado' => $new_estado]);
    exit;
}

if ($action === 'confirm') {
    $ticket_id = $_POST['ticket_id'] ?? 0;
    
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE ticket_items SET cantidad_entregada = cantidad_pedida, estado_item = 'entregado' 
        WHERE ticket_id = ? AND (cantidad_entregada IS NULL OR cantidad_entregada < cantidad_pedida)");
    $stmt->execute([$ticket_id]);
    
    $stmt = $pdo->prepare("UPDATE tickets SET estado = 'finalizado', fecha_entrega = NOW() WHERE id = ?");
    $stmt->execute([$ticket_id]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'comprobante') {
    requireRole('dist');
    
    $ticket_id = $_POST['ticket_id'] ?? 0;
    
    if (!isset($_FILES['comprobante'])) {
        echo json_encode(['success' => false, 'error' => 'Sin archivo']);
        exit;
    }
    
    $file = $_FILES['comprobante'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido']);
        exit;
    }
    
    $filename = 'comprobante_' . $ticket_id . '_' . time() . '.' . $ext;
    $target = __DIR__ . '/../uploads/' . $filename;
    
    if (!is_dir(__DIR__ . '/../uploads')) {
        mkdir(__DIR__ . '/../uploads');
    }
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        $stmt = $pdo->prepare("UPDATE tickets SET observaciones = CONCAT(COALESCE(observaciones, ''), '\n[Comprobante: ", $filename, "]') WHERE id = ?");
        $stmt->execute([$ticket_id]);
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al subir archivo']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);