<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$base_path = dirname($_SERVER['PHP_SELF']);
$logout_link = $base_path === '' || $base_path === '/admin' ? 'logout.php' : '../logout.php';
$home_link = match($_SESSION['rol'] ?? '') {
    'admon' => $base_path === '/admin' ? 'dashboard.php' : 'dashboard.php',
    'dist' => $base_path === '/distribuidor' ? 'mis-tickets.php' : '../distribuidor/mis-tickets.php',
    'comprador' => $base_path === '/comprador' ? 'nuevo-pedido.php' : '../comprador/nuevo-pedido.php',
    default => 'login.php'
};
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: var(--insumax-dark, #1a1a1a);">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= $home_link ?>"><strong>INSUMAX</strong></a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (($_SESSION['rol'] ?? '') === 'admon'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'tickets' ? 'active' : '' ?>" href="tickets.php">Tickets</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'insumos' ? 'active' : '' ?>" href="insumos.php">Insumos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'usuarios' ? 'active' : '' ?>" href="usuarios.php">Usuarios</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'sedes' ? 'active' : '' ?>" href="sedes.php">Sedes</a>
                </li>
                <?php elseif (($_SESSION['rol'] ?? '') === 'dist'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'mis-tickets' ? 'active' : '' ?>" href="mis-tickets.php">Mis Tickets</a>
                </li>
                <?php elseif (($_SESSION['rol'] ?? '') === 'comprador'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'nuevo-pedido' ? 'active' : '' ?>" href="nuevo-pedido.php">Nuevo Pedido</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'mis-pedidos' ? 'active' : '' ?>" href="mis-pedidos.php">Mis Pedidos</a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted small"><?= ucfirst($_SESSION['rol'] ?? '') ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= $logout_link ?>">Salir</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>