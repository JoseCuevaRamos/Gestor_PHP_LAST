# 🔍 Respuestas Críticas del Backend PHP - Para Equipo de Métricas (Python)

**Fecha:** 5 de noviembre de 2025  
**Versión Backend:** PHP 8.1 con Slim Framework + Eloquent ORM  
**Base de Datos:** MySQL 8.0  
**Destinatario:** Equipo de desarrollo de métricas (Python/Dashboard)

---

## 📋 SECCIÓN 1: Tabla COLUMNAS

### ❓ 1.1 ¿Todas las columnas tienen `status_fijas` definido o hay columnas NULL?

**✅ Respuesta: HAY COLUMNAS CON `NULL`**

**Esquema de la tabla:**
```sql
status_fijas ENUM('1', '2') NULL DEFAULT NULL
```

**Tipos de columnas:**

| `tipo_columna` | `status_fijas` | Descripción |
|----------------|----------------|-------------|
| `'normal'` | `NULL` | Columnas regulares del tablero (Por Hacer, Backlog, etc.) |
| `'fija'` | `'1'` | Columna fija "En Progreso" |
| `'fija'` | `'2'` | Columna fija "Finalizado" |

**⚠️ IMPORTANTE PARA MÉTRICAS:**
```python
# ✅ CORRECTO - Filtrar por NULL
tareas_pendientes = tareas.filter(columna__status_fijas__isnull=True)

# ❌ INCORRECTO - NULL no es '0' ni string vacío
tareas_pendientes = tareas.filter(columna__status_fijas='0')  # ❌ NO existe
```

---

### ❓ 1.2 ¿Qué valores puede tener `status_fijas`?

**✅ Respuesta: SOLO `'1'`, `'2'` o `NULL`**

```sql
status_fijas ENUM('1', '2') NULL
```

**Valores válidos:**
- `'1'` = En Progreso (STRING)
- `'2'` = Finalizado/Completado (STRING)
- `NULL` = Columna normal (sin status fijo)

**⚠️ NO EXISTE:**
- ❌ `'0'` NO es un valor válido
- ❌ `3`, `4`, etc. NO existen
- ❌ Números enteros (solo STRING)

**Constantes en código PHP:**
```php
// src/Conduit/Models/Columna.php
const STATUS_FIJA_PROGRESO = '1';
const STATUS_FIJA_FINALIZADO = '2';
```

---

### ❓ 1.3 ¿Una tarea SIEMPRE debe tener `id_columna` o puede ser NULL?

**✅ Respuesta: SIEMPRE debe tener `id_columna` (NOT NULL)**

**Esquema de la tabla:**
```sql
id_columna INT NOT NULL
```

**🚨 GARANTÍA:**
- ✅ **Toda tarea activa** (`status='0'`) SIEMPRE tiene `id_columna` válido
- ✅ MySQL rechaza INSERTs sin `id_columna` (restricción NOT NULL)
- ✅ Hay FOREIGN KEY hacia la tabla `columnas`

**⚠️ PARA MÉTRICAS:**
```python
# ✅ SEGURO - No necesitas validar NULL
tareas_en_progreso = Tarea.objects.filter(
    columna__status_fijas='1',
    status='0'
)

# ❌ INNECESARIO - id_columna nunca es NULL
tareas = Tarea.objects.exclude(columna__isnull=True)  # ❌ Redundante
```

---

### ❓ 1.4 ¿Qué significa cuando `id_columna` es NULL?

**✅ Respuesta: NUNCA es NULL (por restricción de BD)**

**Situaciones teóricas (NO ocurren en producción):**
- ❌ Si fuera NULL → La tarea no existe en ninguna columna
- ❌ MySQL rechaza esto por la restricción NOT NULL

**🔍 Verificación actual:**
```sql
-- 0 resultados (confirmado)
SELECT * FROM tareas WHERE id_columna IS NULL;
```

---

## 📋 SECCIÓN 2: Tabla TAREAS

### ❓ 2.1 ¿El campo `status` solo tiene '0' (activa) y '1' (eliminada)?

**✅ Respuesta: SÍ, solo '0' y '1'**

**Esquema de la tabla:**
```sql
status ENUM('0', '1') DEFAULT '0'
```

**Valores:**
- `'0'` = Tarea activa (visible en el tablero)
- `'1'` = Tarea eliminada (soft delete, no visible)

