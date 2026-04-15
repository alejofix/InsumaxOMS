<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admon') {
    die('Acceso denegado');
}

$sedes = [
    ['nombre' => 'Prado', 'ciudad' => 'Bogotá', 'responsable' => 'Alejandra'],
    ['nombre' => 'Chapinero', 'ciudad' => 'Bogotá', 'responsable' => 'Alejandra'],
    ['nombre' => 'Modelia', 'ciudad' => 'Bogotá', 'responsable' => 'Alejandra'],
    ['nombre' => 'Manila', 'ciudad' => 'Medellín', 'responsable' => 'Angelin'],
    ['nombre' => 'Bello', 'ciudad' => 'Medellín', 'responsable' => 'Angelin'],
    ['nombre' => 'Sabaneta', 'ciudad' => 'Medellín', 'responsable' => 'Angelin'],
    ['nombre' => 'Pereira', 'ciudad' => 'Pereira', 'responsable' => 'Angelin'],
    ['nombre' => 'Barranquilla', 'ciudad' => 'Barranquilla', 'responsable' => 'Angelin'],
    ['nombre' => 'Cali', 'ciudad' => 'Cali', 'responsable' => 'Angelin'],
];

$pdo->query("UPDATE sedes SET activa = 0 WHERE activa = 1");

$stmt = $pdo->prepare("INSERT INTO sedes (nombre, ciudad, responsable, activa) VALUES (?, ?, ?, 1)");

foreach ($sedes as $s) {
    $stmt->execute([$s['nombre'], $s['ciudad'], $s['responsable']]);
    echo "✓ {$s['nombre']} - {$s['ciudad']} ({$s['responsable']})<br>";
}

echo "<br><strong>Sedes creadas exitosamente!</strong>";
echo "<br><a href='../admin/sedes.php'>Ver sedes</a>";
