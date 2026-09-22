# 📋 Parlay Tracker — Documentación Técnica Completa

> Generado el 22 Sept 2026 | Backend Laravel 13 + PHP 8.4 + MySQL + Reverb (WebSockets)

---

## 1. ¿Qué es este proyecto?

**Parlay Tracker** es una aplicación de seguimiento en tiempo real de parlays (apuestas deportivas múltiples).  
A diferencia de las casas de apuestas, esta app **no gestiona dinero ni apuestas reales** — es exclusivamente una herramienta de visualización y tracking personal de picks con:

- **Barras de progreso en vivo** (`current_progress / target_value`)
- **Actualizaciones automáticas** via WebSocket cada vez que cambia el marcador
- **Historial de parlays** ganados/perdidos/pendientes
- **API REST** para consumirse desde Angular (u otro frontend)

---

## 2. Stack Tecnológico Implementado

| Capa | Tecnología | Versión | Rol |
|------|-----------|---------|-----|
| Backend | **Laravel** | 13 | Framework principal |
| Lenguaje | **PHP** | 8.4 | Runtime del servidor |
| BD | **MySQL** | local | Persistencia de datos |
| Auth API | **Laravel Sanctum** | 4.3 | Token Bearer para Angular |
| WebSockets | **Laravel Reverb** | 1.11 | Servidor WS nativo (sin Pusher externo) |
| Cola/Jobs | **Database Queue** | — | Sin Redis, usa MySQL para jobs |
| Caché | **Database Cache** | — | Sin Redis, usa MySQL |
| API deportiva | **SportsData.io** | v4 | Marcadores en vivo (UCL activo) |
| Frontend | **Angular** *(pendiente)* | — | SPA separada, consume la API REST |

---

## 3. Estructura de Archivos — Explicación por Carpeta

### `app/Models/`
Representan las tablas de la BD. Son el corazón del sistema.

| Archivo | Función | ¿Qué pasa si lo elimino? | ¿Es escalable? |
|---------|---------|--------------------------|----------------|
| `User.php` | Usuario dueño de los parlays. Tiene `HasApiTokens` (Sanctum) y la relación `parlays()` | ❌ **Crítico** — todo el sistema de auth cae | Sí: se le pueden añadir roles (admin/user), suscripciones, etc. |
| `Event.php` | Partido deportivo. Guarda `home_score`, `away_score`, `status`, `elapsed_minutes`. Métodos: `totalGoals()`, `currentResult()`, `isLive()` | ❌ **Crítico** — sin eventos no hay picks | Sí: añadir más deportes (`sport`), más ligas, estadísticas avanzadas |
| `Parlay.php` | Agrupador de picks por usuario. Tiene `recalculateStatus()` que evalúa si el parlay ganó/perdió según sus picks | ❌ **Crítico** — sin parlays no hay estructura de apuesta | Sí: añadir cuotas combinadas, multi-leg, etc. |
| `Pick.php` | La selección individual dentro de un parlay. **Contiene toda la lógica matemática**: `calculateCurrentProgress()`, `calculateProgressPercentage()`, `evaluateStatus()` | ❌ **Crítico** — es el centro del tiempo real | Sí: añadir más `pick_type` (jugador, tarjetas, corners, etc.) |

---

### `app/Events/`

| Archivo | Función | ¿Qué pasa si lo elimino? | ¿Es escalable? |
|---------|---------|--------------------------|----------------|
| `PickProgressUpdated.php` | Evento que se **emite vía WebSocket** (Reverb) al canal privado del usuario cuando un pick cambia. Implementa `ShouldBroadcast`. El payload JSON incluye: pick, parlay y event. | ⚠️ La API sigue funcionando pero Angular **no recibe actualizaciones en tiempo real** | Sí: crear más eventos como `ParlaySettled`, `NewEventAvailable`, `PickWon`, etc. |

---

