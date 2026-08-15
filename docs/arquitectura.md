# Arquitectura de RIENDA

Estado: `Fases 1–3 y 5 implementadas` (4 Retos y 6 RIENDA TV pendientes)
Stack: Laravel 13 · Inertia 3 · Vue 3 · TypeScript · Tailwind 4 · SQLite · Laravel Reverb

---

## 1. La idea

RIENDA está organizada por **dominios**, no por tipo de archivo. El esqueleto
de Laravel se queda en `app/`; todo lo que es RIENDA vive en `app/Modules/`,
un directorio por módulo de negocio.

```text
app/
├── Http/, Models/, Providers/     ← esqueleto de Laravel y del starter kit
└── Modules/                       ← el dominio de RIENDA
    ├── ModulesServiceProvider.php ← único punto de registro
    ├── Shared/                    ← kernel compartido
    ├── Participants/
    ├── Horses/
    ├── Tournaments/
    ├── Registrations/
    ├── Competition/
    ├── Leaderboards/
    └── Audit/
```

---

## 2. Anatomía de un módulo

Cada módulo repite las cuatro capas del documento de requerimientos:

```text
app/Modules/{Modulo}/
├── Domain/                        ← el negocio, sin Laravel encima
│   ├── Models/                       entidades Eloquent
│   ├── Enums/                        estados y sus reglas
│   ├── Events/                       hechos del dominio
│   └── Exceptions/
├── Application/                   ← casos de uso
│   ├── Services/                     una clase = una operación
│   ├── Data/                         DTOs de entrada
│   └── Listeners/
├── Infrastructure/                ← lo que toca el mundo exterior
│   ├── Database/Migrations/
│   ├── Database/Factories/
│   └── Providers/{Modulo}ServiceProvider.php
└── Presentation/                  ← HTTP
    ├── Http/Controllers/
    ├── Http/Requests/
    ├── Http/Resources/
    └── Routes/admin.php
```

`ModuleServiceProvider` (en `Shared`) descubre por convención las migraciones,
las rutas y las factories de cada módulo. **Agregar un módulo nuevo solo exige
crear su carpeta y añadir una línea en `ModulesServiceProvider`.**

Las rutas de `Presentation/Routes/admin.php` se montan automáticamente bajo el
prefijo `/admin`, con nombre `admin.` y middleware `auth` + `verified`.

---

## 3. Los módulos

| Módulo | De qué responde |
| --- | --- |
| **Shared** | `Milliseconds`, `HasPublicId`, `DomainException`, providers base, dashboard |
| **Participants** | El jinete permanente. `RND-000184` |
| **Horses** | El caballo permanente. `H-000421` |
| **Tournaments** | Torneo (`T-2026-001`), categorías y su estructura días × rondas |
| **Registrations** | La participación de un jinete en una categoría, con su caballo |
| **Competition** | Días, rondas y **resultados de ronda** — el dato original |
| **Leaderboards** | Resultado diario, acumulado y clasificación. Todo derivado |
| **Audit** | Trazabilidad de cada escritura sobre resultados oficiales |
| **Public** | La cara pública de solo lectura: landing, clasificaciones y perfiles |

---

## 4. Cómo se comunican los módulos

Los módulos **no se llaman entre sí para reaccionar**: anuncian hechos y otro
módulo decide qué hacer con ellos.

```text
Tournaments                 Competition                 Leaderboards / Audit
    │                            │                              │
CategoryStructureDefined ──────► BuildCategorySchedule          │
                                 (crea días y rondas)           │
                                      │                         │
                            RoundResultRecorded ───────────────►│ recalcula
                            RoundResultUpdated  ───────────────►│ y audita
```

Esto es lo que permite que el recálculo en cascada del documento —
`RoundResult → día → acumulado → clasificación` — viva en un solo listener
(`RecalculateAfterResultChange`) sin que `Competition` sepa que existe una
clasificación.

Los mismos hechos son la fuente del **realtime** (Fase 5): cada evento de
`Competition` implementa `ShouldBroadcast` y, al emitirse, difunde por Reverb
el estado para que las pantallas abiertas se actualicen solas.

```text
Competition                     Reverb (colas)
    │                                 │
    └── RoundResultRecorded ─────────►│  private-category.{id}         (consola admin)
        RoundResultUpdated ──────────►│  private-tournament.{id}       (consola del torneo)
        RoundStarted / Completed ────►│  public-category.{torneo}.{cat} (landing)
        CompetitionDayStarted/… ─────►│
```

---

## 5. Las tres invariantes que sostienen todo

