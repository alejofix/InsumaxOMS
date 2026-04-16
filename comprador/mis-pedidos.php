<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
$colors = require __DIR__ . '/../config/colors.php';
requireAuth();

if ($_SESSION['rol'] !== 'comprador') {
    header('Location: ../login.php');
    exit;
}

$estadosStyle = '';
foreach ($colors['estados'] as $estado => $config) {
    $estadosStyle .= ".estado-{$estado} { background: {$config['bg']}; color: {$config['text']}; } ";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos - INSUMAX</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .estado-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        <?= $estadosStyle ?>
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container py-4">
        <h4><i class="bi bi-list-check"></i> Mis Pedidos</h4>
        
        <div class="table-responsive">
            <table class="table table-hover" id="tabla-pedidos">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Fecha</th>
                        <th>Responsable</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
    async function cargarPedidos() {
        try {
            const resp = await fetch('../api/tickets.php?action=list');
            const result = await resp.json();
            
            if (!result.success) {
                alert(result.error);
                return;
            }
            
            const tbody = document.querySelector('#tabla-pedidos tbody');
            tbody.innerHTML = result.data.map(ticket => `
                <tr>
                    <td><strong>${ticket.codigo_ticket}</strong></td>
                    <td>${ticket.fecha_pedido}</td>
                    <td>${ticket.responsable || '-'}</td>
                    <td><span class="estado-badge estado-${ticket.estado}">${ticket.estado.toUpperCase()}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="verDetalle(${ticket.id})">
                            <i class="bi bi-eye"></i> Ver
                        </button>
                    </td>
                </tr>
            `).join('');
        } catch (err) {
            console.error(err);
        }
    }

    async function verDetalle(ticketId) {
        try {
            const resp = await fetch('../api/tickets.php?action=detail&id=' + ticketId);
            const result = await resp.json();
            
            if (!result.success) {
                alert(result.error);
                return;
            }
            
            const ticket = result.ticket;
            const items = result.items;
            
            let html = `
                <div class="text-start">
                    <h5>Ticket: ${ticket.codigo_ticket}</h5>
                    <p><strong>Sede:</strong> ${ticket.sede_nombre} - ${ticket.ciudad}</p>
                    <p><strong>Fecha:</strong> ${ticket.fecha_pedido}</p>
                    <p><strong>Responsable:</strong> ${ticket.responsable}</p>
                    <p><strong>Estado:</strong> <span class="estado-badge estado-${ticket.estado}">${ticket.estado.toUpperCase()}</span></p>
                    ${ticket.observaciones ? `<p><strong>Observaciones:</strong> ${ticket.observaciones}</p>` : ''}
                    <h6 class="mt-3">Ítems:</h6>
                    <table class="table table-sm">
                        <thead><tr><th>Producto</th><th>Cantidad</th><th>Estado</th></tr></thead>
                        <tbody>
                            ${items.map(item => `
                                <tr>
                                    <td>${item.descripcion}</td>
                                    <td>${item.cantidad_pedida} ${item.unidad_medida}</td>
                                    <td>${item.estado_item}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            
            document.getElementById('modal-body').innerHTML = html;
            document.getElementById('modal-title').textContent = 'Detalle del Pedido';
            new bootstrap.Modal(document.getElementById('detailModal')).show();
        } catch (err) {
            alert('Error al cargar detalle');
        }
    }

    cargarPedidos();
    </script>

    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">Detalle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>