**⚠️ IMPORTANTE PARA MÉTRICAS:**
```python
# ✅ SIEMPRE filtrar por status='0' para métricas
tareas_activas = Tarea.objects.filter(status='0')

# ❌ Si olvidas este filtro, contarás tareas eliminadas
tareas_totales = Tarea.objects.all()  # ❌ Incluye eliminadas
```

---

### ❓ 2.2 ¿Una tarea puede tener `id_columna` apuntando a una columna que NO existe?

**⚠️ Respuesta: TEÓRICAMENTE SÍ, pero es un caso edge**

**Escenario problemático:**
```sql
-- Columna 10 existe (status='0')
INSERT INTO tareas (id_columna, ...) VALUES (10, ...);

-- Luego alguien elimina la columna (soft delete)
UPDATE columnas SET status='1' WHERE id_columna=10;

-- Ahora la tarea apunta a columna "eliminada"
```

**🔒 PROTECCIÓN ACTUAL:**
- ✅ Las columnas fijas **NO se pueden eliminar** (bloqueado en código)
- ✅ Las columnas normales **NO se pueden eliminar si tienen tareas**

**Código PHP que lo previene:**
```php
// src/Conduit/Controllers/Columna/ColumnaController.php (línea 319)
if ($columna->tipo_columna === Columna::TIPO_FIJA) {
    return 'No se puede eliminar una columna fija.';
}

$tareasAsociadas = $columna->tareas()->where('status', '0')->count();
if ($tareasAsociadas > 0) {
    return 'No se puede eliminar esta columna porque tiene tareas asociadas.';
}
```

**⚠️ PARA MÉTRICAS (query segura):**
```python
# ✅ FILTRO SEGURO - Solo columnas activas
tareas_validas = Tarea.objects.filter(
    status='0',
    columna__status='0'  # Asegura que la columna existe
)
```

---

### ❓ 2.3 ¿Es posible que una tarea tenga `started_at` pero NO tenga `id_columna`?

**✅ Respuesta: NO, porque `id_columna` es NOT NULL**

**Pero sí puede tener `started_at` sin estar en columna "En Progreso":**

**Escenarios posibles:**
1. ✅ Tarea movida a "En Progreso" → `started_at` se establece automáticamente
2. ✅ Tarea movida FUERA de "En Progreso" → `started_at` se limpia (`NULL`)
3. ✅ Tarea movida a "Finalizado" → `started_at` puede estar o no

**Código PHP automático (línea 318):**
```php
if ($columnaDestino->status_fijas === Columna::STATUS_FIJA_PROGRESO) {
    $t->started_at = $t->started_at ?? Carbon::now();  // Establece si no existe
    $t->completed_at = null;  // Limpia completed
}
```

**🔍 Datos reales del sistema:**
```sql
-- Tarea en "Hecho" con started_at
id_tarea=5, columna="Hecho" (status_fijas='2'), started_at='2025-11-05', completed_at='2025-11-05' ✅

-- Tarea en "tet" (En Progreso) sin started_at
id_tarea=10, columna="tet" (status_fijas='1'), started_at=NULL ❌ INCONSISTENCIA
```

**⚠️ INCONSISTENCIA DETECTADA:**
```python
# 🚨 PROBLEMA: Hay tareas en "En Progreso" sin started_at
# Esto puede ocurrir si:
# 1. La tarea se creó directamente en "En Progreso"
# 2. Hubo un bug en versión anterior
# 3. Migración de datos incompleta
```

---

### ❓ 2.4 ¿El campo `completed_at` se actualiza automáticamente cuando mueve a columna finalizada?

**✅ Respuesta: SÍ, automáticamente**

**Código PHP (línea 325):**
```php
elseif ($columnaDestino->status_fijas === Columna::STATUS_FIJA_FINALIZADO) {
    $t->completed_at = Carbon::now();  // Actualiza SIEMPRE al mover a Finalizado
    $t->save();
}
```

**Comportamiento:**
- ✅ Al mover a columna con `status_fijas='2'` → `completed_at = NOW()`
- ✅ Sobrescribe `completed_at` anterior si existía
- ❌ NO se actualiza si mueve a otras columnas

**⚠️ IMPORTANTE:**
```python
# ✅ completed_at SIEMPRE refleja la última vez que se movió a Finalizado
# Si una tarea se mueve de Finalizado → En Progreso → Finalizado otra vez,
# completed_at tendrá la fecha MÁS RECIENTE
```

