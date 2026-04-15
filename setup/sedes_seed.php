<?php
require_once __DIR__ . '/config/db.php';

$sedes = [
    ['nombre' => 'Prado', 'ciudad' => 'Bogotá', 'direccion' => 'Prado', 'telefono' => '', 'encargada' => 'Alejadra'],
    ['nombre' => 'Chapinero', 'ciudad' => 'Bogotá', 'direccion' => 'Chapinero', 'telefono' => '', 'encargada' => 'Alejadra'],
    ['nombre' => 'Modelia', 'ciudad' => 'Bogotá', 'direccion' => 'Modelia', 'telefono' => '', 'encargada' => 'Alejadra'],
    ['nombre' => 'Manila', 'ciudad' => 'Medellín', 'direccion' => 'Manila', 'telefono' => '', 'encargada' => 'Angelin'],
    ['nombre' => 'Bello', 'ciudad' => 'Medellín', 'direccion' => 'Bello', 'telefono' => '', 'encargada' => 'Angelin'],
    ['nombre' => 'Sabaneta', 'ciudad' => 'Medellín', 'direccion' => 'Sabaneta', 'telefono' => '', 'encargada' => 'Angelin'],
    ['nombre' => 'Pereira Centro', 'ciudad' => 'Pereira', 'direccion' => 'Pereira', 'telefono' => '', 'encargada' => 'Angelin'],
    ['nombre' => 'Barranquilla Centro', 'ciudad' => 'Barranquilla', 'direccion' => 'Barranquilla', 'telefono' => '', 'encargada' => 'Angelin'],
    ['nombre' => 'Cali Centro', 'ciudad' => 'Cali', 'direccion' => 'Cali', 'telefono' => '', 'encargada' => 'Angelin'],
];

$pdo->query("UPDATE sedes SET activa = 0 WHERE activa = 1");

$stmt = $pdo->prepare("INSERT INTO sedes (nombre, ciudad, direccion, telefono, activa) VALUES (?, ?, ?, ?, 1)");

foreach ($sedes as $s) {
    $stmt->execute([$s['nombre'], $s['ciudad'], $s['direccion'], $s['telefono']]);
    echo "Insertada: {$s['nombre']} - {$s['ciudad']}\n";
}

echo "\nSedes creadas exitosamente!";