### `app/Jobs/`
Jobs que se procesan en la cola (`database` driver → tabla `jobs` en MySQL).

| Archivo | Función | ¿Qué pasa si lo elimino? | ¿Es escalable? |
|---------|---------|--------------------------|----------------|
| `FetchSportsDataJob.php` | Se ejecuta **cada 60 segundos** (scheduler). Consulta `GET /GamesByDate/UCL/{fecha}` en SportsData.io. Si el marcador cambió vs lo que hay en BD, despacha `EvaluatePicksJob`. Cola: `sports-data` | ⚠️ Los eventos dejan de actualizarse. El sistema queda estático | Sí: añadir múltiples competiciones, múltiples fechas, deportes distintos |
| `EvaluatePicksJob.php` | Recibe un `$eventId`. Carga todos los picks de ese evento, recalcula `current_progress` y `progress_percentage`. Si cambió algo, guarda en BD y hace `broadcast(new PickProgressUpdated($pick))`. Cola: `pick-evaluation` | ⚠️ Los picks no se actualizan. La barra de progreso queda fija | Sí: añadir lógica de notificaciones push, alertas por email, etc. |

---

### `app/Services/`

| Archivo | Función | ¿Qué pasa si lo elimino? | ¿Es escalable? |
|---------|---------|--------------------------|----------------|
| `SportsApiService.php` | Wrapper de la API de SportsData.io. Métodos: `getFixturesByDate()`, `getLiveFixtures()`, `getFixtureById()`, `mapFixtureToEvent()`. Auth: header `Ocp-Apim-Subscription-Key`. | ❌ `FetchSportsDataJob` no puede consumir la API. El sistema no puede ingestar datos deportivos | Sí: el método `mapFixtureToEvent()` puede adaptarse a cualquier otra API (ESPN, Sofascore, etc.) cambiando solo este archivo |

---

### `app/Http/Controllers/Api/`
Controladores de la API REST que consume Angular.

| Archivo | Función | Endpoints | ¿Si lo elimino? |
|---------|---------|-----------|-----------------|
| `AuthController.php` | Registro, login, logout, obtener usuario (`/me`). Crea tokens Sanctum | `POST /api/auth/register` `POST /api/auth/login` `POST /api/auth/logout` `GET /api/auth/me` | ❌ Angular no puede autenticarse |
| `ParlayController.php` | CRUD completo de parlays del usuario autenticado. Devuelve picks+eventos anidados. | `GET/POST /api/parlays` `GET/PUT/DELETE /api/parlays/{id}` | ⚠️ Angular no puede crear ni ver parlays |
| `PickController.php` | CRUD de picks anidados bajo un parlay. Valida que `event_id`, `pick_type`, `condition` y `target_value` sean correctos. | `GET/POST /api/parlays/{id}/picks` `GET/DELETE /api/picks/{id}` | ⚠️ No se pueden añadir picks a parlays |
| `EventController.php` | Lista eventos con filtros por `?status=live` y `?date=`. Para que Angular muestre al usuario los partidos disponibles donde añadir picks | `GET /api/events` `GET /api/events/{id}` | ⚠️ Angular no puede ver partidos disponibles |

---

### `database/migrations/`

| Archivo | Tabla que crea | Columnas clave |
|---------|---------------|----------------|
| `...create_events_table.php` | `events` | `external_id` (ID de SportsData.io), `home_score`, `away_score`, `status` (`scheduled/live/finished/postponed`), `elapsed_minutes`, `starts_at`, `raw_data` (JSON completo de la API) |
| `...create_parlays_table.php` | `parlays` | `user_id`, `name`, `stake`, `potential_payout`, `status` (`pending/active/won/lost`) |
| `...create_picks_table.php` | `picks` | `parlay_id`, `event_id`, `pick_type`, `condition`, `target_value`, `current_progress`, `progress_percentage`, `status` (`pending/won/lost/void`) |

---

