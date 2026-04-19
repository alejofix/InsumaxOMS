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
    <link rel="icon" type="image/png" href="../assets/iconfinder.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
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
                        <th>Usuario</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
    const ciudadesColors = <?= json_encode($colors['ciudades']) ?>;
    const estadosColors = <?= json_encode($colors['estados']) ?>;
    
    async function cargarPedidos() {
        try {
            const resp = await fetch('../api/tickets.php?action=list');
            const result = await resp.json();
            
            if (!result.success) {
                alert(result.error);
                return;
            }
            
            const tbody = document.querySelector('#tabla-pedidos tbody');
            tbody.innerHTML = result.data.map(ticket => {
                const colorCiudad = ciudadesColors[ticket.ciudad] || '#6c757d';
                const colorEstado = estadosColors[ticket.estado] || {bg: '#eee', text: '#333'};
                return `
                <tr style="border-left: 3px solid ${colorCiudad};">
                    <td><strong>${ticket.codigo_ticket}</strong></td>
                    <td>${ticket.fecha_pedido}</td>
                    <td>${ticket.responsable || '-'}</td>
                    <td><span class="badge" style="background-color: ${colorEstado.bg}; color: ${colorEstado.text}; border: 1px solid ${colorEstado.text}; font-weight: 600;">${ticket.estado.toUpperCase()}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="verDetalle(${ticket.id})">
                            <i class="bi bi-eye"></i> Ver
                        </button>
                        ${ticket.estado === 'recibido' ? `
                        <button class="btn btn-sm btn-outline-warning" onclick="editarPedido(${ticket.id})">
                            <i class="bi bi-pencil"></i> Editar
                        </button>` : ''}
                    </td>
                </tr>
            `}).join('');
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
                    <p><strong>Distribuidor:</strong> ${ticket.distribuidor_nombre ? ticket.distribuidor_nombre + ' ' + ticket.distribuidor_apellido : (ticket.estado === 'recibido' ? 'SIN ASIGNAR' : '-')}</p>
                    <p><strong>Estado:</strong> <span class="estado-badge estado-${ticket.estado}">${ticket.estado.toUpperCase()}</span></p>
                    ${ticket.observaciones ? `<p><strong>Observaciones:</strong> ${ticket.observaciones}</p>` : ''}
                    <h6 class="mt-3">Ítems:</h6>
                    <table class="table table-sm">
                        <thead><tr><th>Código</th><th>Producto</th><th>Cantidad</th><th>Estado</th></tr></thead>
                        <tbody>
                            ${items.map(item => `
                                <tr>
                                    <td>${item.codigo}</td>
                                    <td>${item.descripcion}</td>
                                    <td>${item.cantidad_pedida}</td>
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

    let editItems = [];
    
    async function editarPedido(ticketId) {
        try {
            const resp = await fetch('../api/tickets.php?action=detail&id=' + ticketId);
            const result = await resp.json();
            
            if (!result.success) {
                alert(result.error);
                return;
            }
            
            const ticket = result.ticket;
            editItems = result.items;
            
            if (ticket.estado !== 'recibido') {
                alert('Solo se pueden editar pedidos en estado recibido');
                return;
            }
            
            let html = `
                <div class="text-start">
                    <h5>Ticket: ${ticket.codigo_ticket}</h5>
                    <p><strong>Sede:</strong> ${ticket.sede_nombre} - ${ticket.ciudad}</p>
                    <p><strong>Fecha:</strong> ${ticket.fecha_pedido}</p>
                    <p class="text-muted"><small>Ingrese 0 para eliminar un ítem</small></p>
                    <table class="table table-sm">
                        <thead><tr><th>Producto</th><th>Cantidad</th></tr></thead>
                        <tbody>
                            ${editItems.map(item => `
                                <tr>
                                    <td>${item.codigo} - ${item.descripcion}</td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" 
                                            id="cant-${item.id}" value="${item.cantidad_pedida}" 
                                            min="0" step="0.01" style="width: 100px;">
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            
            document.getElementById('edit-modal-body').innerHTML = html;
            document.getElementById('edit-modal-title').textContent = 'Editar Pedido';
            document.getElementById('edit-ticket-id').value = ticketId;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        } catch (err) {
            alert('Error al cargar detalle');
        }
    }

    async function guardarEdicion() {
        const ticketId = document.getElementById('edit-ticket-id').value;
        
        const items = editItems.map(item => ({
            item_id: item.id,
            cantidad: parseFloat(document.getElementById('cant-' + item.id).value) || 0
        }));
        
        try {
            const resp = await fetch('../api/tickets.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ticket_id: parseInt(ticketId), items })
            });
            const result = await resp.json();
            
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                cargarPedidos();
            } else {
                alert(result.error);
            }
        } catch (err) {
            alert('Error al guardar');
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

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="edit-modal-title">Editar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="edit-modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarEdicion()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="edit-ticket-id">
</body>
</html>