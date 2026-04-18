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

$estado = $_GET['estado'] ?? '';
$sede_id = $_GET['sede_id'] ?? '';

$where = "1=1";
$params = [];
if ($estado) { $where .= " AND t.estado = ?"; $params[] = $estado; }
if ($sede_id) { $where .= " AND t.sede_id = ?"; $params[] = $sede_id; }

$sql = "SELECT t.*, s.nombre as sede_nombre, s.ciudad as sede_ciudad, u.nombre as comprador_nombre, u.apellido as comprador_apellido, d.nombre as distribuidor_nombre, d.apellido as distribuidor_apellido, t.fecha_entrega 
    FROM tickets t 
    JOIN sedes s ON t.sede_id = s.id 
    LEFT JOIN usuarios u ON t.comprador_id = u.id
    LEFT JOIN usuarios d ON t.distribuidor_id = d.id
    WHERE $where ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$sedes = $pdo->query("SELECT id, nombre FROM sedes WHERE activa = 1")->fetchAll();
$todos_distribuidores = $pdo->query("SELECT id, nombre, apellido, ciudad FROM usuarios WHERE rol = 'dist' AND activo = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - INSUMAX</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container py-4">
        <h4><i class="bi bi-ticket-detailed"></i> Gestión de Tickets</h4>
        
        <form method="GET" action="tickets.php" class="row mb-3">
            <div class="col-md-3">
                <select name="estado" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <option value="recibido" <?= $estado=='recibido'?'selected':''?>>Recibido</option>
                    <option value="proceso" <?= $estado=='proceso'?'selected':''?>>En Proceso</option>
                    <option value="pendientes" <?= $estado=='pendientes'?'selected':''?>>Con Pendientes</option>
                    <option value="finalizado" <?= $estado=='finalizado'?'selected':''?>>Finalizado</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="sede_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Todas las sedes</option>
                    <?php foreach($sedes as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $sede_id==$s['id']?'selected':''?>><?= htmlspecialchars($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
            <div class="col-md-2">
                <a href="tickets.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Sede</th>
                        <th>Ciudad</th>
                        <th>Comprador</th>
                        <th>Distribuidor</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                        <th>Entrega</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tickets as $t): ?>
                    <?php $color_ciudad = $colors['ciudades'][$t['sede_ciudad'] ?? ''] ?? '#6c757d'; ?>
                    <tr style="border-left: 3px solid <?= $color_ciudad ?>;">
                        <td><?= $t['codigo_ticket'] ?></td>
                        <td><?= htmlspecialchars($t['sede_nombre']) ?></td>
                        <td style="color: <?= $color_ciudad ?>; font-weight: 600;"><?= htmlspecialchars($t['sede_ciudad'] ?? '') ?></td>
                        <td><?= htmlspecialchars(($t['comprador_nombre'] ?? '') . ' ' . ($t['comprador_apellido'] ?? '')) ?></td>
                        <td><?= htmlspecialchars(($t['distribuidor_nombre'] ?? '') . ' ' . ($t['distribuidor_apellido'] ?? '')) ?></td>
                        <td><?= $t['fecha_pedido'] ?></td>
                        <td><span class="badge" style="background-color: <?= $colors['estados'][$t['estado']]['bg'] ?? '#eee' ?>; color: <?= $colors['estados'][$t['estado']]['text'] ?? '#333' ?>; border: 1px solid <?= $colors['estados'][$t['estado']]['text'] ?? '#666' ?>; font-weight: 600;"><?= strtoupper($t['estado']) ?></span></td>
                        <td data-ticket-id="<?= $t['id'] ?>" data-ciudad="<?= htmlspecialchars($t['sede_ciudad']) ?>">
                            <button class="btn btn-sm btn-outline-primary" onclick="verDetalle(<?= $t['id'] ?>)"><i class="bi bi-eye"></i></button>
                            <?php if (empty($t['distribuidor_id'])): ?>
                            <select class="form-select form-select-sm d-inline-block w-auto select-dist" onchange="asignarDist(<?= $t['id'] ?>, this.value)">
                                <option value="">Asignar dist</option>
                            </select>
                            <?php endif; ?>
                            <select class="form-select form-select-sm d-inline-block w-auto" onchange="cambiarEstado(<?= $t['id'] ?>, this.value)">
                                <option value="">Cambiar estado</option>
                                <option value="recibido">Recibido</option>
                                <option value="proceso">En Proceso</option>
                                <option value="pendientes">Pendientes</option>
                                <option value="finalizado">Finalizado</option>
                            </select>
                        </td>
                        <td>
                            <?php if ($t['estado'] === 'finalizado' && $t['fecha_entrega']): ?>
                            <small class="text-muted" style="font-size: 11px;"><?= date('d/m', strtotime($t['fecha_entrega'])) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modalAsignar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Asignar Distribuidor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="textoCiudad"></p>
                    <select id="selectDistribuidor" class="form-select">
                        <option value="">Seleccione distribuidor</option>
                    </select>
                    <input type="hidden" id="ticketIdSeleccionado">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="asignarDistribuidor()">Asignar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    const distribuidores = <?= json_encode($todos_distribuidores) ?>;

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('select.select-dist').forEach(function(select) {
            const td = select.closest('td');
            const ciudad = td.dataset.ciudad;
            select.innerHTML = '<option value="">Asignar dist</option>';
            const filtrados = distribuidores.filter(d => d.ciudad === ciudad);
            filtrados.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.nombre + ' ' + (d.apellido || '');
                select.appendChild(opt);
            });
            if (filtrados.length === 0) {
                const opt = document.createElement('option');
                opt.textContent = 'No hay dist en ' + ciudad;
                opt.disabled = true;
                select.appendChild(opt);
            }
        });
    });

    function asignarDist(ticketId, distribuidorId) {
        if (!distribuidorId) return;
        
        const formData = new FormData();
        formData.append('ticket_id', ticketId);
        formData.append('distribuidor_id', distribuidorId);
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
        
        fetch('../api/tickets.php?action=assign', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Error al asignar');
            }
        })
        .catch(err => alert('Error: ' + err));
    }

    function asignarDistribuidor() {
        const ticketId = document.getElementById('ticketIdSeleccionado').value;
        const distribuidorId = document.getElementById('selectDistribuidor').value;
        
        if (!distribuidorId) {
            alert('Seleccione un distribuidor');
            return;
        }
        
        const formData = new FormData();
        formData.append('ticket_id', ticketId);
        formData.append('distribuidor_id', distribuidorId);
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
        
        fetch('../api/tickets.php?action=assign', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Error al asignar');
            }
        })
        .catch(err => alert('Error: ' + err));
    }

    function verDetalle(ticketId) {
        window.location.href = 'ticket-detalle.php?id=' + ticketId;
    }

    function cambiarEstado(ticketId, estado) {
        if (!estado) return;
        
        const formData = new FormData();
        formData.append('ticket_id', ticketId);
        formData.append('estado', estado);
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
        
        fetch('../api/tickets.php?action=status', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Error al cambiar estado');
            }
        })
        .catch(err => alert('Error: ' + err));
    }
    </script>
</body>
</html>