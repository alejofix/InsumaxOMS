<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admon') {
    die('Acceso denegado');
}

$sedes = [
    ['nombre' => 'Prado', 'ciudad' => 'Bogotá'],
    ['nombre' => 'Chapinero', 'ciudad' => 'Bogotá'],
    ['nombre' => 'Modelia', 'ciudad' => 'Bogotá'],
    ['nombre' => 'Manila', 'ciudad' => 'Medellín'],
    ['nombre' => 'Bello', 'ciudad' => 'Medellín'],
    ['nombre' => 'Sabaneta', 'ciudad' => 'Medellín'],
    ['nombre' => 'Pereira', 'ciudad' => 'Pereira'],
    ['nombre' => 'Barranquilla', 'ciudad' => 'Barranquilla'],
    ['nombre' => 'Cali', 'ciudad' => 'Cali'],
];

$pdo->query("UPDATE sedes SET activa = 0 WHERE activa = 1");

$stmt = $pdo->prepare("INSERT INTO sedes (nombre, ciudad, activa) VALUES (?, ?, 1)");

foreach ($sedes as $s) {
    $stmt->execute([$s['nombre'], $s['ciudad']]);
    echo "✓ {$s['nombre']} - {$s['ciudad']}<br>";
}

echo "<br><strong>Sedes creadas exitosamente!</strong>";
echo "<br><a href='../admin/sedes.php'>Ver sedes</a>";
