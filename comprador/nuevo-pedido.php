<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
requireAuth();

if ($_SESSION['rol'] !== 'comprador') {
    header('Location: ../login.php');
    exit;
}

$colors = require __DIR__ . '/../config/colors.php';

if (empty($_SESSION['sede_id'])) {
    die('Error: Su cuenta no tiene sede asignada. Contacte al administrador.');
}

$sede_id = $_SESSION['sede_id'];

$stmt = $pdo->prepare("SELECT s.*, c.nombre as ciudad_nombre FROM sedes s LEFT JOIN ciudades c ON s.ciudad_id = c.id WHERE s.id = ?");
$stmt->execute([$sede_id]);
$sede = $stmt->fetch();

$ciudad_id = intval($sede['ciudad_id'] ?? 0);
$ciudad_nombre = $sede['ciudad_nombre'] ?? '';
$color_ciudad = $colors['ciudades'][$ciudad_nombre] ?? '#6c757d';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Pedido - INSUMAX</title>
    <link rel="icon" type="image/png" href="../assets/iconfinder.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { padding-bottom: 80px; }
        .insumo-row td { vertical-align: middle; }
        .insumo-qty { width: 80px; }
        .insumo-total { font-weight: 600; color: #28a745; }
        .badge-grupo { font-size: 10px; }
        .total-fijo {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: <?= $color_ciudad ?>;
            color: white;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <h4><i class="bi bi-cart-plus"></i> Nuevo Pedido</h4>
                <div class="badge bg-light text-dark" style="border-left: 3px solid <?= $color_ciudad ?>; padding: 8px 12px;">
                    <i class="bi bi-shop"></i> <?= htmlspecialchars($sede['nombre'] ?? 'Sin sede') ?>
                    <span class="text-muted">|</span>
                    <strong style="color: <?= $color_ciudad ?>;"><?= htmlspecialchars($ciudad_nombre) ?></strong>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-5">
                <label class="form-label small"><i class="bi bi-person"></i> Usuario</label>
                <input type="text" class="form-control" id="responsable" readonly value="<?= htmlspecialchars(trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''))) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small"><i class="bi bi-calendar"></i> Fecha</label>
                <input type="date" class="form-control" id="fecha_pedido" value="<?= date('Y-m-d') ?>" readonly disabled>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Buscar insumo</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="buscar-insumo" class="form-control" placeholder="Buscar...">
                </div>
            </div>
        </div>

        <div class="alert alert-info py-2 mb-3" style="border-left: 4px solid <?= $color_ciudad ?>;">
            <i class="bi bi-geo-alt-fill" style="color: <?= $color_ciudad ?>;"></i> 
            Lista para <strong style="color: <?= $color_ciudad ?>;">Bogotá</strong>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover fs-6" id="tabla-insumos">
                <thead class="table-light">
                    <tr>
                        <th>Cód</th>
                        <th>Descripción</th>
                        <th>Pres</th>
                        <th>UND</th>
                        <th>Fac</th>
                        <th>$ Precio</th>
                        <th>Cant</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody id="insumos-body">
                    <tr><td colspan="8" class="text-center py-4"><i class="bi bi-hourglass-split"></i> Cargando insumos...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="mb-3 mt-3">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control" id="observaciones" rows="2" placeholder="Observaciones adicionales..."></textarea>
        </div>
    </div>

    <div class="total-fijo" id="total-fijo">
        <div>
            <span class="opacity-75">Total:</span>
            <span id="total-pedido" style="font-size: 20px; font-weight: 700;">$0</span>
            <span id="items-contador" class="badge bg-light text-dark ms-2">0 items</span>
        </div>
        <div>
            <button type="button" class="btn btn-insumax" onclick="mostrarResumen()">
                <i class="bi bi-check2-circle"></i> Enviar Pedido
            </button>
        </div>
    </div>

    <div class="modal fade" id="resumenModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: <?= $color_ciudad ?>; color: white;">
                    <h5 class="modal-title"><i class="bi bi-cart-check"></i> Resumen del Pedido</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Usuario</label>
                            <input type="text" class="form-control" id="resumen-responsable" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Fecha</label>
                            <input type="text" class="form-control" id="resumen-fecha" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Sede</label>
                        <input type="text" class="form-control" id="resumen-sede" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Observaciones</label>
                        <textarea class="form-control" id="resumen-observaciones" rows="2" placeholder="Sin observaciones"></textarea>
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-2">Ítems del Pedido</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="resumen-items-body">
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="2">Total Items</td>
                                    <td id="resumen-total-items">0</td>
                                </tr>
                                <tr class="fw-bold">
                                    <td colspan="2">Total Valor</td>
                                    <td id="resumen-total-valor">$0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-insumax" onclick="confirmarPedido()">
                        <i class="bi bi-check2-circle"></i> Confirmar Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    var basePath = '..';
    var ciudadActual = <?= $ciudad_id ?>;
    var colorCiudad = '<?= $color_ciudad ?>';
    var csrfToken = '<?php echo csrfToken(); ?>';
    var coloresGrupo = <?= json_encode($colors['grupos']) ?>;

    document.addEventListener('DOMContentLoaded', function() {
        cargarInsumos();
        
        document.getElementById('buscar-insumo').addEventListener('input', filtrarTabla);
    });

    function cargarInsumos() {
        var url = basePath + '/api/insumos.php?action=list&ciudad_id=' + ciudadActual;
        fetch(url)
            .then(function(resp) { return resp.json(); })
            .then(function(res) {
                if (!res.success) {
                    document.getElementById('insumos-body').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error: ' + (res.error || 'desconocido') + '</td></tr>';
                    return;
                }
                renderTable(res.data);
            })
            .catch(function(err) {
                document.getElementById('insumos-body').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error de conexión</td></tr>';
            });
    }

    function renderTable(data) {
        var tbody = document.getElementById('insumos-body');
        var html = '';
        
        data.forEach(function(i) {
            var fmt = function(n) {
                return n ? parseFloat(n).toLocaleString('es-CO') : '-';
            };
            var factor = parseFloat(i.factor_conversion) || 1;
            var factorDisplay = factor > 1 ? fmt(factor) + 'g' : '<span class="text-muted">-</span>';
            var precioKg = i.precio_kg ? '<span class="badge bg-info">$' + fmt(i.precio_kg) + '</span>' : '<span class="text-muted">-</span>';
            var unidadDisplay = '<span class="badge bg-primary">' + (i.unidad_compra || i.unidad_medida || '-') + '</span>';
            var colorGrupo = coloresGrupo[i.grupo] || '#6c757d';
            var presentacion = i.presentacion || '-';
            var activo = i.activo !== 0;
            var rowClass = activo ? '' : 'table-secondary opacity-75';
            var descClass = activo ? '' : 'text-muted text-decoration-line-through';
            
            var precioVenta = parseFloat(i.precio_venta) || 0;
            
            html += '<tr class="insumo-row ' + rowClass + '" data-id="' + i.id + '" data-precio="' + precioVenta + '" data-grupo="' + i.grupo + '">' +
                '<td style="border-left: 4px solid ' + colorGrupo + ';">' + (i.codigo || '') + '</td>' +
                '<td class="' + descClass + '">' + i.descripcion + '</td>' +
                '<td class="text-muted small">' + presentacion + '</td>' +
                '<td>' + unidadDisplay + '</td>' +
                '<td class="text-muted small">' + factorDisplay + '</td>' +
                '<td class="fw-bold">' + (i.precio_venta ? '$' + fmt(i.precio_venta) : '-') + '</td>' +
                '<td>' +
                '<input type="number" class="form-control form-control-sm insumo-qty" ' +
                'min="0" step="0.01" placeholder="0" style="border-color: #fd7e0; box-shadow: 0 0 0 2px rgba(253, 126, 0, 0.25);" ' +
                'oninput="calcularItem(this)" ' +
                'data-id="' + i.id + '">' +
                '</td>' +
                '<td class="insumo-total">$0</td>' +
                '</tr>';
        });
        
        if (html === '') {
            html = '<tr><td colspan="8" class="text-center text-muted">No hay insumos disponibles</td></tr>';
        }
        
        tbody.innerHTML = html;
    }

    function filtrarTabla() {
        var texto = document.getElementById('buscar-insumo').value.toLowerCase();
        var filas = document.querySelectorAll('#insumos-body .insumo-row');
        
        filas.forEach(function(fila) {
            var desc = fila.querySelector('td:nth-child(2)').textContent.toLowerCase();
            var cod = fila.querySelector('td:first-child').textContent.toLowerCase();
            var mostrar = texto === '' || desc.includes(texto) || cod.includes(texto);
            fila.style.display = mostrar ? '' : 'none';
        });
    }

    function calcularItem(input) {
        var row = input.closest('tr');
        var cantidad = parseFloat(input.value) || 0;
        var precio = parseFloat(row.dataset.precio) || 0;
        var total = cantidad * precio;
        
        var totalCell = row.querySelector('.insumo-total');
        totalCell.textContent = total > 0 ? '$' + total.toLocaleString('es-CO', {maximumFractionDigits: 0}) : '$0';
        
        actualizarTotalGeneral();
    }

    function actualizarTotalGeneral() {
        var total = 0;
        var itemsCount = 0;
        
        document.querySelectorAll('#insumos-body .insumo-row').forEach(function(row) {
            var input = row.querySelector('.insumo-qty');
            var cantidad = parseFloat(input.value) || 0;
            var precio = parseFloat(row.dataset.precio) || 0;
            if (cantidad > 0) {
                total += cantidad * precio;
                itemsCount++;
            }
        });
        
        document.getElementById('total-pedido').textContent = '$' + total.toLocaleString('es-CO', {maximumFractionDigits: 0});
        document.getElementById('items-contador').textContent = itemsCount + ' items';
    }

    var itemsData = [];

    function mostrarResumen() {
        itemsData = [];
        
        document.querySelectorAll('#insumos-body .insumo-row').forEach(function(row) {
            var input = row.querySelector('.insumo-qty');
            var cantidad = parseFloat(input.value) || 0;
            var precio = parseFloat(row.dataset.precio) || 0;
            if (cantidad > 0) {
                var descripcion = row.querySelector('td:nth-child(2)').textContent;
                itemsData.push({
                    insumo_id: row.dataset.id,
                    descripcion: descripcion,
                    cantidad: cantidad,
                    precio: precio,
                    total: cantidad * precio
                });
            }
        });
        
        if (itemsData.length === 0) {
            alert('Debe seleccionar al menos un producto');
            return;
        }

        document.getElementById('resumen-responsable').value = document.getElementById('responsable').value;
        document.getElementById('resumen-fecha').value = document.getElementById('fecha_pedido').value;
        var sedeBadge = document.querySelector('.badge.bg-light');
        var sedeNombre = sedeBadge ? sedeBadge.textContent.replace('|', '-').trim() : '<?= htmlspecialchars($sede['nombre'] ?? 'Sin sede') ?> - <?= htmlspecialchars($ciudad_nombre) ?>';
        document.getElementById('resumen-sede').value = sedeNombre;
        document.getElementById('resumen-observaciones').value = document.getElementById('observaciones').value;

        var tbody = document.getElementById('resumen-items-body');
        var html = '';
        var totalItems = 0;
        var totalValor = 0;
        
        itemsData.forEach(function(item) {
            html += '<tr>' +
                '<td>' + item.descripcion + '</td>' +
                '<td>' + item.cantidad + '</td>' +
                '<td>$' + item.total.toLocaleString('es-CO') + '</td>' +
                '</tr>';
            totalItems += item.cantidad;
            totalValor += item.total;
        });
        
        tbody.innerHTML = html;
        document.getElementById('resumen-total-items').textContent = totalItems;
        document.getElementById('resumen-total-valor').textContent = '$' + totalValor.toLocaleString('es-CO');

        new bootstrap.Modal(document.getElementById('resumenModal')).show();
    }

    function confirmarPedido() {
        var items = itemsData.map(function(item) {
            return {
                insumo_id: parseInt(item.insumo_id, 10),
                cantidad: parseFloat(item.cantidad)
            };
        });

        var payload = {
            csrf_token: csrfToken,
            responsable: document.getElementById('responsable').value,
            fecha_pedido: document.getElementById('fecha_pedido').value,
            observaciones: document.getElementById('observaciones').value,
            items: items
        };

        

        fetch(basePath + '/api/tickets.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(resp) {
            console.log('Status:', resp.status);
            if (!resp.ok) {
                throw new Error('HTTP ' + resp.status);
            }
            return resp.text();
        })
        .then(function(text) {
            console.log('Response:', text);
            if (!text) {
                throw new Error('Respuesta vacía');
            }
            var result = JSON.parse(text);
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('resumenModal')).hide();
                alert('Pedido creado: ' + result.codigo_ticket);
                window.location.href = 'mis-pedidos.php';
            } else {
                alert('Error: ' + (result.error || 'desconocido'));
            }
        })
        .catch(function(err) {
            alert('Error: ' + err.message);
        });
    }
    </script>
</body>
</html>