### 5.1 El tiempo es un entero en milisegundos

Nunca un `FLOAT`. La única traducción entre `"8.342"` y `8342` ocurre en el
value object `Milliseconds`, y la conversión se hace **sobre la cadena**, no
con aritmética de punto flotante:

```php
Milliseconds::fromSeconds('8.342')->value;  // 8342
Milliseconds::fromInt(8342)->format();      // "8.342"
```

Hay un test que recorre los 999 milisegundos de un segundo comprobando que
ninguno se desvía.

### 5.2 Una ronda fallida no acumula tiempo

La invariante está en el constructor del DTO, no en el controlador: cualquier
camino que intente guardar un resultado fallido con tiempo lo pierde.

```php
successful = false  ⟹  time_ms = 0
```

### 5.3 Lo calculado nunca es la fuente de verdad

`daily_results` y `category_standings` son **caché**. Se reconstruyen enteras
desde `round_results`. Borrarlas y recalcular debe dar exactamente lo mismo.

---

## 6. La clasificación

```sql
ORDER BY total_successful_rounds DESC,
         total_time_ms ASC
```

Las rondas acertadas mandan sobre el tiempo. Un jinete con 9 aciertos y mejor
tiempo queda **por debajo** de uno con 10 aciertos — el caso Pedro/Carlos del
documento, cubierto por un test.

Los empates comparten posición (`1, 2, 2, 4`) y se marcan con `tied = true`.
**No se inventa un tercer criterio de desempate** hasta que las reglas oficiales
del deporte lo definan.

---

## 7. Reglas de integridad implementadas

| Regla | Dónde vive |
| --- | --- |
| Participante duplicado | índice único en `document` + validación |
| Inscripción duplicada en una categoría | `UNIQUE(tournament_category_id, participant_id)` + `RegisterParticipantInCategory` |
| Resultado duplicado en una ronda | `UNIQUE(round_id, registration_id)` + `RegisterRoundResult` |
| Ronda de otro día / día de otra categoría | `UNIQUE` compuestos + `scopeBindings()` en las rutas anidadas |
| Participante inactivo inscrito | `RegisterParticipantInCategory` |
| Resultado de una inscripción de otra categoría | `RegisterRoundResult::guardRegistrationBelongsToRound` |
| Transición de estado inválida en un torneo | `TournamentStatus::allowedTransitions()` |
| Reconfigurar una categoría ya iniciada | `SaveTournamentCategory` + `BuildCategorySchedule` |
| Grupo de salida duplicado en un torneo | `UNIQUE(tournament_id, name)` + `SaveCompetitionGroup` |
| Grupo de otro torneo en una ruta anidada | `scopeBindings()` + alias `groups()` |
| Resultado desde la consola del torneo fuera de su día/inscripción | `TournamentCompetitionConsoleController::guard*` |
| Inscripción con resultado en la ronda activa reencolada | `BuildTournamentRunOrder::nextRoundFor` |

---

## 8. Identificadores públicos

El `id` interno nunca sale del backend. Toda referencia pública usa `public_id`,
generado por el trait `HasPublicId`:

```text
Participante  RND-000184
Caballo       H-000421
Torneo        T-2026-001    (lleva el año de la competencia)
```

Los torneos y las categorías se resuelven en la URL por `slug`; la categoría va
anidada bajo su torneo con `scopeBindings()`, de modo que la propia resolución
de la ruta garantiza la pertenencia.

---

## 9. El frontend

Mismo criterio que el backend: el esqueleto del starter kit se queda donde
está, y el dominio de RIENDA vive en `resources/js/modules/`.

```text
resources/js/
├── components/        ← starter kit (ui/, layout, navegación)
├── layouts/           ← starter kit
├── modules/           ← el dominio de RIENDA
│   ├── shared/
│   │   ├── components/    PageHeader, StatusBadge, EmptyState, Pagination
│   │   ├── format.ts      formatTime, formatDate…
│   │   └── types.ts       Paginated<T>, Option
│   ├── participants/types.ts
│   ├── horses/types.ts
│   ├── tournaments/types.ts
│   ├── registrations/types.ts
│   └── competition/
│       ├── types.ts
│       └── components/    TimeEntryRow, LeaderboardTable
└── pages/             ← páginas Inertia, una carpeta por módulo
    ├── participants/  Index · Form · Show
    ├── horses/        Index · Form
    ├── tournaments/   Index · Form · Show
    ├── registrations/ Index
    └── competition/   Console · TournamentConsole
```

