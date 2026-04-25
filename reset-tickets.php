<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

if ($_SESSION['rol'] !== 'admon') {
    die('Solo admin');
}

$pdo->exec('DELETE FROM ticket_items');
$pdo->exec('DELETE FROM tickets');

echo 'Tickets y ticket_items borrados';
echo '<br><a href="admin/tickets.php">Volver</a>';