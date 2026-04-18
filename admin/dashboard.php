<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
$colors = require __DIR__ . '/../config/colors.php';
requireAuth();

if ($_SESSION['rol'] !== 'admon') {
    header('Location: ../login.php');
    exit;
}

$filtro_mes = $_GET['filtro_mes'] ?? 'este_mes';

$filtro_mes = $_GET['filtro_mes'] ?? 'todo';

switch($filtro_mes) {
    case 'este_mes':
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-t');
        break;
    case 'mes_anterior':
        $fecha_inicio = date('Y-m-01', strtotime('-1 month'));
        $fecha_fin = date('Y-m-t', strtotime('-1 month'));
        break;
    case 'ultimos_30':
        $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
        $fecha_fin = date('Y-m-d');
        break;
    case 'todo':
        $fecha_inicio = '2000-01-01';
        $fecha_fin = date('Y-m-d');
        break;
    default:
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-t');
}

# Contadores y Tickets por Sede - SIN FILTRO (totales siempre)
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'recibido' THEN 1 ELSE 0 END) as recibido,
    SUM(CASE WHEN estado = 'proceso' THEN 1 ELSE 0 END) as proceso,
    SUM(CASE WHEN estado = 'pendientes' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados
FROM tickets");
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

# Ultimos Tickets - CON FILTRO
$sql = "SELECT t.*, s.nombre as sede_nombre, s.ciudad as sede_ciudad
    FROM tickets t 
    JOIN sedes s ON t.sede_id = s.id 
    WHERE t.fecha_pedido >= '$fecha_inicio' AND t.fecha_pedido <= '$fecha_fin'
    ORDER BY t.created_at DESC 
    LIMIT 20";
$ultimos_tickets = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - INSUMAX</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4><i class="bi bi-speedometer2"></i> Dashboard - Administrador</h4>
            
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center" style="border-left: 4px solid <?= $colors['estados']['recibido']['border'] ?? '#1565c0' ?>;">
                    <div class="card-body">
                        <div class="text-muted">Recibidos</div>
                        <div style="font-size: 32px; font-weight: 700; color: <?= $colors['estados']['recibido']['border'] ?? '#1565c0' ?>;"><?= (int)($stats['recibido'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center" style="border-left: 4px solid <?= $colors['estados']['proceso']['border'] ?? '#f57f17' ?>;">
                    <div class="card-body">
                        <div class="text-muted">En Proceso</div>
                        <div style="font-size: 32px; font-weight: 700; color: <?= $colors['estados']['proceso']['border'] ?? '#f57f17' ?>;"><?= (int)($stats['proceso'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center" style="border-left: 4px solid <?= $colors['estados']['pendientes']['border'] ?? '#d32f2f' ?>;">
                    <div class="card-body">
                        <div class="text-muted">Con Pendientes</div>
                        <div style="font-size: 32px; font-weight: 700; color: <?= $colors['estados']['pendientes']['border'] ?? '#d32f2f' ?>;"><?= (int)($stats['pendientes'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center" style="border-left: 4px solid <?= $colors['estados']['finalizado']['border'] ?? '#388e3c' ?>;">
                    <div class="card-body">
                        <div class="text-muted">Finalizados</div>
                        <div style="font-size: 32px; font-weight: 700; color: <?= $colors['estados']['finalizado']['border'] ?? '#388e3c' ?>;"><?= (int)($stats['finalizados'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-ticket-detailed"></i> Últimos Tickets</h5>
                        <select class="form-select form-select-sm" style="width:auto" onchange="window.location.href='dashboard.php?filtro_mes='+this.value">
                            <option value="este_mes" <?= $filtro_mes=='este_mes'?'selected':''?>>Este mes</option>
                            <option value="mes_anterior" <?= $filtro_mes=='mes_anterior'?'selected':''?>>Mes anterior</option>
                            <option value="ultimos_30" <?= $filtro_mes=='ultimos_30'?'selected':''?>>Últimos 30 días</option>
                            <option value="todos" <?= $filtro_mes=='todos'?'selected':''?>>Todos</option>
                        </select>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="tabla-tickets">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código</th>
                                        <th>Sede</th>
                                        <th>Ciudad</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if(count($ultimos_tickets) > 0): ?>
                                <?php foreach($ultimos_tickets as $t): ?>
                                <?php $color_ciudad = $colors['ciudades'][$t['sede_ciudad'] ?? ''] ?? '#6c757d'; ?>
                                <tr style="border-left: 3px solid <?= $color_ciudad ?>;">
                                    <td><strong><?= htmlspecialchars($t['codigo_ticket']) ?></strong></td>
                                    <td><?= htmlspecialchars($t['sede_nombre']) ?></td>
                                    <td style="color: <?= $color_ciudad ?>; font-weight: 600;"><?= htmlspecialchars($t['sede_ciudad'] ?? '') ?></td>
                                    <td><span class="badge" style="background-color: <?= $colors['estados'][$t['estado']]['bg'] ?? '#eee' ?>; color: <?= $colors['estados'][$t['estado']]['text'] ?? '#333' ?>; border: 1px solid <?= $colors['estados'][$t['estado']]['text'] ?? '#666' ?>; font-weight: 600;"><?= strtoupper($t['estado']) ?></span></td>
                                    <td><?= $t['fecha_pedido'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted">No hay tickets para el periodo: <?= $filtro_mes ?> (<?= $fecha_inicio ?> a <?= $fecha_fin ?>)</td></tr>
                                <?php endif; ?>
                                </tbody>
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
    const filtroMes = '<?= $filtro_mes ?>';
    const fechaDesde = '<?= $fecha_inicio ?>';
    
    async function cargarTickets() {
        console.log('Cargando tickets desde:', fechaDesde);
        try {
            const resp = await fetch('../api/tickets.php?action=list&fecha_desde=' + encodeURIComponent(fechaDesde));
            const result = await resp.json();
            console.log('API response:', result);
            
            if (!result.success) {
                console.error('Error:', result.error);
                return;
            }
            
            const tbody = document.querySelector('#tabla-tickets tbody');
            const estados = <?= json_encode($colors['estados']) ?>;
            
            tbody.innerHTML = result.data.slice(0, 10).map(t => `
                <tr>
                    <td><strong>${t.codigo_ticket}</strong></td>
                    <td>${t.sede_nombre || '-'}</td>
                    <td><span class="badge" style="background-color: ${estados[t.estado]?.bg || '#6c757d'}; color: ${estados[t.estado]?.text || '#fff'};">${t.estado.toUpperCase()}</span></td>
                    <td>${t.fecha_pedido}</td>
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

    // cargarTickets(); // deshabilitado - tabla se llena desde PHP
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