Las páginas viven bajo `pages/` porque es la raíz que resuelve el plugin de
Inertia; la organización por módulo se conserva en la subcarpeta. Todo lo demás
que pertenece a un dominio — tipos, componentes, helpers — vive en su módulo.

---

## 10. La consola de competencia

`/admin/tournaments/{torneo}/categorias/{categoria}/competition` es la pantalla
que el administrador tiene abierta mientras se corre la categoría:

```text
┌─────────────┬──────────────────────────┬─────────────────┐
│ Días        │  Ronda activa            │ Clasificación   │
│  y rondas   │  (captura de tiempos)    │  (en vivo)      │
│             │                          │                 │
│ Día 1  ●    │  # Jinete    Tiempo  ✓✗  │ 1  Carlos  10   │
│  R1 R2 R3 R4│  1 Carlos    8.342   ✓   │ 2  Juan     9   │
│ Día 2       │  2 Juan      7.521   ✓   │ 3  Pedro    9   │
│  R1 R2 R3 R4│  3 Pedro     ——      ✗   │                 │
│       F     │                          │                 │
└─────────────┴──────────────────────────┴─────────────────┘
```

La `F` del último día es el **lazo final**: una ronda extra (`round_number =
rounds_per_day + 1`, `is_final`) que solo corre el top 10 de la clasificación y
cuyo resultado sí cuenta para el acumulado.

Registrar un tiempo dispara el recálculo completo y la tabla de la derecha se
actualiza en la misma respuesta de Inertia. Además, el evento `RoundResultRecorded`
se difunde por Reverb: la consola (`echo.private('category.{id}')`) y la landing
( canal público por slugs) hacen `router.reload` de solo los props afectados,
así que las pantallas abiertas se mantienen en vivo sin tocar nada.

### 10.1 La consola del torneo y la cola de salida

`/admin/tournaments/{torneo}/competition` es la consola de **todo el evento**:
una sola pantalla con la cola de salida, la captura de tiempos y la
clasificación de cada categoría.

Los **grupos de salida** son una tabla a nivel de torneo (`competition_groups`,
`UNIQUE(tournament_id, name)`) que ordenan la operación de pista. Cada
inscripción puede asignarse a un grupo (`competition_group_id` nullable con
`nullOnDelete`); el orden dentro del grupo es el dorsal. Quien no tiene grupo
sale al final de la cola.

La cola **no se persiste**: se calcula bajo demanda con el servicio
`BuildTournamentRunOrder`, que agrupa los días por `day_number` a través de
todas las categorías y decide el día de trabajo así: el pedido en la URL → el
primer día activo → el primer día pendiente → el primer día disponible.

Para cada inscripción, la ronda de trabajo es:

- Si el día tiene una **ronda activa**, entra a la cola solo quien aún no
  registró en ella; el que ya salió espera a que cierre el lazo.
- Sin ronda activa, la siguiente ronda pendiente sin resultado.

El lazo se cierra **solo**: cuando el último inscrito activo registra su
resultado, el listener `CompleteRoundWhenEveryoneRecorded` (escucha
`RoundResultRecorded`) finaliza la ronda y la siguiente pendiente pasa a ser la
activa, así la cola avanza sin intervención manual. Si alguien no se presenta,
el cierre queda para el cierre manual del día o de la ronda.

En el **lazo final** el conteo esperado no son los inscritos activos sino los
**finalistas**: `BuildCategorySchedule` crea en el último día de cada categoría
una ronda extra (`round_number = rounds_per_day + 1`, `is_final`) y solo entran
a la cola los 10 primeros de la clasificación (los `category_standings` con
posición dentro del top 10; con menos de 10 inscritos con resultados pasan
todos). Cuando la final se cierra, el día no deja pendientes: la cola queda
vacía.

El orden de la cola prioriza la ronda activa sobre el resto y, dentro del mismo
estado, grupo → dorsal → id:

```text
ronda activa primero → group.order → registration_number
```

**Desde el segundo día** el orden deja de ser por grupos y pasa a ser deportivo,
por categoría, según el rendimiento acumulado en `category_standings`: primero
quien menos lazos ha acertado (`total_successful_rounds` ASC) y, a la par,
quien más tiempo tiene registrado (`total_time_ms` DESC). La categoría mantiene
a sus vaqueros juntos en la cola:

```text
ronda activa primero → categoría → total_successful_rounds ASC → total_time_ms DESC → dorsal
```

La jornada completa se puede arrancar de una sola vez desde la consola con
`PUT tournaments/{torneo}/competition/start-all-days` (día `day`), que llama a
`ManageRoundLifecycle::startDayForAllCategories` e inicia el día en todas las
categorías con vaqueros inscritos.

