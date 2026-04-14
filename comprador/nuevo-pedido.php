<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireAuth();

if ($_SESSION['rol'] !== 'comprador') {
    header('Location: ../bienvenido.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$sede_id = $_SESSION['sede_id'] ?? null;

$stmt = $pdo->prepare("SELECT s.*, u.nombre as responsable FROM sedes s 
    LEFT JOIN usuarios u ON u.sede_id = s.id AND u.rol = 'comprador' 
    WHERE s.id = ?");
$stmt->execute([$sede_id]);
$sede = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM insumos WHERE activo = 1 ORDER BY FIELD(grupo, 'carnes', 'quesos', 'plaza', 'salsas', 'varios', 'aseo'), descripcion");
$stmt->execute();
$insumos = $stmt->fetchAll();

$grupos = [];
foreach ($insumos as $ins) {
    $grupos[$ins['grupo']][] = $ins;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Pedido - INSUMAX</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .grupo-card { margin-bottom: 20px; }
        .grupo-title { 
            background: var(--insumax-primary, #e85d24); 
            color: white; 
            padding: 10px 15px; 
            border-radius: 8px 8px 0 0;
            font-weight: 600;
        }
        .insumo-row {
            display: grid;
            grid-template-columns: 1fr 80px 100px;
            gap: 10px;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }
        .insumo-row:last-child { border-bottom: none; }
        .insumo-nombre { font-size: 14px; }
        .insumo-unidad { font-size: 12px; color: #666; }
        .insumo-qty { width: 80px; }
        .sede-info { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container py-4">
        <h4><i class="bi bi-plus-circle"></i> Nuevo Pedido</h4>
        
        <div class="sede-info">
            <strong><i class="bi bi-shop"></i> <?= htmlspecialchars($sede['nombre'] ?? 'Sin sede') ?></strong>
            <span class="text-muted"> | <?= htmlspecialchars($sede['ciudad'] ?? '') ?></span>
            <div class="mt-2">
                <label class="form-label small">Responsable del pedido</label>
                <input type="text" class="form-control" name="responsable" required 
                       value="<?= htmlspecialchars($_SESSION['nombre'] ?? '') ?>">
            </div>
            <div class="mt-2">
                <label class="form-label small">Fecha de pedido</label>
                <input type="date" class="form-control" name="fecha_pedido" required 
                       value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <form id="form-pedido" method="POST">
            <?php foreach ($grupos as $grupo => $items): ?>
            <div class="card grupo-card">
                <div class="grupo-title">
                    <i class="bi bi-tag"></i> <?= ucfirst($grupo) ?>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($items as $ins): ?>
                    <div class="insumo-row">
                        <div>
                            <div class="insumo-nombre"><?= htmlspecialchars($ins['descripcion']) ?></div>
                            <div class="insumo-unidad"><?= htmlspecialchars($ins['unidad_medida']) ?></div>
                        </div>
                        <input type="number" class="form-control form-control-sm insumo-qty" 
                               name="items[<?= $ins['id'] ?>][cantidad]" 
                               min="0" step="0.01" placeholder="0">
                        <input type="hidden" name="items[<?= $ins['id'] ?>][insumo_id]" value="<?= $ins['id'] ?>">
                        <input type="hidden" name="items[<?= $ins['id'] ?>][precio]" value="<?= $ins['precio_venta'] ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="mb-3">
                <label class="form-label">Observaciones</label>
                <textarea class="form-control" name="observaciones" rows="3" 
                          placeholder="Observaciones adicionales..."></textarea>
            </div>

            <button type="submit" class="btn btn-insumax">
                <i class="bi bi-check2-circle"></i> Enviar Pedido
            </button>
            <a href="mis-pedidos" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>

    <script>
    document.getElementById('form-pedido').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        
        // Recolectar items con cantidad > 0
        const items = [];
        formData.forEach((value, key) => {
            if (key.startsWith('items[') && value > 0) {
                const match = key.match(/items\[(\d+)\]\[cantidad\]/);
                if (match) {
                    items.push({ insumo_id: match[1], cantidad: value });
                }
            }
        });
        
        if (items.length === 0) {
            alert('Debe seleccionar al menos un producto');
            return;
        }

        const payload = {
            responsable: formData.get('responsable'),
            fecha_pedido: formData.get('fecha_pedido'),
            observaciones: formData.get('observaciones'),
            items: items
        };

        try {
            const resp = await fetch('../api/tickets.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await resp.json();
            if (result.success) {
                alert('Pedido creado: ' + result.codigo_ticket);
                window.location.href = 'mis-pedidos';
            } else {
                alert(result.error || 'Error al crear pedido');
            }
        } catch (err) {
            alert('Error de conexión');
        }
    });
    </script>
</body>
</html>