### `database/factories/`

| Archivo | Uso | Estados disponibles |
|---------|-----|---------------------|
| `EventFactory.php` | Genera partidos falsos para tests y seeders | `.live()` (marcador random, en vivo) `.finished()` (partido terminado) |
| `ParlayFactory.php` | Genera parlays falsos | `.active()` `.won()` `.lost()` |
| `PickFactory.php` | Genera picks con tipos y condiciones aleatorias pero válidas | default (pending) |

---

### `database/seeders/`

| Archivo | Qué genera |
|---------|-----------|
| `DatabaseSeeder.php` | 3 usuarios · 7 eventos (3 live, 2 scheduled, 2 finished) · 1 parlay activo con picks en partidos en vivo · 1 parlay pendiente · 2 usuarios extra con sus parlays |

> **Usuario de prueba:** `test@example.com` / `password`

---

### `routes/`

| Archivo | Función |
|---------|---------|
| `api.php` | 15 endpoints REST protegidos con Sanctum. Rutas públicas: register/login. Todo lo demás requiere `Authorization: Bearer {token}` |
| `channels.php` | Define el canal WebSocket privado `user.{userId}`. Verifica que solo el propio usuario pueda suscribirse. Angular lo usa como `Echo.private('user.1')` |
| `console.php` | Registra el scheduler: `FetchSportsDataJob` cada minuto, con `withoutOverlapping(5)` para evitar solapamiento si la API tarda |

---

### `config/`

| Archivo | Qué configuré |
|---------|--------------|
| `.env` | `BROADCAST_CONNECTION=reverb`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, credenciales MySQL, Reverb (app_id, key, secret, host:8080), SportsData.io key + competition=UCL |
| `config/services.php` | Bloque `sports_api` con key, base_url y competition leídos desde `.env` |
| `config/sanctum.php` | Publicado por Sanctum (configuración de expiración de tokens, dominios permitidos) |

---

## 4. Cómo Funciona el Backend Ahora

```
┌─────────────────────────────────────────────────────────────────────┐
│                     CADA 60 SEGUNDOS (scheduler)                    │
│                                                                     │
│  FetchSportsDataJob                                                 │
│       │                                                             │
│       ▼                                                             │
│  SportsApiService.getFixturesByDate(hoy)                           │
│       │  GET /GamesByDate/UCL/2026-09-22                           │
│       │  Header: Ocp-Apim-Subscription-Key                         │
│       ▼                                                             │
│  [Para cada partido en la respuesta]                                │
│       │                                                             │
│       ├─ ¿Cambió el marcador/status vs BD?  ─── NO → Ignorar      │
│       │                                                             │
│       └─ SÍ → events.home_score = 2, away_score = 1, etc.         │
│               → dispatch(EvaluatePicksJob($eventId))               │
│                                                                     │
│  EvaluatePicksJob($eventId)                                         │
│       │                                                             │
│       ▼                                                             │
│  [Para cada Pick del evento]                                        │
│       │                                                             │
│       ├─ pick.calculateCurrentProgress()   → ej: 3 goles           │
│       ├─ pick.calculateProgressPercentage() → ej: 120% → cappado a 100% │
│       ├─ pick.evaluateStatus()             → "won" si terminó      │
│       │                                                             │
│       ├─ ¿Cambió algo?  ─── NO → No broadcast (eficiencia)         │
│       │                                                             │
│       └─ SÍ → picks.current_progress = 3                           │
│               → broadcast(PickProgressUpdated($pick))              │
│                                                                     │
│  Laravel Reverb (WebSocket server en puerto 8080)                   │
│       │                                                             │
│       ▼                                                             │
│  Canal privado: user.{userId}                                       │
│       │                                                             │
│       ▼                                                             │
│  Angular (Laravel Echo)  ←─────────────────────────────────────────┘
│  .listen('.pick.progress.updated', (data) => {
│      // Actualiza la barra de progreso en la UI
│  })
```

