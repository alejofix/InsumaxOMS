<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireAuth();

if ($_SESSION['rol'] !== 'admon') {
    header('Location: ../bienvenido.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM sedes WHERE activa = 1 ORDER BY nombre");
$sedes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sedes - INSUMAX</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4><i class="bi bi-shop"></i> Gestión de Sedes</h4>
            <button class="btn btn-insumax" onclick="mostrarForm()"><i class="bi bi-plus-circle"></i> Nueva Sede</button>
        </div>

        <div class="row">
            <?php foreach($sedes as $s): ?>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5><?= htmlspecialchars($s['nombre']) ?></h5>
                        <p class="mb-1"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($s['ciudad']) ?></p>
                        <p class="mb-1"><i class="bi bi-pin-map"></i> <?= htmlspecialchars($s['direccion'] ?? 'Sin dirección') ?></p>
                        <p class="mb-0"><i class="bi bi-telephone"></i> <?= htmlspecialchars($s['telefono'] ?? 'Sin teléfono') ?></p>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>