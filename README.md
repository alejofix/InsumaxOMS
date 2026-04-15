# INSUMAX OMS

Sistema de gestión de insumos y pedidos.

## Ciudades y Colores

Cada ciudad tiene un color representativo usado en la interfaz:

| Ciudad | Color | Hex | Borde |
|--------|-------|-----|-------|
| Bogotá | Azul oscuro | `#1E3A5F` | Capital, institucionalidad |
| Medellín | Verde esmeralda | `#00897B` | Ciudad de la eterna primavera |
| Pereira | Naranja cálido | `#F57C00` | Calidez |
| Barranquilla | Rojo intenso | `#C62828` | Energía, carnaval |
| Cali | Morado vibrante | `#7B1FA2` | Salsa, pasión |

## Modelo de Datos: Precios por Ciudad

Los precios de insumos varían según la ciudad. La estructura de datos es:

### Tablas relacionadas

```
ciudades (1) ──────< (N) insumos_precios (N) ──────> (1) insumos
   │
   │
   └────────────> (N) sedes
```

### Tabla: ciudades
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | PK |
| nombre | VARCHAR(80) | Nombre de la ciudad |
| codigo | VARCHAR(10) | Código corto (ej: BOG) |
| activa | TINYINT | 1=activa, 0=inactiva |

### Tabla: insumos_precios
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | PK |
| insumo_id | INT | FK a insumos |
| ciudad_id | INT | FK a ciudades |
| precio_compra | DECIMAL(12,2) | Precio al proveedor |
| precio_venta | DECIMAL(12,2) | Precio al cliente |

### Relación
- Cada insumo puede tener precios diferentes en cada ciudad
- La relación es **única**: un insumo tiene un solo precio por ciudad
- Al crear un pedido, el precio se toma según la ciudad de la sede del comprador

## Migración SQL

El archivo `sql/migration_precios_ciudad.sql` contiene:
1. Creación de tabla `ciudades`
2. Creación de tabla `insumos_precios`
3. Población inicial de datos
4. Migración de sedes con FK a ciudades

## API Endpoints

### Insumos
- `GET /api/insumos.php?action=list&ciudad_id=X` - Listar con precios de ciudad
- `POST /api/insumos.php?action=save` - Guardar insumo y precio por ciudad
- `GET /api/insumos.php?action=get&id=X&ciudad_id=Y` - Obtener insumo con precio

### Tickets
- Los precios se obtienen automáticamente según la ciudad de la sede del comprador
