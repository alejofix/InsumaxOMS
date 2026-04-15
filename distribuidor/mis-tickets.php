<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireAuth();

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
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .ticket-card { border-left: 4px solid #1a5fb4; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container py-4">
        <h4><i class="bi bi-truck"></i> Mis Tickets de Entrega</h4>
        
        <div id="lista-tickets"></div>
    </div>

    <script>
    async function cargarTickets() {
        const resp = await fetch('../api/tickets.php?action=list');
        const r = await resp.json();
        
        if (!r.success) {
            document.getElementById('lista-tickets').innerHTML = '<div class="alert alert-warning">No hay tickets asignados</div>';
            return;
        }
        
        const tickets = r.data;
        
        if (tickets.length === 0) {
            document.getElementById('lista-tickets').innerHTML = '<div class="alert alert-info">No tiene tickets asignados</div>';
            return;
        }
        
        document.getElementById('lista-tickets').innerHTML = tickets.map(t => `
            <div class="card ticket-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5>${t.codigo_ticket}</h5>
                            <p class="mb-1"><i class="bi bi-shop"></i> ${t.sede_nombre || '-'} - ${t.ciudad || ''}</p>
                            <p class="mb-1"><i class="bi bi-calendar"></i> ${t.fecha_pedido}</p>
                            <p class="mb-0"><i class="bi bi-person"></i> ${t.responsable || '-'}</p>
                        </div>
                        <div>
                            <span class="badge bg-${t.estado === 'finalizado' ? 'success' : t.estado === 'pendientes' ? 'danger' : t.estado === 'proceso' ? 'warning' : 'primary'}">
                                ${t.estado.toUpperCase()}
                            </span>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm mt-3" onclick="abrirChecklist(${t.id})">
                        <i class="bi bi-check2-square"></i> Ver Checklist
                    </button>
                </div>
            </div>
        `).join('');
    }

    async function abrirChecklist(ticketId) {
        const resp = await fetch('../api/tickets.php?action=detail&id=' + ticketId);
        const r = await resp.json();
        
        if (!r.success) { alert(r.error); return; }
        
        const t = r.ticket;
        const items = r.items;
        
        let html = `
            <div class="text-start">
                <h5>${t.codigo_ticket}</h5>
                <p><strong>Sede:</strong> ${t.sede_nombre}</p>
                <p><strong>Fecha:</strong> ${t.fecha_pedido}</p>
                <p><strong>Responsable:</strong> ${t.responsable}</p>
                
                <h6 class="mt-3">Items del Pedido</h6>
                <table class="table table-sm" id="checklist-items">
                    <thead><tr><th>Producto</th><th>Pedido</th><th>Entregado</th><th>Estado</th></tr></thead>
                    <tbody>
                        ${items.map((item, idx) => `
                        <tr>
                            <td>${item.descripcion}<br><small class="text-muted">${item.unidad_medida}</small></td>
                            <td>${item.cantidad_pedida}</td>
                            <td>
                                <input type="number" class="form-control form-control-sm" style="width:80px" 
                                    value="${item.cantidad_entregada || 0}" min="0" step="0.01"
                                    data-item-id="${item.id}" data-cantidad-pedida="${item.cantidad_pedida}">
                            </td>
                            <td>
                                <select class="form-select form-select-sm" style="width:110px" data-item-id="${item.id}">
                                    <option value="pendiente" ${item.estado_item === 'pendiente' ? 'selected' : ''}>Pendiente</option>
                                    <option value="entregado" ${item.estado_item === 'entregado' ? 'selected' : ''}>Entregado</option>
                                    <option value="faltante" ${item.estado_item === 'faltante' ? 'selected' : ''}>Faltante</option>
                                </select>
                            </td>
                        </tr>`).join('')}
                    </tbody>
                </table>
                
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="entregar-todo">
                    <label class="form-check-label" for="entregar-todo">Entregar todo</label>
                </div>
                
                <div class="mt-3">
                    <button class="btn btn-primary" onclick="guardarAvance(${t.id})">
                        <i class="bi bi-save"></i> Guardar Avance
                    </button>
                    <button class="btn btn-success" onclick="confirmarEntrega(${t.id})">
                        <i class="bi bi-check2-circle"></i> Confirmar Entrega Total
                    </button>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </div>
        `;
        
        document.getElementById('modal-body').innerHTML = html;
        
        document.getElementById('entregar-todo').addEventListener('change', function() {
            const inputs = document.querySelectorAll('#checklist-items input[type="number"]');
            const selects = document.querySelectorAll('#checklist-items select');
            inputs.forEach((input, i) => {
                if (this.checked) {
                    input.value = input.dataset.cantidadPedida;
                    selects[i].value = 'entregado';
                }
            });
        });
        
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }

    async function guardarAvance(ticketId) {
        const items = [];
        document.querySelectorAll('#checklist-items tr').forEach(tr => {
            const input = tr.querySelector('input[type="number"]');
            const select = tr.querySelector('select');
            items.push({
                item_id: input.dataset.itemId,
                cantidad_entregada: parseFloat(input.value) || 0,
                estado_item: select.value
            });
        });
        
        const fd = new FormData();
        fd.append('ticket_id', ticketId);
        fd.append('csrf_token', '<?= csrfToken() ?>');
        
        const resp = await fetch('../api/items.php?action=save', { method: 'POST', body: fd });
        const r = await resp.json();
        
        // Save each item
        for (const item of items) {
            const fdItem = new FormData();
            fdItem.append('item_id', item.item_id);
            fdItem.append('cantidad_entregada', item.cantidad_entregada);
            fdItem.append('estado_item', item.estado_item);
            await fetch('../api/items.php?action=check', { method: 'POST', body: fdItem });
        }
        
        alert(r.success ? 'Avance guardado' : r.error);
        cargarTickets();
    }

    async function confirmarEntrega(ticketId) {
        if (!confirm('¿Confirmar entrega total del pedido?')) return;
        
        const fd = new FormData();
        fd.append('ticket_id', ticketId);
        
        const resp = await fetch('../api/items.php?action=confirm', { method: 'POST', body: fd });
        const r = await resp.json();
        
        if (r.success) {
            alert('Entrega confirmada');
            bootstrap.Modal.getInstance(document.getElementById('detailModal')).hide();
            cargarTickets();
        } else {
            alert(r.error);
        }
    }

    cargarTickets();
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>