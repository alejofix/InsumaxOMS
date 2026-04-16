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

$stmt = $pdo->query("SELECT s.*, c.nombre as ciudad_nombre 
    FROM sedes s 
    LEFT JOIN ciudades c ON s.ciudad_id = c.id 
    ORDER BY s.activa DESC, c.nombre, s.nombre");
$sedes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sedes - INSUMAX</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4><i class="bi bi-shop"></i> Gestión de Sedes</h4>
            <button class="btn btn-insumax" onclick="mostrarForm()"><i class="bi bi-plus-circle"></i> Nueva Sede</button>
        </div>

        <div class="row">
            <?php foreach($sedes as $s): 
                $color_ciudad = $colors['ciudades'][$s['ciudad_nombre']] ?? '#6c757d';
            ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100 <?= !$s['activa'] ? 'opacity-75' : '' ?>" style="border-left: 4px solid <?= $color_ciudad ?>;">
                    <?php if (!$s['activa']): ?>
                    <div class="card-header bg-secondary text-white">
                        <small><i class="bi bi-eye-slash"></i> OCULTA</small>
                    </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="mb-1"><?= htmlspecialchars($s['nombre']) ?></h5>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" onclick="toggleSede(<?= $s['id'] ?>, <?= $s['activa'] ?>)" title="<?= $s['activa'] ? 'Ocultar' : 'Mostrar' ?>">
                                    <i class="bi <?= $s['activa'] ? 'bi-eye' : 'bi-eye-slash' ?>"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editarSede(<?= $s['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                        </div>
                        <p class="mb-1" style="color: <?= $color_ciudad ?>;">
                            <i class="bi bi-geo-alt"></i> <strong><?= htmlspecialchars($s['ciudad_nombre'] ?? $s['ciudad'] ?? 'Sin ciudad') ?></strong>
                        </p>
                        <p class="mb-1"><i class="bi bi-person"></i> <span id="resp-<?= $s['id'] ?>"><?= htmlspecialchars($s['responsable'] ?? 'Sin responsable') ?></span></p>
                        <p class="mb-1"><i class="bi bi-pin-map"></i> <span id="dir-<?= $s['id'] ?>"><?= htmlspecialchars($s['direccion'] ?: 'Sin dirección') ?></span></p>
                        <p class="mb-0"><i class="bi bi-telephone"></i> <span id="tel-<?= $s['id'] ?>"><?= htmlspecialchars($s['telefono'] ?: 'Sin teléfono') ?></span></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal Editar -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Sede</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="form-edit">
                    <div class="modal-body">
                        <input type="hidden" id="edit-id" name="id">
                        <div class="mb-3">
                            <label class="form-label"><strong>Nombre</strong></label>
                            <input type="text" class="form-control" id="edit-nombre" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Ciudad</strong></label>
                            <input type="text" class="form-control" id="edit-ciudad" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-person"></i> Responsable</label>
                            <input type="text" class="form-control" id="edit-responsable" name="responsable" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-pin-map"></i> Dirección</label>
                            <input type="text" class="form-control" id="edit-direccion" name="direccion">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-telephone"></i> Teléfono</label>
                            <input type="text" class="form-control" id="edit-telefono" name="telefono">
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
    document.addEventListener('DOMContentLoaded', function() {
        var basePath = '<?php echo dirname($_SERVER['SCRIPT_NAME']) . '/..'; ?>';
        var csrfToken = '<?php echo csrfToken(); ?>';
        
        window.editarSede = function(id) {
            var url = basePath + '/api/sedes.php?action=get&id=' + id;
            console.log('GET URL:', url);
            fetch(url)
                .then(function(resp) { 
                    console.log('GET Status:', resp.status);
                    if (!resp.ok) throw new Error('HTTP ' + resp.status);
                    return resp.json(); 
                })
                .then(function(result) {
                    if (!result.success) {
                        alert('Error: ' + (result.error || 'desconocido'));
                        return;
                    }
                    var s = result.data;
                    document.getElementById('edit-id').value = s.id;
                    document.getElementById('edit-nombre').value = s.nombre;
                    document.getElementById('edit-ciudad').value = s.ciudad;
                    document.getElementById('edit-responsable').value = s.responsable || '';
                    document.getElementById('edit-direccion').value = s.direccion || '';
                    document.getElementById('edit-telefono').value = s.telefono || '';
                    new bootstrap.Modal(document.getElementById('editModal')).show();
                })
                .catch(function(err) {
                    alert('Error al cargar: ' + err.message);
                });
        };
        
        document.getElementById('form-edit').addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(e.target);
            formData.append('csrf_token', csrfToken);
            var url = basePath + '/api/sedes.php?action=update';
            console.log('POST URL:', url);
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(function(resp) { 
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                return resp.json(); 
            })
            .then(function(result) {
                if (result.success) {
                    var id = document.getElementById('edit-id').value;
                    document.getElementById('resp-' + id).textContent = formData.get('responsable') || 'Sin responsable';
                    document.getElementById('dir-' + id).textContent = formData.get('direccion') || 'Sin dirección';
                    document.getElementById('tel-' + id).textContent = formData.get('telefono') || 'Sin teléfono';
                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                    alert('Sede actualizada correctamente');
                } else {
                    alert('Error: ' + (result.error || 'desconocido'));
                }
            })
            .catch(function(err) {
                alert('Error al guardar: ' + err.message);
            });
        });
        
        window.toggleSede = function(id, activo) {
            var accion = activo ? 'ocultar' : 'mostrar';
            if (!confirm('¿Desea ' + accion + ' esta sede?\nLos pedidos no podrán usar sedes ocultas.')) return;
            
            var formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', csrfToken);
            var url = basePath + '/api/sedes.php?action=toggle';
            console.log('TOGGLE URL:', url);
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(function(resp) { 
                console.log('Response:', resp.status);
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                return resp.json(); 
            })
            .then(function(result) {
                console.log('Result:', result);
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'desconocido'));
                }
            })
            .catch(function(err) {
                console.error('Error:', err);
                alert('Error al cambiar: ' + err.message);
            });
        };
        
        window.mostrarForm = function() {
            alert('Formulario de nueva sede por implementar');
        };
    });
    </script>
</body>
</html>