El frontend (`TournamentConsole.vue`) escucha `echo.private('tournament.{id}')`
y refresca `['days','selectedDayNumber','queue','currentEntry','standings']`;
las correcciones y los cierres de ronda también llegan por este canal, de modo
que la consola del torneo se mantiene en vivo junto con la de cada categoría.

---

## 11. Puesta en marcha

```bash
composer install
npm install

php artisan migrate:fresh
php artisan db:seed --class=RiendaDemoSeeder   # datos de demostración

composer dev     # Laravel + Vite + cola + logs

php artisan reverb:start   # servidor WebSocket (terminal aparte)
```

El seeder crea un torneo con el día 1 corrido, seis jinetes con sus caballos y
el usuario `admin@rienda.test` / `password`.

La difusión en vivo requiere la cola (el evento se encola como `BroadcastEvent`
por Reverb). En desarrollo `composer dev` levanta el worker; en producción se
configura `BROADCAST_CONNECTION=reverb` y las variables `REVERB_*` del `.env`.

Verificación:

```bash
composer test         # Pint + PHPStan nivel 7 + PHPUnit
```

---

## 12. Lo que sigue

| Fase | Estado |
| --- | --- |
| 1 — Administración | ✅ |
| 2 — Competencia | ✅ |
| 3 — Público (landing, perfiles, resultados) | ✅ |
| 4 — Retos | pendiente |
| 5 — Realtime con Laravel Reverb | ✅ |
| 6 — RIENDA TV y overlay para OBS | parcial (clasificación) |

### Fase 5 — Realtime

Los seis eventos de dominio implementan `ShouldBroadcast` y se difunden por
categoría y por torneo:

```text
private-category.{id}                        ← consola del administrador
private-tournament.{id}                      ← consola del torneo
public-category.{torneo-slug}.{categoria-slug} ← landing / RIENDA TV
```

El trait `BroadcastsCategoryUpdates` centraliza el `broadcastOn()` y el contexto
de categoría; cada evento declara su `broadcastAs()` (`round-result.recorded`,
`round.started`, `competition-day.started`, …) y un `broadcastWith()` sanitizado
que nunca expone ids internos: solo `participant` (public_id), `time_ms`,
`successful`, `day_number` y `round_number`.

En el frontend, `resources/js/echo.ts` configura Laravel Echo contra Reverb y
las vistas se suscriben:

- `Console.vue` → `echo.private('category.{id}')` y refresca
  `['days','activeRound','entries','standings']` con debounce.
- `TournamentConsole.vue` → `echo.private('tournament.{id}')` y refresca
  `['days','selectedDayNumber','queue','currentEntry','standings']`.
- `Home.vue` (landing) → canal público y refresca `['active']`.
- `Overlay.vue` (RIENDA TV) → canal público y refresca `['standings']`.

La difusión es solo un aviso: los datos frescos siempre llegan por HTTP.

### Fase 6 — RIENDA TV

RIENDA TV es el **overlay para OBS Studio**: la tabla de clasificación de una
categoría como fuente de navegador, lista para superponer sobre la señal de
video. Dos URLs renderizan la misma página `public/tv/Overlay`:

```text
/tv                                      ← sigue la categoría activa del torneo en curso
/tv/{torneo-slug}/categorias/{cat-slug}  ← fija una categoría (repeticiones / selección manual)
```

La página es desnuda (sin layout, `app.ts` devuelve `null` para `public/tv/`):
fondo transparente con panel negro traslúcido (`rgba(0,0,0,0.72)`), sin barra
de progreso de Inertia (`#nprogress` oculta) y sin scrollbars. Columnas:
posición · jinete · rondas · tiempo, con el primer puesto en dorado. El panel
llena la fuente; si hay más jinetes de los que caben, la tabla hace scroll
interno invisible mostrando siempre los líderes.

**Conexión con OBS** (una vez):

1. En OBS, añadir una fuente **Navegador** (Browser).
2. Pegar la URL (`/tv` para el torneo en curso, o la URL fija de la categoría).
3. Ajustar el ancho y alto de la fuente al área del overlay (p. ej. `480 × 800`)
   y desmarcar "Mostrar barra de desplazamiento".
4. El fondo del navegador ya es transparente: no hace falta croma.

La actualización en vivo llega por el mismo canal público de Reverb de la Fase 5:
al registrarse un resultado, el overlay refresca solo la clasificación. El
backend (`BuildTvOverlay`, `TvController`) es solo lectura y no expone ids
internos; los torneos en borrador no tienen overlay (404).
