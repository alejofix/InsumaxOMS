<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireAuth();

if ($_SESSION['rol'] !== 'admon') {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM insumos WHERE activo = 1 ORDER BY FIELD(grupo, 'carnes', 'quesos', 'plaza', 'salsas', 'varios', 'aseo'), descripcion");
$insumos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insumos - INSUMAX</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4><i class="bi bi-box-seam"></i> Catálogo de Insumos</h4>
            <button class="btn btn-insumax" onclick="mostrarForm()"><i class="bi bi-plus-circle"></i> Nuevo Insumo</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Grupo</th>
                        <th>Descripción</th>
                        <th>Unidad</th>
                        <th>Precio Compra</th>
                        <th>Precio Venta</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($insumos as $i): ?>
                    <tr>
                        <td><?= htmlspecialchars($i['codigo'] ?? '') ?></td>
                        <td><span class="badge bg-secondary"><?= strtoupper($i['grupo']) ?></span></td>
                        <td><?= htmlspecialchars($i['descripcion']) ?></td>
                        <td><?= htmlspecialchars($i['unidad_medida']) ?></td>
                        <td><?= $i['precio_compra'] ? number_format($i['precio_compra'], 0, ',', '.') : '-' ?></td>
                        <td><?= $i['precio_venta'] ? number_format($i['precio_venta'], 0, ',', '.') : '-' ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editar(<?= $i['id'] ?>)"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="eliminar(<?= $i['id'] ?>)"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>