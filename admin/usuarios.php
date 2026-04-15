<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireAuth();

if ($_SESSION['rol'] !== 'admon') {
    header('Location: ../bienvenido.php');
    exit;
}

$stmt = $pdo->query("SELECT u.*, s.nombre as sede_nombre FROM usuarios u LEFT JOIN sedes s ON u.sede_id = s.id WHERE u.activo = 1 ORDER BY u.nombre");
$usuarios = $stmt->fetchAll();
$sedes = $pdo->query("SELECT id, nombre FROM sedes WHERE activa = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - INSUMAX</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4><i class="bi bi-people"></i> Gestión de Usuarios</h4>
            <button class="btn btn-insumax" onclick="mostrarForm()"><i class="bi bi-person-plus"></i> Nuevo Usuario</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Celular</th>
                        <th>Rol</th>
                        <th>Sede</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre'] . ' ' . ($u['apellido'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['celular'] ?? $u['telefono'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= $u['rol']=='admon'?'danger':($u['rol']=='dist'?'primary':'success') ?>"><?= strtoupper($u['rol']) ?></span></td>
                        <td><?= htmlspecialchars($u['sede_nombre'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editar(<?= $u['id'] ?>)"><i class="bi bi-pencil"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>