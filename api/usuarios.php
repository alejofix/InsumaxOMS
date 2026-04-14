<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
requireAuth();

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $rol = $_GET['rol'] ?? null;
    
    if ($rol) {
        $stmt = $pdo->prepare("SELECT id, nombre, apellido, email, celular, rol, sede_id, activo FROM usuarios WHERE rol = ? ORDER BY nombre");
        $stmt->execute([$rol]);
    } else {
        $stmt = $pdo->query("SELECT id, nombre, apellido, email, celular, rol, sede_id, activo FROM usuarios ORDER BY nombre");
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
    $password = $_POST['password'] ?? '';
    
    if (empty($nombre) || empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Nombre y email requeridos']);
        exit;
    }
    
    if ($id) {
        // Update
        $sql = "UPDATE usuarios SET nombre=?, apellido=?, email=?, celular=?, rol=?, sede_id=?";
        $params = [$nombre, $apellido, $email, $celular, $rol, $sede_id];
        
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
        
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, celular, password_hash, rol, sede_id, activo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$nombre, $apellido, $email, $celular, password_hash($password, PASSWORD_DEFAULT), $rol, $sede_id]);
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

echo json_encode(['success' => false, 'error' => 'Acción no válida']);