<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
$colors = require __DIR__ . '/../config/colors.php';
requireAuth();

if ($_SESSION['rol'] !== 'admon') {
    header('Location: ../login.php');
    exit;
}

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
    die('Ticket no encontrado');
}

$stmt = $pdo->prepare("SELECT ti.*, i.codigo, i.descripcion, i.unidad_medida, ti.precio_unitario
    FROM ticket_items ti
    JOIN insumos i ON ti.insumo_id = i.id
    WHERE ti.ticket_id = ?");
$stmt->execute([$ticket_id]);
$items = $stmt->fetchAll();

$total = array_sum(array_column($items, 'precio_unitario'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Ticket - INSUMAX</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="bi bi-ticket-detailed"></i> Detalle Ticket</h4>
            <a href="tickets.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">Información</h5></div>
                    <div class="card-body">
                        <p><strong>Código:</strong> <?= htmlspecialchars($ticket['codigo_ticket']) ?></p>
                        <p><strong>Sede:</strong> <?= htmlspecialchars($ticket['sede_nombre']) ?> (<?= htmlspecialchars($ticket['ciudad']) ?>)</p>
                        <p><strong>Comprador:</strong> <?= htmlspecialchars($ticket['comprador_nombre'] . ' ' . $ticket['comprador_apellido']) ?></p>
                        <p><strong>Celular:</strong> <?= htmlspecialchars($ticket['celular'] ?? '-') ?></p>
                        <p><strong>Distribuidor:</strong> <?= $ticket['distribuidor_nombre'] ? htmlspecialchars($ticket['distribuidor_nombre'] . ' ' . $ticket['distribuidor_apellido']) : '<span class="text-muted">Sin asignar</span>' ?></p>
                        <p><strong>Responsable:</strong> <?= htmlspecialchars($ticket['responsable']) ?></p>
                        <p><strong>Fecha:</strong> <?= $ticket['fecha_pedido'] ?></p>
                        <p><strong>Estado:</strong> <span class="badge" style="background-color: <?= $colors['estados'][$ticket['estado']]['bg'] ?? '#6c757d' ?>; color: <?= $colors['estados'][$ticket['estado']]['text'] ?? '#fff' ?>;"><?= strtoupper($ticket['estado']) ?></span></p>
                        <?php if ($ticket['observaciones']): ?>
                        <p><strong>Observaciones:</strong> <?= htmlspecialchars($ticket['observaciones']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <h5>Items</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Insumo</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Total</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['codigo']) ?></td>
                        <td><?= htmlspecialchars($item['descripcion']) ?></td>
                        <td><?= $item['cantidad_pedida'] ?> <?= htmlspecialchars($item['unidad_medida']) ?></td>
                        <td>$<?= number_format($item['precio_unitario'], 0, ',', '.') ?></td>
                        <td>$<?= number_format($item['cantidad_pedida'] * $item['precio_unitario'], 0, ',', '.') ?></td>
                        <td><?= strtoupper($item['estado_item']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>