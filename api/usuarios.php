<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

header('Content-Type: application/json');
requireAuth();

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $rol = $_GET['rol'] ?? null;
    
    if ($rol) {
        $stmt = $pdo->prepare("SELECT id, nombre, apellido, email, celular, rol, sede_id, ciudad, activo FROM usuarios WHERE rol = ? ORDER BY nombre");
        $stmt->execute([$rol]);
    } else {
        $stmt = $pdo->query("SELECT id, nombre, apellido, email, celular, rol, sede_id, ciudad, activo FROM usuarios ORDER BY nombre");
    }
    
    $usuarios = $stmt->fetchAll();
    
    // Agregar nombre de sede
    $stmt = $pdo->query("SELECT id, nombre FROM sedes");
    $sedes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($usuarios as &$u) {
        $u['sede_nombre'] = $sedes[$u['sede_id']] ?? null;
    }
    
    echo json_encode(['success' => true, 'data' => $usuarios]);
    exit;
}

if ($action === 'save') {
    requireCsrf();
    requireRole('admon');
    
    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $celular = trim($_POST['celular'] ?? '');
    $rol = $_POST['rol'] ?? 'comprador';
    $sede_id = $_POST['sede_id'] ?? null;
    $ciudad = trim($_POST['ciudad'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($nombre) || empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Nombre y email requeridos']);
        exit;
    }
    
    if ($id) {
        // Update
        $sql = "UPDATE usuarios SET nombre=?, apellido=?, email=?, celular=?, rol=?, sede_id=?, ciudad=?";
        $params = [$nombre, $apellido, $email, $celular, $rol, $sede_id, $ciudad];
        
        if (!empty($password)) {
            $sql .= ", password_hash=?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        $sql .= " WHERE id=?";
        $params[] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        // Create
        if (empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Password requerido']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, celular, password_hash, rol, sede_id, ciudad, activo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$nombre, $apellido, $email, $celular, password_hash($password, PASSWORD_DEFAULT), $rol, $sede_id, $ciudad]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    requireCsrf();
    requireRole('admon');
    
    $id = $_POST['id'] ?? 0;
    $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'toggle') {
    requireCsrf();
    requireRole('admon');
    
    $id = $_POST['id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("SELECT activo FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            exit;
        }
        
        $nuevoEstado = $usuario['activo'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
        $stmt->execute([$nuevoEstado, $id]);
        
        echo json_encode(['success' => true, 'activo' => $nuevoEstado]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'changePassword') {
    requireCsrf();
    requireRole('admon');
    
    $id = $_POST['id'] ?? 0;
    $password = $_POST['password'] ?? '';
    
    if (empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Password requerido']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);