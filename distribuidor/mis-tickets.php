<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
$colors = require __DIR__ . '/../config/colors.php';

if ($_SESSION['rol'] !== 'dist') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Tickets - INSUMAX</title>
    <link rel="icon" type="image/png" href="../assets/iconfinder.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container py-4">
        <h4><i class="bi bi-truck"></i> Mis Tickets de Entrega</h4>
        <div id="lista-tickets"></div>
    </div>

    <script>
    const coloresCiudades = <?= json_encode($colors['ciudades']) ?>;
    const coloresEstados = <?= json_encode($colors['estados']) ?>;
    
    fetch('test-api.php')
    .then(r => r.json())
    .then(data => {
        console.log(data);
        if (!data.success) {
            document.getElementById('lista-tickets').innerHTML = '<div class="alert alert-danger">Error: ' + data.error + '</div>';
            return;
        }
        
        const tickets = data.data || [];
        
        if (tickets.length === 0) {
            document.getElementById('lista-tickets').innerHTML = '<div class="alert alert-warning">No hay tickets</div>';
            return;
        }
        
        document.getElementById('lista-tickets').innerHTML = tickets.map(t => {
            const colorCiudad = coloresCiudades[t.ciudad] || '#6c757d';
            const colorEstado = coloresEstados[t.estado] || {bg: '#eee', text: '#333'};
            return `
            <div class="card ticket-card mb-3" style="border-left: 4px solid ${colorCiudad};">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5>${t.codigo_ticket}</h5>
                            <p class="mb-1"><i class="bi bi-shop"></i> ${t.sede_nombre || '-'} - <strong style="color:${colorCiudad}">${t.ciudad || ''}</strong></p>
                            <p class="mb-1"><i class="bi bi-calendar"></i> ${t.fecha_pedido}</p>
                            <p class="mb-0"><i class="bi bi-person"></i> ${t.responsable || '-'}</p>
                        </div>
                        <div>
                            <span class="badge" style="background-color: ${colorEstado.bg}; color: ${colorEstado.text}; border: 1px solid ${colorEstado.text}; font-weight: 600;">
                                ${t.estado.toUpperCase()}
                            </span>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm mt-3" onclick="verDetalle(${t.id})">
                        <i class="bi bi-check2-square"></i> Ver Checklist
                    </button>
                </div>
            </div>
        `}).join('');
    })
    .catch(err => {
        console.error(err);
        document.getElementById('lista-tickets').innerHTML = 'Error: ' + err;
    });

    let currentTicket = null;
    let currentItems = [];

    function verDetalle(ticketId) {
        fetch('../api/tickets.php?action=detail&id=' + ticketId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert(data.error); return; }
            currentTicket = data.ticket;
            currentItems = data.items;
            
            let html = `
                <div class="text-start">
                    <h5>${currentTicket.codigo_ticket}</h5>
                    <p><strong>Sede:</strong> ${currentTicket.sede_nombre}</p>
                    <p><strong>Fecha:</strong> ${currentTicket.fecha_pedido}</p>
                    <p><strong>Responsable:</strong> ${currentTicket.responsable}</p>
                    
                    <h6 class="mt-3">Items del Pedido</h6>
                    <table class="table table-sm" id="checklist-items">
                        <thead><tr><th>Producto</th><th>Pedido</th><th>Entregado</th><th>Estado</th></tr></thead>
                        <tbody>
                            ${currentItems.map(item => `
                            <tr>
                                <td>${item.descripcion}<br><small class="text-muted">${item.unidad_medida}</small></td>
                                <td>${item.cantidad_pedida}</td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" style="width:80px" 
                                        value="${item.cantidad_entregada || 0}" min="0" step="0.01"
                                        id="cant-${item.id}">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" style="width:110px" id="estado-${item.id}">
                                        <option value="pendiente" ${item.estado_item === 'pendiente' ? 'selected' : ''}>Pendiente</option>
                                        <option value="entregado" ${item.estado_item === 'entregado' ? 'selected' : ''}>Entregado</option>
                                        <option value="faltante" ${item.estado_item === 'faltante' ? 'selected' : ''}>Faltante</option>
                                    </select>
                                </td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                    
                    <div class="mt-3">
                        <button class="btn btn-primary" onclick="guardarAvance(${currentTicket.id})">
                            <i class="bi bi-save"></i> Guardar Avance
                        </button>
                        <button class="btn btn-success" onclick="confirmarEntrega(${currentTicket.id})">
                            <i class="bi bi-check2-circle"></i> Confirmar Entrega Total
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('modal-body').innerHTML = html;
            new bootstrap.Modal(document.getElementById('detailModal')).show();
        });
    }

    function guardarAvance(ticketId) {
        const updates = currentItems.map(item => ({
            item_id: item.id,
            cantidad_entregada: parseFloat(document.getElementById('cant-' + item.id).value) || 0,
            estado_item: document.getElementById('estado-' + item.id).value
        }));
        
        Promise.all(updates.map(item => {
            return fetch('../api/items.php?action=check', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'item_id=' + item.item_id + '&cantidad_entregada=' + item.cantidad_entregada + '&estado_item=' + item.estado_item
            });
        }))
        .then(() => {
            alert('Avance guardado');
            bootstrap.Modal.getInstance(document.getElementById('detailModal')).hide();
            location.reload();
        });
    }

    function confirmarEntrega(ticketId) {
        if (!confirm('¿Confirmar entrega total del pedido?')) return;
        
        fetch('../api/items.php?action=confirm', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'ticket_id=' + ticketId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Entrega confirmada');
                bootstrap.Modal.getInstance(document.getElementById('detailModal')).hide();
                location.reload();
            } else {
                alert(data.error);
            }
        });
    }
    </script>

    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Checklist de Entrega</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modal-body"></div>
            </div>
        </div>
    </div>
</body>
</html>