---

## 📋 SECCIÓN 3: Consistencia de Datos

### ❓ 3.1 ¿Puede haber tareas en columna con `status_fijas='2'` pero SIN `completed_at`?

**⚠️ Respuesta: SÍ, ES POSIBLE (inconsistencia detectada)**

**Datos reales encontrados:**
```sql
-- Tarea en columna "Hecho" (status_fijas='2') SIN completed_at
id_tarea=8, titulo='dfc', columna='Hecho' (status_fijas='2'), completed_at=NULL ❌
```

**Causas posibles:**
1. ✅ Tarea creada directamente en columna "Hecho" (sin pasar por movimiento)
2. ✅ Columna cambió de tipo después de que la tarea ya estaba ahí
3. ✅ Migración de datos desde sistema anterior
4. ✅ Bug en código anterior (ya corregido)

**⚠️ PARA MÉTRICAS:**
```python
# 🚨 NO CONFIAR SOLO EN completed_at para "tareas completadas"
# ✅ USAR status_fijas de la columna como fuente de verdad

# ✅ CORRECTO
tareas_completadas = Tarea.objects.filter(
    status='0',
    columna__status_fijas='2'  # Usar status_fijas, NO completed_at
)

# ❌ INCORRECTO - Omitirá tareas sin completed_at
tareas_completadas = Tarea.objects.filter(
    status='0',
    completed_at__isnull=False  # ❌ Inconsistente con la realidad
)
```

---

### ❓ 3.2 ¿Puede haber tareas con `completed_at` pero en columna con `status_fijas='1'`?

**⚠️ Respuesta: NO debería, pero técnicamente es posible**

**Escenario hipotético:**
1. Tarea se mueve a "Finalizado" (`status_fijas='2'`) → `completed_at` se establece
2. Tarea se mueve de vuelta a "En Progreso" (`status_fijas='1'`)
3. **PROBLEMA:** El código actual NO limpia `completed_at` al retroceder

**Código actual (línea 318):**
```php
if ($columnaDestino->status_fijas === Columna::STATUS_FIJA_PROGRESO) {
    $t->started_at = $t->started_at ?? Carbon::now();
    $t->completed_at = null;  // ✅ SÍ limpia completed_at
}
```

**✅ CORRECCIÓN IMPLEMENTADA:**
El código SÍ limpia `completed_at` al mover a "En Progreso", por lo que esta inconsistencia NO debería ocurrir.

**⚠️ PARA MÉTRICAS:**
```python
# ✅ USAR columna.status_fijas, NO completed_at
# Si existe completed_at pero status_fijas='1', la columna es la verdad

tareas_en_progreso = Tarea.objects.filter(
    status='0',
    columna__status_fijas='1'
    # completed_at puede ser NULL o tener valor, no importa
)
```

---

### ❓ 3.3 ¿Qué pasa si elimino una columna? ¿Se actualiza `id_columna` en tareas a NULL?

**✅ Respuesta: NO SE PUEDE ELIMINAR columnas con tareas**

**Protección en código (línea 327):**
```php
$tareasAsociadas = $columna->tareas()->where('status', '0')->count();

if ($tareasAsociadas > 0) {
    return $response->withJson([
        'error' => 'No se puede eliminar esta columna porque tiene X tareas asociadas.'
    ], 400);
}

// Además, columnas FIJAS nunca se pueden eliminar
if ($columna->tipo_columna === Columna::TIPO_FIJA) {
    return $response->withJson([
        'error' => 'No se puede eliminar una columna fija.'
    ], 400);
}
```

**Comportamiento:**
- ✅ Eliminación es **SOFT DELETE** (`status='1'`, la columna sigue en BD)
- ✅ `id_columna` en tareas **NO cambia** (sigue apuntando a la columna)
- ✅ La columna queda "invisible" pero los datos persisten

**⚠️ PARA MÉTRICAS:**
```python
# ✅ FILTRO RECOMENDADO - Solo columnas activas
tareas_validas = Tarea.objects.filter(
    status='0',
    columna__status='0'  # Excluye tareas de columnas eliminadas
)

# 🔍 Para auditoría - Ver tareas "huérfanas"
tareas_huerfanas = Tarea.objects.filter(
    status='0',
    columna__status='1'  # Tareas en columnas eliminadas
)
```

