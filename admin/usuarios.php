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

$stmt = $pdo->query("SELECT u.*, s.nombre as sede_nombre FROM usuarios u LEFT JOIN sedes s ON u.sede_id = s.id WHERE u.activo = 1 ORDER BY u.nombre");
$usuarios = $stmt->fetchAll();
$sedes = $pdo->query("SELECT id, nombre, ciudad FROM sedes WHERE activa = 1")->fetchAll();
$ciudades = $pdo->query("SELECT DISTINCT ciudad FROM sedes WHERE activa = 1 ORDER BY ciudad")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - INSUMAX</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4><i class="bi bi-people"></i> Gestión de Usuarios</h4>
            <button class="btn btn-insumax" onclick="mostrarForm()"><i class="bi bi-person-plus"></i> Nuevo Usuario</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Celular</th>
                        <th>Rol</th>
                        <th>Ciudad</th>
                        <th>Sede</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios as $u): ?>
                    <tr id="row-<?= $u['id'] ?>">
                        <td><?= htmlspecialchars($u['nombre'] . ' ' . ($u['apellido'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['celular'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= $u['rol']=='admon'?'danger':($u['rol']=='dist'?'primary':'success') ?>"><?= strtoupper($u['rol']) ?></span></td>
                        <td id="ciudad-<?= $u['id'] ?>"><?= htmlspecialchars($u['ciudad'] ?? '-') ?></td>
                        <td id="sede-<?= $u['id'] ?>"><?= htmlspecialchars($u['sede_nombre'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editar(<?= $u['id'] ?>)"><i class="bi bi-pencil"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Crear/Editar Usuario -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title"><i class="bi bi-person-plus"></i> Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="form-usuario">
                    <div class="modal-body">
                        <input type="hidden" id="user-id" name="id">
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="user-nombre" name="nombre" required>
                            </div>
                            <div class="col">
                                <label class="form-label">Apellido</label>
                                <input type="text" class="form-control" id="user-apellido" name="apellido" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="user-email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Celular</label>
                            <input type="text" class="form-control" id="user-celular" name="celular">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select class="form-select" id="user-rol" name="rol" required>
                                <option value="comprador">Comprador</option>
                                <option value="dist">Distribuidor</option>
                                <option value="admon">Administrador</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ciudad</label>
                            <select class="form-select" id="user-ciudad" name="ciudad" required>
                                <option value="">Seleccionar ciudad</option>
                                <?php foreach($ciudades as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sede</label>
                            <select class="form-select" id="user-sede" name="sede_id" required>
                                <option value="">Seleccionar sede</option>
                            </select>
                        </div>
                        <div class="mb-3" id="password-section">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" id="user-password" name="password">
                            <small class="text-muted">Solo requerido para nuevos usuarios</small>
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
        var sedesPorCiudad = <?php echo json_encode(array_reduce($sedes, function($carry, $s) {
            $carry[$s['id']] = $s['nombre'];
            return $carry;
        }, [])); ?>;
        var todasSedes = <?php echo json_encode($sedes); ?>;

        var ciudadSelect = document.getElementById('user-ciudad');
        var sedeSelect = document.getElementById('user-sede');

        function filtrarSedesPorCiudad(ciudad) {
            sedeSelect.innerHTML = '<option value="">Seleccionar sede</option>';
            if (!ciudad) return;
            var filtered = todasSedes.filter(function(s) { return s.ciudad === ciudad; });
            filtered.forEach(function(s) {
                var opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.nombre;
                sedeSelect.appendChild(opt);
            });
        }

        ciudadSelect.addEventListener('change', function() {
            filtrarSedesPorCiudad(this.value);
        });

        window.mostrarForm = function() {
            document.getElementById('user-id').value = '';
            document.getElementById('user-nombre').value = '';
            document.getElementById('user-apellido').value = '';
            document.getElementById('user-email').value = '';
            document.getElementById('user-celular').value = '';
            document.getElementById('user-rol').value = 'comprador';
            document.getElementById('user-ciudad').value = '';
            document.getElementById('user-sede').innerHTML = '<option value="">Seleccionar sede</option>';
            document.getElementById('user-password').value = '';
            document.getElementById('password-section').style.display = 'block';
            document.getElementById('modal-title').innerHTML = '<i class="bi bi-person-plus"></i> Nuevo Usuario';
            new bootstrap.Modal(document.getElementById('userModal')).show();
        };

        window.editar = function(id) {
            fetch(basePath + '/api/usuarios.php?action=list')
                .then(function(resp) { return resp.json(); })
                .then(function(result) {
                    if (!result.success) {
                        alert('Error al cargar usuarios');
                        return;
                    }
                    var usuario = result.data.find(function(u) { return u.id == id; });
                    if (!usuario) {
                        alert('Usuario no encontrado');
                        return;
                    }
                    document.getElementById('user-id').value = usuario.id;
                    document.getElementById('user-nombre').value = usuario.nombre;
                    document.getElementById('user-apellido').value = usuario.apellido;
                    document.getElementById('user-email').value = usuario.email;
                    document.getElementById('user-celular').value = usuario.celular || '';
                    document.getElementById('user-rol').value = usuario.rol;
                    document.getElementById('user-ciudad').value = usuario.ciudad || '';
                    filtrarSedesPorCiudad(usuario.ciudad || '');
                    document.getElementById('user-sede').value = usuario.sede_id || '';
                    document.getElementById('user-password').value = '';
                    document.getElementById('password-section').style.display = 'none';
                    document.getElementById('modal-title').innerHTML = '<i class="bi bi-pencil"></i> Editar Usuario';
                    new bootstrap.Modal(document.getElementById('userModal')).show();
                })
                .catch(function(err) {
                    alert('Error: ' + err.message);
                });
        };

        document.getElementById('form-usuario').addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(e.target);
            formData.append('csrf_token', csrfToken);
            
            fetch(basePath + '/api/usuarios.php?action=save', {
                method: 'POST',
                body: formData
            })
            .then(function(resp) { return resp.json(); })
            .then(function(result) {
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'desconocido'));
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        });
    });
    </script>
</body>
</html>