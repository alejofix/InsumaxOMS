<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
requireAuth();

if ($_SESSION['rol'] !== 'admon') {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->query("SELECT id, nombre FROM ciudades WHERE activa = 1 ORDER BY nombre");
$ciudades = $stmt->fetchAll();

$ciudad_default = $ciudades[0]['id'] ?? 0;

$colores_ciudad = [
    'Bogotá' => '#1E3A5F',
    'Medellín' => '#00897B',
    'Pereira' => '#F57C00',
    'Barranquilla' => '#C62828',
    'Cali' => '#7B1FA2'
];
$color_default = $colores_ciudad[$ciudades[0]['nombre']] ?? '#6c757d';

$stmt = $pdo->prepare("
    SELECT i.*, ip.precio_compra, ip.precio_venta
    FROM insumos i
    LEFT JOIN insumos_precios ip ON i.id = ip.insumo_id AND ip.ciudad_id = ?
    WHERE i.activo = 1
    ORDER BY FIELD(i.grupo, 'carnes', 'quesos', 'plaza', 'salsas', 'varios', 'aseo'), i.descripcion
");
$stmt->execute([$ciudad_default]);
$insumos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insumos - INSUMAX</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h4><i class="bi bi-box-seam"></i> Catálogo de Insumos</h4>
                <select id="ciudad-select" class="form-select form-select-sm" style="width: 160px; border-left: 4px solid <?= $color_default ?>;">
                    <?php foreach($ciudades as $c): 
                        $color = $colores_ciudad[$c['nombre']] ?? '#6c757d';
                    ?>
                    <option value="<?= $c['id'] ?>" data-color="<?= $color ?>" data-nombre="<?= $c['nombre'] ?>" <?= $c['id'] == $ciudad_default ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-insumax" onclick="mostrarForm()"><i class="bi bi-plus-circle"></i> Nuevo Insumo</button>
        </div>

        <div class="alert alert-info py-2" id="ciudad-info" style="border-left: 4px solid <?= $color_default ?>;">
            <i class="bi bi-geo-alt-fill" style="color: <?= $color_default ?>;"></i> Precios para <strong style="color: <?= $color_default ?>;"><?= htmlspecialchars($ciudades[0]['nombre'] ?? '') ?></strong>. Seleccione otra ciudad para ver/editar sus precios.
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Grupo</th>
                        <th>Descripción</th>
                        <th>Unidad</th>
                        <th>Precio Compra</th>
                        <th>Precio Venta</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="insumos-body">
                    <?php foreach($insumos as $i): ?>
                    <tr data-id="<?= $i['id'] ?>">
                        <td><?= htmlspecialchars($i['codigo'] ?? '') ?></td>
                        <td><span class="badge bg-secondary"><?= strtoupper($i['grupo']) ?></span></td>
                        <td><?= htmlspecialchars($i['descripcion']) ?></td>
                        <td><?= htmlspecialchars($i['unidad_medida']) ?></td>
                        <td><?= $i['precio_compra'] ? number_format($i['precio_compra'], 0, ',', '.') : '-' ?></td>
                        <td><?= $i['precio_venta'] ? number_format($i['precio_venta'], 0, ',', '.') : '-' ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editar(<?= $i['id'] ?>)"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="eliminar(<?= $i['id'] ?>)"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form -->
    <div class="modal fade" id="formModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="form-title"><i class="bi bi-plus-circle"></i> Nuevo Insumo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="insumo-form">
                    <div class="modal-body">
                        <input type="hidden" id="insumo-id" name="id">
                        <input type="hidden" id="insumo-ciudad" name="ciudad_id" value="<?= $ciudad_default ?>">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" id="insumo-codigo" name="codigo">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Grupo</label>
                                <select class="form-select" id="insumo-grupo" name="grupo" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="carnes">Carnes</option>
                                    <option value="quesos">Quesos</option>
                                    <option value="plaza">Plaza</option>
                                    <option value="salsas">Salsas</option>
                                    <option value="varios">Varios</option>
                                    <option value="aseo">Aseo</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Unidad</label>
                                <input type="text" class="form-control" id="insumo-unidad" name="unidad_medida" required placeholder="KG, UND...">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="insumo-descripcion" name="descripcion" required>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">Precio Compra</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="insumo-pcompra" name="precio_compra" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Precio Venta</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="insumo-pventa" name="precio_venta" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-insumax"><i class="bi bi-check"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    var basePath = '..';
    var ciudadActual = <?= $ciudad_default ?>;
    var csrfToken = '<?php echo csrfToken(); ?>';

    document.getElementById('ciudad-select').addEventListener('change', function() {
        ciudadActual = this.value;
        document.getElementById('insumo-ciudad').value = ciudadActual;
        var selectedOption = this.options[this.selectedIndex];
        var color = selectedOption.getAttribute('data-color') || '#6c757d';
        var nombre = selectedOption.getAttribute('data-nombre') || '';
        
        this.style.borderLeftColor = color;
        
        var infoAlert = document.getElementById('ciudad-info');
        infoAlert.style.borderLeftColor = color;
        infoAlert.innerHTML = '<i class="bi bi-geo-alt-fill" style="color: ' + color + ';"></i> Precios para <strong style="color: ' + color + ';">' + nombre + '</strong>. Seleccione otra ciudad para ver/editar sus precios.';
        
        cargarInsumos();
    });

    document.getElementById('insumo-form').addEventListener('submit', function(e) {
        e.preventDefault();
        guardarInsumo();
    });

    function cargarInsumos() {
        var url = basePath + '/api/insumos.php?action=list&ciudad_id=' + ciudadActual;
        document.getElementById('insumos-body').innerHTML = '<tr><td colspan="7" class="text-center py-3"><i class="bi bi-hourglass-split"></i> Cargando...</td></tr>';
        
        fetch(url)
            .then(function(resp) { return resp.json(); })
            .then(function(res) {
                if (!res.success) {
                    alert('Error: ' + (res.error || 'desconocido'));
                    return;
                }
                renderTable(res.data);
            });
    }

    function renderTable(data) {
        var tbody = document.getElementById('insumos-body');
        var selectEl = document.getElementById('ciudad-select');
        var color = selectEl.options[selectEl.selectedIndex].getAttribute('data-color') || '#6c757d';
        var html = '';
        data.forEach(function(i) {
            var fmt = function(n) {
                return n ? parseFloat(n).toLocaleString('es-CO') : '-';
            };
            html += '<tr data-id="' + i.id + '">' +
                '<td style="border-left: 3px solid ' + color + ';">' + (i.codigo || '') + '</td>' +
                '<td><span class="badge bg-secondary">' + (i.grupo || '').toUpperCase() + '</span></td>' +
                '<td>' + i.descripcion + '</td>' +
                '<td>' + i.unidad_medida + '</td>' +
                '<td>' + fmt(i.precio_compra) + '</td>' +
                '<td>' + fmt(i.precio_venta) + '</td>' +
                '<td><button class="btn btn-sm btn-outline-primary" onclick="editar(' + i.id + ')"><i class="bi bi-pencil"></i></button>' +
                ' <button class="btn btn-sm btn-outline-danger" onclick="eliminar(' + i.id + ')"><i class="bi bi-trash"></i></button></td>' +
                '</tr>';
        });
        tbody.innerHTML = html;
    }

    function mostrarForm() {
        document.getElementById('form-title').innerHTML = '<i class="bi bi-plus-circle"></i> Nuevo Insumo';
        document.getElementById('insumo-id').value = '';
        document.getElementById('insumo-ciudad').value = ciudadActual;
        document.getElementById('insumo-codigo').value = '';
        document.getElementById('insumo-grupo').value = '';
        document.getElementById('insumo-descripcion').value = '';
        document.getElementById('insumo-unidad').value = '';
        document.getElementById('insumo-pcompra').value = '';
        document.getElementById('insumo-pventa').value = '';
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }

    function editar(id) {
        var url = basePath + '/api/insumos.php?action=get&id=' + id + '&ciudad_id=' + ciudadActual;
        fetch(url)
            .then(function(resp) { return resp.json(); })
            .then(function(res) {
                if (!res.success) {
                    alert('Error cargando insumo');
                    return;
                }
                var i = res.data;
                document.getElementById('form-title').innerHTML = '<i class="bi bi-pencil"></i> Editar Insumo';
                document.getElementById('insumo-id').value = i.id;
                document.getElementById('insumo-ciudad').value = ciudadActual;
                document.getElementById('insumo-codigo').value = i.codigo || '';
                document.getElementById('insumo-grupo').value = i.grupo;
                document.getElementById('insumo-descripcion').value = i.descripcion;
                document.getElementById('insumo-unidad').value = i.unidad_medida;
                document.getElementById('insumo-pcompra').value = i.precio_compra || '';
                document.getElementById('insumo-pventa').value = i.precio_venta || '';
                new bootstrap.Modal(document.getElementById('formModal')).show();
            });
    }

    function guardarInsumo() {
        var form = document.getElementById('insumo-form');
        var formData = new FormData(form);
        formData.append('action', 'save');
        formData.append('csrf_token', csrfToken);

        var url = basePath + '/api/insumos.php';
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(function(resp) { return resp.json(); })
        .then(function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('formModal')).hide();
                cargarInsumos();
            } else {
                alert('Error: ' + (res.error || 'desconocido'));
            }
        });
    }

    function eliminar(id) {
        if (!confirm('¿Eliminar este insumo?')) return;
        var formData = new FormData();
        formData.append('action', 'delete');
        formData.append('csrf_token', csrfToken);
        formData.append('id', id);

        var url = basePath + '/api/insumos.php';
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(function(resp) { return resp.json(); })
        .then(function(res) {
            if (res.success) {
                cargarInsumos();
            } else {
                alert('Error: ' + (res.error || 'desconocido'));
            }
        });
    }
    </script>
</body>
</html>
