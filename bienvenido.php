<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'];
if ($rol === 'admon') {
    header('Location: admin/dashboard.php');
} elseif ($rol === 'dist') {
    header('Location: distribuidor/mis-tickets.php');
} elseif ($rol === 'comprador') {
    header('Location: comprador/nuevo-pedido.php');
} else {
    header('Location: login.php');
}
exit;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INSUMAX - Bienvenido</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container" style="background: #f5f5f5;">
        <div class="login-card">
            <div class="login-header" style="background: #2d8a4e;">
                <h1><i class="bi bi-check-circle"></i></h1>
                <div class="subtitle">Bienvenido a INSUMAX</div>
            </div>
            <div class="login-body text-center">
                <h4><?= htmlspecialchars($_SESSION['nombre'] . ' ' . ($_SESSION['apellido'] ?? '')) ?></h4>
                <p class="text-muted"><?= htmlspecialchars($_SESSION['email']) ?></p>
                
                <span class="badge bg-primary">
                    <?= htmlspecialchars(ucfirst($_SESSION['rol'])) ?>
                </span>
                
                <hr>
                
                <p class="text-muted">Sesión iniciada correctamente</p>
                
                <a href="logout" class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-left"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </div>
</body>
</html>