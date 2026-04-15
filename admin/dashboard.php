<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireAuth();

if ($_SESSION['rol'] !== 'admon') {
    header('Location: ../bienvenido.php');
    exit;
}

$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'recibido' THEN 1 ELSE 0 END) as recibido,
    SUM(CASE WHEN estado = 'proceso' THEN 1 ELSE 0 END) as proceso,
    SUM(CASE WHEN estado = 'pendientes' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finaleado
FROM tickets WHERE DATE(created_at) = CURDATE()");
$stats = $stmt->fetch();

$stmt = $pdo->query("SELECT 
    s.nombre as sede,
    COUNT(t.id) as total
FROM tickets t
JOIN sedes s ON t.sede_id = s.id
GROUP BY s.id
ORDER BY total DESC
LIMIT 5");
$sedes_stats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - INSUMAX</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container py-4">
        <h4><i class="bi bi-speedometer2"></i> Dashboard - Administrador</h4>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center" style="border-left: 4px solid #1565c0;">
                    <div class="card-body">
                        <div class="text-muted">Recibidos</div>
                        <div style="font-size: 32px; font-weight: 700; color: #1565c0;"><?= $stats['recibido'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center" style="border-left: 4px solid #f57f17;">
                    <div class="card-body">
                        <div class="text-muted">En Proceso</div>
                        <div style="font-size: 32px; font-weight: 700; color: #f57f17;"><?= $stats['proceso'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center" style="border-left: 4px solid #c62828;">
                    <div class="card-body">
                        <div class="text-muted">Con Pendientes</div>
                        <div style="font-size: 32px; font-weight: 700; color: #c62828;"><?= $stats['pendientes'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center" style="border-left: 4px solid #2e7d32;">
                    <div class="card-body">
                        <div class="text-muted">Finalizados</div>
                        <div style="font-size: 32px; font-weight: 700; color: #2e7d32;"><?= $stats['finaleado'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-ticket-detailed"></i> Últimos Tickets</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="tabla-tickets">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código</th>
                                        <th>Sede</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-shop"></i> Tickets por Sede</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($sedes_stats as $s): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span><?= htmlspecialchars($s['sede']) ?></span>
                            <strong><?= $s['total'] ?></strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    async function cargarTickets() {
        try {
            const resp = await fetch('../api/tickets.php?action=list');
            const result = await resp.json();
            
            if (!result.success) return;
            
            const tbody = document.querySelector('#tabla-tickets tbody');
            const estados = { recibido: '#1565c0', proceso: '#f57f17', pendientes: '#c62828', finalizedo: '#2e7d32' };
            
            tbody.innerHTML = result.data.slice(0, 10).map(t => `
                <tr>
                    <td><strong>${t.codigo_ticket}</strong></td>
                    <td>${t.sede_nombre || '-'}</td>
                    <td><span class="badge" style="background: ${estados[t.estado] || '#666'}">${t.estado.toUpperCase()}</span></td>
                    <td>${t.fecha_pedido}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="verTicket(${t.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        } catch (err) {
            console.error(err);
        }
    }

    async function verTicket(id) {
        const resp = await fetch('../api/tickets.php?action=detail&id=' + id);
        const r = await resp.json();
        
        if (!r.success) { alert(r.error); return; }
        
        const t = r.ticket;
        const items = r.items;
        
        let html = `
            <div class="text-start">
                <h5>${t.codigo_ticket}</h5>
                <p><strong>Sede:</strong> ${t.sede_nombre}</p>
                <p><strong>Comprador:</strong> ${t.comprador_nombre} ${t.comprador_apellido || ''}</p>
                <p><strong>Fecha:</strong> ${t.fecha_pedido}</p>
                <p><strong>Estado:</strong> <span class="badge bg-${t.estado === 'finalizado' ? 'success' : t.estado === 'pendientes' ? 'danger' : t.estado === 'proceso' ? 'warning' : 'primary'}">${t.estado.toUpperCase()}</span></p>
                <p><strong>Distribuidor:</strong> ${t.distribuidor_nombre || 'Sin asignar'}</p>
                
                <h6>Items:</h6>
                <table class="table table-sm">
                    <thead><tr><th>Producto</th><th>Cant</th><th>Entregado</th><th>Estado</th></tr></thead>
                    <tbody>
                        ${items.map(i => `
                        <tr>
                            <td>${i.descripcion}</td>
                            <td>${i.cantidad_pedida}</td>
                            <td>${i.cantidad_entregada || '-'}</td>
                            <td>${i.estado_item}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>
                
                <div class="mt-3">
                    <label class="form-label">Cambiar Estado:</label>
                    <select class="form-select" id="newEstado" onchange="cambiarEstado(${t.id}, this.value)">
                        <option value="recibido" ${t.estado === 'recibido' ? 'selected' : ''}>Recibido</option>
                        <option value="proceso" ${t.estado === 'proceso' ? 'selected' : ''}>En Proceso</option>
                        <option value="pendientes" ${t.estado === 'pendientes' ? 'selected' : ''}>Con Pendientes</option>
                        <option value="finalizado" ${t.estado === 'finalizado' ? 'selected' : ''}>Finalizado</option>
                    </select>
                </div>
                
                <div class="mt-3">
                    <label class="form-label">Asignar Distribuidor:</label>
                    <select class="form-select" id="newDist" onchange="asignarDist(${t.id}, this.value)">
                        <option value="">Seleccionar...</option>
                    </select>
                </div>
            </div>
        `;
        
        document.getElementById('modal-body').innerHTML = html;
        
        // Cargar distribuidores
        fetch('../api/usuarios.php?action=list&rol=dist')
            .then(r => r.json())
            .then(r => {
                if (r.success) {
                    const sel = document.getElementById('newDist');
                    r.data.forEach(d => {
                        sel.innerHTML += `<option value="${d.id}">${d.nombre}</option>`;
                    });
                    if (t.distribuidor_id) sel.value = t.distribuidor_id;
                }
            });
        
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }

    async function cambiarEstado(ticketId, estado) {
        const fd = new FormData();
        fd.append('ticket_id', ticketId);
        fd.append('estado', estado);
        fd.append('csrf_token', '<?= csrfToken() ?>');
        
        const r = await fetch('../api/tickets.php?action=status', { method: 'POST', body: fd });
        const res = await r.json();
        alert(res.success ? 'Estado actualizado' : res.error);
        cargarTickets();
    }

    async function asignarDist(ticketId, distId) {
        if (!distId) return;
        const fd = new FormData();
        fd.append('ticket_id', ticketId);
        fd.append('distribuidor_id', distId);
        fd.append('csrf_token', '<?= csrfToken() ?>');
        
        const r = await fetch('../api/tickets.php?action=assign', { method: 'POST', body: fd });
        const res = await r.json();
        alert(res.success ? 'Distribuidor asignado' : res.error);
        cargarTickets();
    }

    cargarTickets();
    </script>

    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalle del Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modal-body"></div>
            </div>
        </div>
    </div>
</body>
</html>