---

## 5. Qué Puede Hacer el Backend Ahora Mismo

| Funcionalidad | Estado |
|---------------|--------|
| ✅ Registro y login de usuarios vía API (token Bearer) | **Funcionando** |
| ✅ CRUD de parlays por usuario | **Funcionando** |
| ✅ CRUD de picks dentro de parlays | **Funcionando** |
| ✅ Listar eventos deportivos (UCL, con filtros) | **Funcionando** |
| ✅ Ingesta automática de marcadores (SportsData.io UCL) cada 60s | **Listo** (requiere `schedule:work` corriendo) |
| ✅ Evaluación automática de picks y recálculo de progreso | **Listo** (requiere `queue:work` corriendo) |
| ✅ Broadcast WebSocket via Reverb al canal del usuario | **Listo** (requiere `reverb:start` corriendo) |
| ✅ Datos de prueba sembrados en BD | **Hecho** |
| ⚙️ Frontend Angular que consume todo esto | **Pendiente** |

---

## 6. Qué Falta — Lo que viene a continuación

### 6.1 Backend — Pendientes

| Tarea | Prioridad | Descripción |
|-------|-----------|-------------|
| **CORS configurado para Angular** | 🔴 Alta | Añadir `http://localhost:4200` a `config/cors.php` para que Angular pueda hacer peticiones |
| **Múltiples competiciones** | 🟡 Media | Actualmente solo UCL. Expandir si se compra acceso a La Liga, EPL, etc. en SportsData.io |
| **Notificaciones push** | 🟡 Media | Avisar al usuario cuando un pick gana/pierde (Firebase FCM o WebPush) |
| **Tests automatizados** | 🟡 Media | Feature tests para los controllers y unit tests para la lógica de `Pick.php` |
| **Rate limiting** | 🟡 Media | Proteger la API de abuso (Laravel tiene `throttle` middleware) |
| **Logging estructurado** | 🟢 Baja | Centralizar logs para producción (Sentry, Papertrail, etc.) |
| **Endpoint de estadísticas** | 🟢 Baja | Historial de win rate del usuario, racha, etc. |

### 6.2 Frontend Angular — Todo por hacer

| Módulo | Descripción |
|--------|-------------|
| **Auth module** | Login/Register, guardar token en `localStorage`, interceptor HTTP |
| **Parlay list** | Vista principal: lista de parlays activos con su progreso general |
| **Parlay detail** | Tarjetas por pick con barra de progreso animada |
| **Create parlay** | Formulario para crear parlay y añadir picks seleccionando evento + tipo + condición |
| **Event browser** | Listado de partidos disponibles hoy (UCL) para añadir como picks |
| **WebSocket service** | Integrar `laravel-echo` + `pusher-js` apuntando a Reverb (puerto 8080) |
| **Real-time updates** | Suscripción al canal `user.{id}` y actualización reactiva de barras de progreso |

### 6.3 Infraestructura / Deployment — Para producción (Hetzner)

| Tarea | Herramienta | Descripción |
|-------|-------------|-------------|
| **Servidor Linux** | Ubuntu 24.04 en Hetzner | VPS mínimo recomendado: CAX11 (2 vCPU ARM, 4GB RAM) |
| **Nginx** | Reverse proxy | Servir Laravel en puerto 80/443, proxy Reverb en 8080 |
| **Supervisor** | Process manager | Mantener `queue:work` y `reverb:start` corriendo en background |
| **Crontab** | System cron | `* * * * * php artisan schedule:run` para el scheduler |
| **SSL** | Let's Encrypt (Certbot) | HTTPS obligatorio para WebSockets seguros (`wss://`) |
| **Redis** | Opcional (mejora de perf.) | Si el tráfico crece, reemplazar database queue/cache por Redis |
| **Docker** | Opcional | Para estandarizar el entorno (ya previsto en el contexto) |