---

## 📊 RESUMEN PARA QUERIES DE MÉTRICAS

### ✅ Query Python CORRECTO para Dashboard:

```python
from django.db.models import Count, Q

# Tareas pendientes (en columnas normales)
tareas_pendientes = Tarea.objects.filter(
    status='0',
    columna__status='0',
    columna__status_fijas__isnull=True
).count()

# Tareas en progreso
tareas_en_progreso = Tarea.objects.filter(
    status='0',
    columna__status='0',
    columna__status_fijas='1'
).count()

# Tareas completadas
tareas_completadas = Tarea.objects.filter(
    status='0',
    columna__status='0',
    columna__status_fijas='2'
).count()

# Total de tareas activas
total_tareas = Tarea.objects.filter(
    status='0',
    columna__status='0'
).count()
```

### ❌ Errores Comunes a EVITAR:

```python
# ❌ ERROR 1: Usar completed_at para contar tareas completadas
tareas_completadas = Tarea.objects.filter(completed_at__isnull=False)
# Problema: Omite tareas en "Finalizado" sin completed_at

# ❌ ERROR 2: No filtrar columnas eliminadas
tareas = Tarea.objects.filter(status='0')
# Problema: Incluye tareas en columnas con status='1'

# ❌ ERROR 3: Comparar status_fijas con número
tareas = Tarea.objects.filter(columna__status_fijas=1)
# Problema: status_fijas es STRING '1', no INT 1

# ❌ ERROR 4: Asumir que NULL = '0'
tareas = Tarea.objects.filter(columna__status_fijas='0')
# Problema: '0' NO existe, debe usar __isnull=True
```

---

## 🚨 INCONSISTENCIAS DETECTADAS

### 1. Tareas en "Finalizado" sin `completed_at`
**Encontrado:** 1 tarea (id_tarea=8)  
**Impacto:** Métricas de tiempo de completado inconsistentes  
**Solución:** Usar `columna.status_fijas='2'` como fuente de verdad

### 2. Tareas en "En Progreso" sin `started_at`
**Encontrado:** 1 tarea (id_tarea=10)  
**Impacto:** Métricas de tiempo en progreso inconsistentes  
**Solución:** Migración para llenar `started_at` en tareas existentes

### 3. Tarea en "Finalizado" con `completed_at` pero sin `started_at`
**Encontrado:** 1 tarea (id_tarea=9)  
**Impacto:** Tarea completada sin pasar por "En Progreso"  
**Solución:** Aceptar como válido (creación directa en Finalizado)

---

## 📋 CHECKLIST para Equipo de Métricas

- ✅ **status_fijas** puede ser `'1'`, `'2'` o `NULL` (NO existe '0')
- ✅ **status_fijas** es STRING, NO número
- ✅ **id_columna** NUNCA es NULL
- ✅ **status** en tareas y columnas es ENUM('0','1')
- ✅ Siempre filtrar `status='0'` en tareas
- ✅ Siempre filtrar `status='0'` en columnas
- ✅ Usar `columna.status_fijas` como fuente de verdad (NO `completed_at`)
- ✅ `completed_at` puede ser NULL incluso en columnas finalizadas
- ✅ `started_at` puede ser NULL incluso en columnas en progreso
- ⚠️ Hay inconsistencias en datos históricos (migraciones)

---

## 🔗 Archivos Relevantes del Backend PHP

```
src/Conduit/Controllers/Tarea/TareaController.php
  - Método move() línea 256: Actualiza started_at y completed_at
  - Método destroy() línea 247: Eliminación lógica

src/Conduit/Controllers/Columna/ColumnaController.php
  - Método destroy() línea 306: Previene eliminación con tareas
  - Método gestionarTipos() línea 342: Gestión de tipos de columnas

src/Conduit/Models/Columna.php
  - Constantes STATUS_FIJA_PROGRESO = '1'
  - Constantes STATUS_FIJA_FINALIZADO = '2'

src/Conduit/Models/Tarea.php
  - Relación belongsTo('columna')
```

---

**Documento generado el 5 de noviembre de 2025**  
**Para consultas técnicas:** Backend PHP Team  
**Para solicitudes de nuevas métricas:** Actualizar endpoint `/proyectos/{id}/metricas`
