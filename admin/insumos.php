<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
requireAuth();

$colors = require __DIR__ . '/../config/colors.php';
$enums = require __DIR__ . '/../config/enums.php';

if ($_SESSION['rol'] !== 'admon') {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->query("SELECT id, nombre FROM ciudades WHERE activa = 1 ORDER BY nombre");
$ciudades = $stmt->fetchAll();

$ciudad_default = $ciudades[0]['id'] ?? 0;
$color_default = $colors['ciudades'][$ciudades[0]['nombre']] ?? '#6c757d';

$stmt = $pdo->prepare("
    SELECT i.*, ip.precio_compra, ip.precio_venta,
        u.unidad_compra, u.unidad_base, u.factor_conversion, u.presentacion,
        ROUND(ip.precio_compra / NULLIF(u.factor_conversion, 0) * 1000, 2) AS precio_kg
    FROM insumos i
    LEFT JOIN insumos_precios ip ON i.id = ip.insumo_id AND ip.ciudad_id = ?
    LEFT JOIN insumos_unidades u ON i.id = u.insumo_id
    WHERE i.activo = 1
    ORDER BY FIELD(i.grupo, '" . implode("','", $enums['grupos']) . "'), i.descripcion
");
$stmt->execute([$ciudad_default]);
$insumos = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM unidades_medida WHERE activo = 1 ORDER BY tipo, codigo");
$unidades = $stmt->fetchAll();
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
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h4><i class="bi bi-box-seam"></i> Catálogo de Insumos</h4>
                <select id="ciudad-select" class="form-select form-select-sm" style="width: 160px; border-left: 4px solid <?= $color_default ?>;">
                    <?php foreach($ciudades as $c): 
                        $color = $colors['ciudades'][$c['nombre']] ?? '#6c757d';
                    ?>
                    <option value="<?= $c['id'] ?>" data-color="<?= $color ?>" data-nombre="<?= $c['nombre'] ?>" <?= $c['id'] == $ciudad_default ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-insumax" onclick="mostrarForm()"><i class="bi bi-plus-circle"></i> Nuevo Insumo</button>
        </div>

        <div class="d-flex gap-3 mb-3 align-items-center flex-wrap">
            <div class="input-group" style="max-width: 280px;">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="buscar-insumo" class="form-control" placeholder="Buscar insumo...">
            </div>
            <div class="input-group" style="max-width: 220px;">
                <span class="input-group-text bg-white"><i class="bi bi-tag"></i></span>
                <select id="filtro-grupo" class="form-select">
                    <option value="">Todos los grupos</option>
                    <?php foreach($enums['grupos'] as $g): ?>
                    <option value="<?= $g ?>"><?= ucfirst($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <span id="contador-insumos" class="text-muted small"></span>
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
                        <th>Presentación</th>
                        <th>UND</th>
                        <th>Factor</th>
                        <th>Precio Compra</th>
                        <th>Precio/KG</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="insumos-body">
                    <?php foreach($insumos as $i): 
                        $factor = floatval($i['factor_conversion'] ?? 1);
                        $presentacion = htmlspecialchars($i['presentacion'] ?? '-');
                    ?>
                    <tr data-id="<?= $i['id'] ?>" data-grupo="<?= $i['grupo'] ?>">
                        <td style="border-left: 3px solid <?= $color_default ?>;"><?= htmlspecialchars($i['codigo'] ?? '') ?></td>
                        <td><span class="badge" style="background-color: <?= $colors['grupos'][$i['grupo']] ?? '#6c757d' ?>; color: <?= $i['grupo'] === 'quesos' ? '#000' : '#fff' ?>;"><?= strtoupper($i['grupo']) ?></span></td>
                        <td><?= htmlspecialchars($i['descripcion']) ?></td>
                        <td><small class="text-muted"><?= $presentacion ?></small></td>
                        <td><span class="badge bg-primary"><?= htmlspecialchars($i['unidad_compra'] ?? $i['unidad_medida'] ?? '-') ?></span></td>
                        <td>
                            <?php if ($factor > 1): ?>
                                <strong><?= number_format($factor, 0, ',', '.') ?>g</strong>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $i['precio_compra'] ? '$' . number_format($i['precio_compra'], 0, ',', '.') : '-' ?></td>
                        <td>
                            <?php if ($factor > 1 && $i['precio_kg']): ?>
                                <span class="badge bg-success">$<?= number_format($i['precio_kg'], 0, ',', '.') ?>/kg</span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
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
        <div class="modal-dialog modal-lg">
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
                            <div class="col-md-3">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" id="insumo-codigo" name="codigo">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Grupo</label>
                                <select class="form-select" id="insumo-grupo" name="grupo" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach($enums['grupos'] as $g): ?>
                                    <option value="<?= $g ?>"><?= ucfirst($g) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Und. Compra</label>
                                <select class="form-select" id="insumo-unidad-compra" name="unidad_compra" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach($unidades as $u): ?>
                                    <option value="<?= $u['codigo'] ?>" data-factor="<?= $u['a_gramos'] ?>"><?= $u['codigo'] ?> - <?= $u['nombre'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Und. Base</label>
                                <select class="form-select" id="insumo-unidad-base" name="unidad_base">
                                    <option value="G">G - Gramo</option>
                                    <option value="ML">ML - Mililitro</option>
                                    <option value="UND">UND - Unidad</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="insumo-descripcion" name="descripcion" required>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <label class="form-label">Factor (gramos por unidad)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="insumo-factor" name="factor_conversion" value="1" min="1" step="1">
                                    <span class="input-group-text">g</span>
                                </div>
                                <small class="text-muted">
                                    <a href="#" onclick="extraerFactor(); return false;"><i class="bi bi-magic"></i> Extraer de descripción</a>
                                </small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Presentación</label>
                                <input type="text" class="form-control" id="insumo-presentacion" name="presentacion" placeholder="Ej: Balde 5kg">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Precio/KG (calculado)</label>
                                <div class="form-control bg-light" id="precio-kg-display" style="height: calc(2.25rem + 2px);">
                                    <span class="text-muted">Ingrese precio</span>
                                </div>
                            </div>
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

    var coloresGrupo = <?= json_encode($colors['grupos']) ?>;

    document.addEventListener('DOMContentLoaded', function() {
        var ciudadSelect = document.getElementById('ciudad-select');
        if (ciudadSelect) {
            ciudadSelect.addEventListener('change', function() {
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
        }

        var form = document.getElementById('insumo-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                guardarInsumo();
            });
        }

        var unidadSelect = document.getElementById('insumo-unidad-compra');
        if (unidadSelect) {
            unidadSelect.addEventListener('change', function() {
                if (this.value === 'KG') {
                    document.getElementById('insumo-factor').value = 1000;
                    document.getElementById('insumo-unidad-base').value = 'G';
                    actualizarPrecioKG();
                } else if (this.value === 'GL') {
                    document.getElementById('insumo-factor').value = 3785;
                    document.getElementById('insumo-unidad-base').value = 'ML';
                    actualizarPrecioKG();
                } else if (this.value === 'FILETE') {
                    document.getElementById('insumo-factor').value = 120;
                    document.getElementById('insumo-unidad-base').value = 'G';
                    actualizarPrecioKG();
                }
            });
        }

        var pcompra = document.getElementById('insumo-pcompra');
        if (pcompra) pcompra.addEventListener('input', actualizarPrecioKG);
        
        var factor = document.getElementById('insumo-factor');
        if (factor) factor.addEventListener('input', actualizarPrecioKG);

        var filtroGrupo = document.getElementById('filtro-grupo');
        var buscarInput = document.getElementById('buscar-insumo');
        
        if (filtroGrupo) {
            filtroGrupo.addEventListener('change', filtrarTabla);
        }
        if (buscarInput) {
            buscarInput.addEventListener('input', filtrarTabla);
        }
    });

    function filtrarTabla() {
        var grupoSeleccionado = document.getElementById('filtro-grupo').value.toLowerCase();
        var textoBusqueda = document.getElementById('buscar-insumo').value.toLowerCase();
        var tbody = document.getElementById('insumos-body');
        var filas = tbody.querySelectorAll('tr');
        var contador = 0;
        
        filas.forEach(function(fila) {
            var grupoFila = (fila.getAttribute('data-grupo') || '').toLowerCase();
            var descripcion = (fila.querySelector('td:nth-child(3)')?.textContent || '').toLowerCase();
            var codigo = (fila.querySelector('td:first-child')?.textContent || '').toLowerCase();
            
            var coincideGrupo = grupoSeleccionado === '' || grupoFila === grupoSeleccionado;
            var coincideBusqueda = textoBusqueda === '' || 
                                   descripcion.includes(textoBusqueda) || 
                                   codigo.includes(textoBusqueda);
            
            if (coincideGrupo && coincideBusqueda) {
                fila.style.display = '';
                contador++;
            } else {
                fila.style.display = 'none';
            }
        });
        
        document.getElementById('contador-insumos').textContent = contador + ' de ' + filas.length + ' insumos';
    }

    function actualizarPrecioKG() {
        var precio = parseFloat(document.getElementById('insumo-pcompra').value) || 0;
        var factor = parseFloat(document.getElementById('insumo-factor').value) || 1;
        var display = document.getElementById('precio-kg-display');
        
        if (precio > 0 && factor > 0) {
            var precioKg = precio / factor * 1000;
            display.innerHTML = '<strong class="text-success">$' + precioKg.toLocaleString('es-CO', {maximumFractionDigits: 0}) + '/kg</strong>';
        } else {
            display.innerHTML = '<span class="text-muted">N/A</span>';
        }
    }

    function extraerFactor() {
        var descripcion = document.getElementById('insumo-descripcion').value;
        var patrones = [
            /(\d+(?:[.,]\d+)?)\s*kg/i,
            /(\d+(?:[.,]\d+)?)\s*kilogramo/i,
            /(\d+(?:[.,]\d+)?)\s*lb/i,
            /(\d+(?:[.,]\d+)?)\s*libra/i,
            /(\d+(?:[.,]\d+)?)\s*l(?!i)/i,
            /(\d+(?:[.,]\d+)?)\s*litro/i
        ];
        
        for (var i = 0; i < patrones.length; i++) {
            var match = descripcion.match(patrones[i]);
            if (match) {
                var valor = parseFloat(match[1].replace(',', '.'));
                var unidad = match[0].toLowerCase();
                var factor = valor;
                
                if (unidad.includes('kg') || unidad.includes('kilogramo')) {
                    factor = valor * 1000;
                    document.getElementById('insumo-unidad-base').value = 'G';
                } else if (unidad.includes('lb') || unidad.includes('libra')) {
                    factor = Math.round(valor * 453.592);
                    document.getElementById('insumo-unidad-base').value = 'G';
                } else if (unidad.includes('l') && !unidad.includes('li')) {
                    factor = valor * 1000;
                    document.getElementById('insumo-unidad-base').value = 'ML';
                }
                
                document.getElementById('insumo-factor').value = Math.round(factor);
                actualizarPrecioKG();
                alert('Factor extraído: ' + Math.round(factor) + 'g');
                return;
            }
        }
        alert('No se encontró peso en la descripción. Ingréselo manualmente.');
    }

    function cargarInsumos() {
        var url = basePath + '/api/insumos.php?action=list&ciudad_id=' + ciudadActual;
        document.getElementById('insumos-body').innerHTML = '<tr><td colspan="9" class="text-center py-3"><i class="bi bi-hourglass-split"></i> Cargando...</td></tr>';
        
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
            var factor = parseFloat(i.factor_conversion) || 1;
            var factorDisplay = factor > 1 ? '<strong>' + fmt(factor) + 'g</strong>' : '<span class="text-muted">-</span>';
            var precioKg = i.precio_kg ? '<span class="badge bg-success">$' + fmt(i.precio_kg) + '/kg</span>' : '<span class="text-muted">-</span>';
            var unidadDisplay = '<span class="badge bg-primary">' + (i.unidad_compra || i.unidad_medida || '-') + '</span>';
            var colorGrupo = coloresGrupo[i.grupo] || '#6c757d';
            var presentacion = i.presentacion || '-';
            
            html += '<tr data-id="' + i.id + '" data-grupo="' + i.grupo + '">' +
                '<td style="border-left: 3px solid ' + color + ';">' + (i.codigo || '') + '</td>' +
                '<td><span class="badge" style="background-color:' + colorGrupo + '; color:' + (i.grupo === 'quesos' ? '#000' : '#fff') + ';">' + (i.grupo || '').toUpperCase() + '</span></td>' +
                '<td>' + i.descripcion + '</td>' +
                '<td><small class="text-muted">' + presentacion + '</small></td>' +
                '<td>' + unidadDisplay + '</td>' +
                '<td>' + factorDisplay + '</td>' +
                '<td>' + (i.precio_compra ? '$' + fmt(i.precio_compra) : '-') + '</td>' +
                '<td>' + precioKg + '</td>' +
                '<td><button class="btn btn-sm btn-outline-primary" onclick="editar(' + i.id + ')"><i class="bi bi-pencil"></i></button>' +
                ' <button class="btn btn-sm btn-outline-danger" onclick="eliminar(' + i.id + ')"><i class="bi bi-trash"></i></button></td>' +
                '</tr>';
        });
        
        if (html === '') {
            html = '<tr><td colspan="9" class="text-center py-3 text-muted">No hay insumos registrados</td></tr>';
        }
        
        tbody.innerHTML = html;
        filtrarTabla();
    }

    function mostrarForm() {
        document.getElementById('form-title').innerHTML = '<i class="bi bi-plus-circle"></i> Nuevo Insumo';
        document.getElementById('insumo-id').value = '';
        document.getElementById('insumo-ciudad').value = ciudadActual;
        document.getElementById('insumo-codigo').value = '';
        document.getElementById('insumo-grupo').value = '';
        document.getElementById('insumo-descripcion').value = '';
        document.getElementById('insumo-unidad-compra').value = '';
        document.getElementById('insumo-unidad-base').value = 'G';
        document.getElementById('insumo-factor').value = '1';
        document.getElementById('insumo-presentacion').value = '';
        document.getElementById('insumo-pcompra').value = '';
        document.getElementById('insumo-pventa').value = '';
        document.getElementById('precio-kg-display').innerHTML = '<span class="text-muted">Ingrese precio</span>';
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }

    function editar(id) {
        var url = basePath + '/api/insumos.php?action=get&id=' + id + '&ciudad_id=' + ciudadActual;
        console.log('Cargando insumo ID:', id);
        fetch(url)
            .then(function(resp) { return resp.json(); })
            .then(function(res) {
                console.log('Datos recibidos:', res);
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
                document.getElementById('insumo-unidad-compra').value = i.unidad_compra || i.unidad_medida || '';
                document.getElementById('insumo-unidad-base').value = i.unidad_base || 'G';
                document.getElementById('insumo-factor').value = i.factor_conversion || 1;
                document.getElementById('insumo-presentacion').value = i.presentacion || '';
                document.getElementById('insumo-pcompra').value = i.precio_compra || '';
                document.getElementById('insumo-pventa').value = i.precio_venta || '';
                console.log('Precio compra:', i.precio_compra);
                console.log('Precio venta:', i.precio_venta);
                actualizarPrecioKG();
                var modal = new bootstrap.Modal(document.getElementById('formModal'));
                modal.show();
            })
            .catch(function(err) {
                console.error('Error:', err);
                alert('Error al cargar insumo');
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