---

## 7. Lógica de Tipos de Pick — Cómo se calcula el progreso

Esta es la lógica central del producto. Vive en `Pick.php`:

| `pick_type` | `condition` | `target_value` | ¿Cómo se calcula `current_progress`? | Ejemplo |
|-------------|-------------|----------------|---------------------------------------|---------|
| `total_goals` | `over` | 2.5 | `home_score + away_score` | 1+1=2 → 80% |
| `total_goals` | `under` | 2.5 | `home_score + away_score` | 1+0=1 → Ganando (falta que llegue a 2.5) |
| `home_score` | `over` | 1.5 | `home_score` | 2 goles → ✅ Won |
| `away_score` | `under` | 0.5 | `away_score` | 0 goles → Ganando |
| `result` | `home` | 1.0 | `currentResult() === 'home'` ? 1.0 : 0.0 | Ganando local → 100% |
| `result` | `draw` | 1.0 | `currentResult() === 'draw'` ? 1.0 : 0.0 | Empate → 100% |

> **Regla de oro:** Los picks solo se resuelven como `won` o `lost` cuando el evento tiene `status = 'finished'`. Durante el partido solo se actualiza la barra visual.

---

## 8. API REST — Referencia completa para Angular

### Autenticación
```
POST   /api/auth/register    → { name, email, password, password_confirmation }
POST   /api/auth/login       → { email, password }   ← devuelve { user, token }
POST   /api/auth/logout      → Header: Authorization: Bearer {token}
GET    /api/auth/me          → Devuelve el usuario autenticado
```

### Eventos deportivos
```
GET    /api/events                 → Lista eventos de hoy (live + scheduled por defecto)
GET    /api/events?status=live     → Solo partidos en vivo
GET    /api/events?date=2026-09-22 → Partidos de una fecha específica
GET    /api/events/{id}            → Detalle de un partido con sus picks
```

### Parlays
```
GET    /api/parlays          → Lista parlays del usuario (paginado, con picks+eventos)
POST   /api/parlays          → Crear parlay { name, stake?, potential_payout? }
GET    /api/parlays/{id}     → Detalle con picks y eventos
PUT    /api/parlays/{id}     → Actualizar nombre/stake
DELETE /api/parlays/{id}     → Eliminar parlay y sus picks
```

### Picks
```
GET    /api/parlays/{id}/picks    → Lista picks de un parlay
POST   /api/parlays/{id}/picks    → Añadir pick { event_id, pick_type, condition, target_value, odd? }
GET    /api/picks/{id}            → Detalle de un pick
DELETE /api/picks/{id}            → Eliminar un pick
```

### WebSocket — Angular Echo
```javascript
// Instalar en Angular:
// npm install laravel-echo pusher-js

// Configuración:
const echo = new Echo({
  broadcaster: 'reverb',
  key: 'parlay-tracker-key',  // REVERB_APP_KEY del .env
  wsHost: 'localhost',
  wsPort: 8080,
  forceTLS: false,
  enabledTransports: ['ws'],
});

// Suscripción al canal del usuario autenticado:
echo.private(`user.${userId}`)
  .listen('.pick.progress.updated', (data) => {
    // data.pick.progress_percentage → número 0-100 para la barra
    // data.event.home_score, away_score → marcador actual
    // data.parlay.status → estado del parlay
  });
```

---

## 9. Preguntas Abiertas — Necesito tu respuesta para continuar

> Estas decisiones afectan la arquitectura del frontend y funcionalidades futuras.

### 9.1 Alcance de la aplicación
- [ ] ¿La app es **solo para tu uso personal** o la van a usar **múltiples usuarios** (pública)?
- [ ] ¿Los usuarios podrán **registrarse solos** o tú los agregas manualmente?
- [ ] ¿Necesitas un **panel de administración** (ver todos los usuarios, gestionar eventos)?

