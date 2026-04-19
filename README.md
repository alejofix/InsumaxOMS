# INSUMAX OMS

Sistema de gestión de insumos y pedidos para distribución.

## Fuente de Datos - Insumos

El archivo principal de referencia para insumos es:

```
/home/alejo_fix/Escritorio/borrar/insumax/INSUMOS.xlsx
```

👉 Order Management System (Sistema de Gestión de Órdenes)

Este archivo contiene el catálogo base de insumos organizados por grupos:
- **CARNES**: Carne molida, desmechar, costilla cerdo, pollo filete, tocineta
- **QUESOS**: Queso crema, philadelphia, mozzarella, cheddar
- **PLAZA**: Vegetales y frutas (cebolla, lechuga, tomate, etc.)
- **MAYORISTA**: Aceites, huevos, condimentos, harinas, etc.

> **Nota**: Este archivo Excel es la referencia para crear/actualizar insumos en la BD.

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

### Diagrama de relaciones

```
ciudades (1) ──────< (N) insumos_precios (N) ──────> (1) insumos
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

### Tabla: insumos
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | PK |
| codigo | VARCHAR(20) | Código interno |
| grupo | ENUM | carnes, quesos, plaza, salsas, varios, aseo |
| descripcion | VARCHAR(200) | Nombre del insumo |
| activo | TINYINT | 1=activo, 0=oculto |

### Tabla: insumos_unidades
| Campo | Tipo | Descripción |
|-------|------|-------------|
| insumo_id | INT | FK a insumos |
| unidad_compra | VARCHAR(10) | KG, CAJA, BLQ, etc. |
| unidad_base | VARCHAR(10) | G, ML, UND |
| factor_conversion | DECIMAL | Gramos por unidad de compra |
| presentacion | VARCHAR(100) | Descripción de presentación |

### Tabla: insumos_precios
| Campo | Tipo | Descripción |
|-------|------|-------------|
| insumo_id | INT | FK a insumos |
| ciudad_id | INT | FK a ciudades |
| precio_compra | DECIMAL(12,2) | Precio al proveedor |
| precio_venta | DECIMAL(12,2) | Precio al cliente |

## Migración SQL

### Archivo principal
`sql/insumax.sql` - Dump completo de la base de datos con todos los datos actuales.

### Scripts de migración
`migrate_unidades.php` - Script PHP para migrar/adicionar unidades de medida y presentaciones.

### Relación de precios
- Cada insumo puede tener precios diferentes en cada ciudad
- La relación es **única**: un insumo tiene un solo precio por ciudad
- Al crear un pedido, el precio se toma según la ciudad de la sede del comprador

## Gestión de Insumos (Admin)

### Catálogo de Insumos (`admin/insumos.php`)

Funcionalidades:
- **Selector de ciudad**: Seleccionar ciudad para ver/editar precios
- **Filtro por grupo**: Filtrar por carnes, quesos, plaza, salsas, varios, aseo
- **Búsqueda**: Buscar por código o descripción
- **Toggle ocultar/mostrar**: Botón de ojo para ocultar insumos (no aparecen en pedidos)
- **Edición**: Modificar datos del insumo y precios por ciudad

### API Endpoints

#### Insumos
- `GET /api/insumos.php?action=list&ciudad_id=X` - Listar insumos activos con precios
- `POST /api/insumos.php?action=save` - Crear/actualizar insumo y precio
- `GET /api/insumos.php?action=get&id=X&ciudad_id=Y` - Obtener insumo con precio
- `POST /api/insumos.php?action=toggle` - Ocultar/mostrar insumo

#### Tickets
- Los precios se obtienen automáticamente según la ciudad de la sede del comprador

## Roles de Usuario

| Rol | Descripción | Acceso |
|-----|-------------|--------|
| admon | Administrador | Panel admin completo, gestión de insumos |
| dist | Distribuidor | Ver pedidos, gestionar entregas |
| comprador | Comprador | Crear pedidos para su sede |
