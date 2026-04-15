<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireAuth();

if ($_SESSION['rol'] !== 'admon') {
    header('Location: ../bienvenido.php');
    exit;
}

$estado = $_GET['estado'] ?? '';
$sede_id = $_GET['sede_id'] ?? '';

$where = "1=1";
$params = [];
if ($estado) { $where .= " AND t.estado = ?"; $params[] = $estado; }
if ($sede_id) { $where .= " AND t.sede_id = ?"; $params[] = $sede_id; }

$sql = "SELECT t.*, s.nombre as sede_nombre, u.nombre as comprador_nombre, d.nombre as distribuidor_nombre 
    FROM tickets t 
    JOIN sedes s ON t.sede_id = s.id 
    LEFT JOIN usuarios u ON t.comprador_id = u.id
    LEFT JOIN usuarios d ON t.distribuidor_id = d.id
    WHERE $where ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$sedes = $pdo->query("SELECT id, nombre FROM sedes WHERE activa = 1")->fetchAll();
$distribuidores = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol = 'dist' AND activo = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - INSUMAX</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container py-4">
        <h4><i class="bi bi-ticket-detailed"></i> Gestión de Tickets</h4>
        
        <form method="GET" class="row mb-3">
            <div class="col-md-3">
                <select name="estado" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="recibido" <?= $estado=='recibido'?'selected':''?>>Recibido</option>
                    <option value="proceso" <?= $estado=='proceso'?'selected':''?>>En Proceso</option>
                    <option value="pendientes" <?= $estado=='pendientes'?'selected':''?>>Con Pendientes</option>
                    <option value="finalizado" <?= $estado=='finalizado'?'selected':''?>>Finalizado</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="sede_id" class="form-select">
                    <option value="">Todas las sedes</option>
                    <?php foreach($sedes as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $sede_id==$s['id']?'selected':''?>><?= htmlspecialchars($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Sede</th>
                        <th>Comprador</th>
                        <th>Distribuidor</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tickets as $t): ?>
                    <tr>
                        <td><?= $t['codigo_ticket'] ?></td>
                        <td><?= htmlspecialchars($t['sede_nombre']) ?></td>
                        <td><?= htmlspecialchars($t['comprador_nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($t['distribuidor_nombre'] ?? '<span class="text-muted">Sin asignar</span>') ?></td>
                        <td><?= $t['fecha_pedido'] ?></td>
                        <td><span class="badge bg-<?= $t['estado']=='finalizado'?'success':($t['estado']=='pendientes'?'danger':($t['estado']=='proceso'?'warning':'primary')) ?>"><?= strtoupper($t['estado']) ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="verDetalle(<?= $t['id'] ?>)"><i class="bi bi-eye"></i></button>
                            <select class="form-select form-select-sm d-inline-block w-auto" onchange="cambiarEstado(<?= $t['id'] ?>, this.value)">
                                <option value="">Cambiar estado</option>
                                <option value="recibido">Recibido</option>
                                <option value="proceso">En Proceso</option>
                                <option value="pendientes">Pendientes</option>
                                <option value="finalizado">Finalizado</option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>