### 9.2 Funcionalidades
- [ ] ¿Los picks incluirán **jugadores individuales** (ej: "Mbappé anota")? → Requiere otro tipo de API
- [ ] ¿Habrá **notificaciones** cuando un pick gane/pierda? (push móvil, email, SMS)
- [ ] ¿El parlay mostrará las **cuotas combinadas** y la **ganancia potencial calculada** automáticamente?
- [ ] ¿Habrá **historial** y **estadísticas** del usuario (win rate, rachas, etc.)?
- [ ] ¿El usuario puede **editar un pick** después de crearlo (cambiar target/odd)?

### 9.3 Deportes y Competiciones
- [ ] ¿Solo **fútbol** o también basketball, NFL, tenis, béisbol?
- [ ] ¿Qué ligas necesitas además de UCL? (La Liga, Premier League, Champions, Bundesliga, etc.)
- [ ] ¿Necesitas ligas de **LATAM** (Liga MX, Liga Profesional Argentina, etc.)?

> ⚠️ Cada nueva competición requiere verificar acceso en tu cuenta de SportsData.io

### 9.4 Frontend y Plataformas
- [ ] ¿La app debe ser **solo web** (Angular) o también **móvil nativa** (iOS/Android)?
- [ ] ¿Consideras usar **Angular + Capacitor/Ionic** para tener web + app móvil con un solo código?
- [ ] ¿La app web debe ser **responsiva** (adaptarse a móvil) o tienes diseño dedicado?
- [ ] ¿Tienes un **diseño/mockup** en Figma u otro que ya tengas claro?

### 9.5 Autenticación y Seguridad
- [ ] ¿Login con **Google/Social** además de email/password?
- [ ] ¿Los parlays son **privados** por usuario o puede haber parlays **compartidos/públicos**?

---

## 10. Comandos de Desarrollo — Referencia rápida

```bash
# Levantar el backend completo (4 terminales)
php artisan serve             # API REST en http://localhost:8000
php artisan reverb:start      # WebSocket server en ws://localhost:8080
php artisan queue:work --queue=sports-data,pick-evaluation  # Procesa los Jobs
php artisan schedule:work     # Ejecuta el scheduler (polling API cada 60s)

# Base de datos
php artisan migrate           # Crear/actualizar tablas
php artisan db:seed           # Sembrar datos de prueba
php artisan migrate:fresh --seed  # Reiniciar BD completa con datos frescos

# Depuración
php artisan queue:failed      # Ver jobs que fallaron
php artisan queue:retry all   # Reintentar todos los jobs fallidos
php artisan route:list        # Ver todas las rutas API registradas

# Limpiar caché
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear    # Limpiar todo de una vez
```

---

## 11. Diagrama de Relaciones de BD

```
users
  ├── id (PK)
  ├── name
  ├── email
  └── password

        │ HasMany
        ▼

parlays
  ├── id (PK)
  ├── user_id (FK → users)
  ├── name
  ├── stake
  ├── potential_payout
  └── status: pending | active | won | lost

        │ HasMany
        ▼

picks
  ├── id (PK)
  ├── parlay_id (FK → parlays)
  ├── event_id  (FK → events)  ──────────┐
  ├── pick_type                           │
  ├── condition                           │
  ├── target_value                        │ BelongsTo
  ├── current_progress   ← SE ACTUALIZA  │
  ├── progress_percentage ← SE ACTUALIZA │
  └── status: pending | won | lost       │

events ◄─────────────────────────────────┘
  ├── id (PK)
  ├── external_id (ID de SportsData.io)
  ├── home_team / away_team
  ├── home_score / away_score  ← SE ACTUALIZA
  ├── status: scheduled | live | finished
  ├── elapsed_minutes          ← SE ACTUALIZA
  └── raw_data (JSON completo de la API)
```

---

*Documento generado automáticamente — Parlay Tracker v0.1 Backend*
