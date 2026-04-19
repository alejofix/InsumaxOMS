<?php
session_start();
require_once __DIR__ . '/config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor ingrese email y contraseña';
    } else {
        $stmt = $pdo->prepare("SELECT id, nombre, email, password_hash, rol, apellido, celular, activo, sede_id, ciudad FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['apellido'] = $user['apellido'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['celular'] = $user['celular'] ?? '';
            $_SESSION['sede_id'] = $user['sede_id'] ?? null;
            $_SESSION['ciudad'] = $user['ciudad'] ?? null;
            
            // Redirect based on role
            $role_redirects = [
                'admon' => 'admin/dashboard.php',
                'dist' => 'distribuidor/mis-tickets.php',
                'comprador' => 'comprador/nuevo-pedido.php'
            ];
            
            $redirect = $role_redirects[$_SESSION['rol']] ?? 'login.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Credenciales inválidas';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INSUMAX - Login</title>
    <link rel="icon" type="image/png" href="assets/iconfinder.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="assets/logo.png" alt="INSUMAX OMS" style="height: 100px; max-width: 100%;">
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" name="email" required placeholder="correo@ejemplo.com">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" name="password" id="password" required placeholder="Ingrese su contraseña">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                <i class="bi bi-eye" id="eye-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-insumax w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                    </button>
                </form>
                
                <div class="text-center mt-4 text-muted">
                    <small>&copy; 2026 INSUMAX</small>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('eye-icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }
    </script>
</body>
</html>