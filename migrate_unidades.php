<?php
/**
 * Script de Migración: Sistema de Unidades en Gramos
 * Ejecutar UNA SOLA VEZ
 */

require_once __DIR__ . '/config/db.php';

echo "=== INSUMAX OMS - Migración Sistema de Unidades ===\n\n";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // FASE 1: Crear tabla unidades_medida
    echo "[1/4] Creando tabla unidades_medida...\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS unidades_medida (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(10) NOT NULL UNIQUE,
            nombre VARCHAR(50) NOT NULL,
            tipo ENUM('peso', 'volumen', 'und') DEFAULT 'und',
            a_gramos DECIMAL(12,4) DEFAULT 1,
            activo TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB
    ");
    
    $unidades = [
        ['G', 'Gramo', 'peso', 1],
        ['KG', 'Kilogramo', 'peso', 1000],
        ['ML', 'Mililitro', 'volumen', 1],
        ['L', 'Litro', 'volumen', 1000],
        ['GL', 'Galón', 'volumen', 3785],
        ['UND', 'Unidad', 'und', 1],
        ['CAJA', 'Caja', 'und', 1],
        ['BLQ', 'Bloque', 'und', 1],
        ['BALDE', 'Balde', 'und', 1],
        ['PAQ', 'Paquete', 'und', 1],
        ['CJ', 'Cajetilla', 'und', 1],
        ['PQT', 'Paquetico', 'und', 1],
        ['FILETE', 'Filete/Porción', 'peso', 120]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO unidades_medida (codigo, nombre, tipo, a_gramos) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), tipo=VALUES(tipo), a_gramos=VALUES(a_gramos)
    ");
    
    foreach ($unidades as $u) {
        $stmt->execute($u);
    }
    echo "  ✓ Tabla creada y datos precargados\n\n";
    
    // FASE 2: Crear tabla insumos_unidades
    echo "[2/4] Creando tabla insumos_unidades...\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS insumos_unidades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            insumo_id INT NOT NULL UNIQUE,
            unidad_compra VARCHAR(10) NOT NULL,
            unidad_base VARCHAR(10) DEFAULT 'G',
            factor_conversion DECIMAL(12,4) NOT NULL DEFAULT 1,
            presentacion VARCHAR(100),
            FOREIGN KEY (insumo_id) REFERENCES insumos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    echo "  ✓ Tabla creada\n\n";
    
    // FASE 3: Migrar datos existentes
    echo "[3/4] Migrando insumos existentes...\n";
    
    $stmt = $pdo->query("SELECT id, unidad_medida, descripcion FROM insumos WHERE activo = 1");
    $insumos = $stmt->fetchAll();
    
    $migrados = 0;
    $stmtInsert = $pdo->prepare("
        INSERT INTO insumos_unidades (insumo_id, unidad_compra, unidad_base, factor_conversion, presentacion)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            unidad_compra = VALUES(unidad_compra),
            unidad_base = VALUES(unidad_base),
            factor_conversion = VALUES(factor_conversion),
            presentacion = VALUES(presentacion)
    ");
    
    foreach ($insumos as $i) {
        $unidad = $i['unidad_medida'];
        $factor = 1;
        $base = 'UND';
        $presentacion = $i['descripcion'];
        
        switch ($unidad) {
            case 'KG':
                $factor = 1000;
                $base = 'G';
                $presentacion = 'Kilogramo';
                break;
            case 'GL':
                $factor = 3785;
                $base = 'ML';
                $presentacion = 'Galón 3.8L';
                break;
            case 'FILETE':
                $factor = 120;
                $base = 'G';
                $presentacion = 'Porción filete 120g';
                break;
            case 'CAJA':
            case 'BALDE':
            case 'BLQ':
            case 'PAQ':
            case 'PQT':
            case 'CJ':
            case 'UND':
            case 'UNIDAD':
                $factor = 1;
                $base = ($unidad === 'UND' || $unidad === 'UNIDAD') ? 'UND' : 'G';
                $presentacion = $i['descripcion'];
                break;
        }
        
        $stmtInsert->execute([$i['id'], $unidad, $base, $factor, $presentacion]);
        $migrados++;
    }
    echo "  ✓ {$migrados} insumos migrados\n\n";
    
    // FASE 4: Verificación
    echo "[4/4] Verificando migración...\n";
    
    $stmt = $pdo->query("
        SELECT u.*, i.descripcion 
        FROM insumos_unidades u 
        JOIN insumos i ON u.insumo_id = i.id 
        ORDER BY i.grupo, i.descripcion
    ");
    $resultados = $stmt->fetchAll();
    
    echo "\n--- RESULTADO DE MIGRACIÓN ---\n";
    printf("%-6s %-30s %-10s %-10s %s\n", "COD", "DESCRIPCION", "UND", "FACTOR", "PRESENTACION");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($resultados as $r) {
        printf("%-6s %-30s %-10s %-10s %s\n", 
            substr($r['unidad_compra'], 0, 6),
            substr($r['descripcion'], 0, 28),
            $r['unidad_base'],
            $r['factor_conversion'],
            substr($r['presentacion'], 0, 25)
        );
    }
    
    $total = $pdo->query("SELECT COUNT(*) FROM insumos_unidades")->fetchColumn();
    $pendientes = $pdo->query("SELECT COUNT(*) FROM insumos_unidades WHERE factor_conversion = 1 AND unidad_compra IN ('CAJA', 'BALDE', 'BLQ')")->fetchColumn();
    
    echo "\n--- RESUMEN ---\n";
    echo "Total insumos migrados: {$total}\n";
    echo "Pendientes de factor (CAJA/BALDE/BLQ): {$pendientes}\n";
    echo "\n✓ MIGRACIÓN COMPLETADA EXITOSAMENTE\n";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
