# RIENDA

**La plataforma oficial de los jinetes de lazo.**

Versión: `MVP 1.0`
Estado: `Definición inicial`
Fecha: `2026-08-13`

---

## 1. Descripción

RIENDA es una plataforma digital para administrar, registrar y publicar competencias de jinetes de lazo.

La plataforma permitirá:

* Registrar jinetes y caballos.
* Crear y administrar torneos.
* Crear categorías dentro de los torneos.
* Registrar participantes por categoría y caballo.
* Administrar días y rondas.
* Registrar resultados en tiempo real.
* Calcular automáticamente clasificaciones.
* Crear y administrar retos entre jinetes.
* Publicar resultados y rankings.
* Mostrar resultados en tiempo real mediante WebSockets.
* Proporcionar una interfaz especial para transmisiones en vivo.

El objetivo de RIENDA es construir progresivamente un **historial deportivo permanente de cada jinete**.

---

# 2. Principios del producto

## 2.1 Participantes permanentes

Un participante se registra una sola vez en RIENDA.

El participante no pertenece a un torneo.

Los torneos solamente registran la participación de un participante.

Ejemplo:

```text
Carlos Pérez
RIENDA ID: RND-000184

    ├── Torneo A
    ├── Torneo B
    ├── Torneo C
    └── Torneo D
```

Toda la información competitiva debe acumularse sobre el mismo participante.

---

## 2.2 Caballos permanentes

Los caballos también son entidades independientes.

Un jinete puede tener varios caballos y puede competir con diferentes caballos en diferentes torneos.

```text
Participant
    │
    ├── Horse A
    ├── Horse B
    └── Horse C
```

La inscripción a un torneo determina qué caballo utilizará el participante en esa competencia.

---

## 2.3 Participante ≠ Usuario

Un participante puede existir en RIENDA sin tener una cuenta de usuario.

Inicialmente los participantes serán registrados exclusivamente por administradores.

En una fase posterior un participante podrá reclamar su perfil.

```text
User
   │
   └── Participant
```

El usuario podrá administrar información de su perfil, pero no podrá modificar resultados oficiales.

---

# 3. Roles

## 3.1 Administrador

Puede:

* Crear participantes.
* Editar participantes.
* Crear caballos.
* Crear torneos.
* Crear categorías.
* Configurar días.
* Configurar rondas.
* Inscribir participantes.
* Registrar resultados.
* Corregir resultados.
* Crear retos.
* Registrar resultados de retos.
* Publicar resultados.
* Controlar el estado de una competencia.
* Administrar información mostrada en RIENDA TV.

---

## 3.2 Público

Puede:

* Ver torneos.
* Ver categorías.
* Ver participantes.
* Ver resultados.
* Ver rankings.
* Ver retos.
* Ver perfiles públicos.
* Ver RIENDA TV.

No puede modificar información.

---

# 4. Módulos MVP

```text
RIENDA
│
├── Participants
├── Horses
├── Tournaments
├── Categories
├── Registrations
├── Competition Days
├── Rounds
├── Results
├── Leaderboards
├── Challenges
├── Public Website
└── RIENDA TV
```

---

# 5. Participantes

## 5.1 Datos

Un participante debe tener como mínimo:

```text
id
public_id
first_name
last_name
document
phone
city
country
photo
status
created_at
updated_at
```

`public_id` debe ser un identificador público permanente.

Ejemplo:

```text
RND-000184
```

El `id` interno de base de datos no debe exponerse públicamente.

---

## 5.2 Estado

Estados:

```text
active
inactive
```

Un participante inactivo no podrá ser inscrito en nuevos torneos.

Su historial histórico debe mantenerse.

---

# 6. Caballos

## 6.1 Datos

```text
id
public_id
name
owner
breed
color
sex
birth_date
photo
status
created_at
updated_at
```

No todos estos campos tienen que ser obligatorios en el MVP.

Campos mínimos:

```text
name
status
```

---

# 7. Torneos

Un torneo representa una competencia completa.

## 7.1 Datos

```text
id
name
slug
description
location
start_date
end_date
status
cover_image
created_at
updated_at
```

## 7.2 Estados

```text
draft
published
in_progress
finished
cancelled
```

### Draft

El torneo está siendo configurado.

No es visible públicamente.

### Published

El torneo está publicado.

### In progress

El torneo está actualmente en competencia.

### Finished

El torneo finalizó.

### Cancelled

El torneo fue cancelado.

---

# 8. Categorías

Una categoría pertenece a un torneo.

Ejemplo:

```text
Torneo Nacional 2026

├── Profesional
├── Amateur
├── Juvenil
└── Femenina
```

## 8.1 Datos

```text
id
tournament_id
name
description
order
status
created_at
updated_at
```

---

# 9. Configuración de categoría

Cada categoría debe definir su estructura competitiva.

```text
number_of_days
rounds_per_day
```

Ejemplo:

```text
Categoría Profesional

Días: 3
Rondas por día: 4
```

Esto genera:

```text
Día 1
 ├── Ronda 1
 ├── Ronda 2
 ├── Ronda 3
 └── Ronda 4

Día 2
 ├── Ronda 1
 ├── Ronda 2
 ├── Ronda 3
 └── Ronda 4

Día 3
 ├── Ronda 1
 ├── Ronda 2
 ├── Ronda 3
 └── Ronda 4
```

---

# 10. Inscripciones

La inscripción representa la participación de un participante en una categoría específica de un torneo.

```text
Registration
```

## 10.1 Relaciones

```text
Registration
    ├── TournamentCategory
    ├── Participant
    └── Horse
```

## 10.2 Datos

```text
id
tournament_category_id
participant_id
horse_id
competition_group_id
registration_number
status
created_at
updated_at
```

## 10.3 Regla

Un participante no debe poder tener dos inscripciones dentro de la misma categoría.

Restricción:

```text
UNIQUE(
    tournament_category_id,
    participant_id
)
```

Si el negocio posteriormente permite competir con múltiples caballos en una misma categoría, esta regla deberá cambiar.

## 10.4 Grupos de salida

Los grupos de salida ordenan la operación de pista del evento. Son del **torneo**,
no de la categoría: un mismo grupo puede agrupar inscripciones de varias categorías.

```text
CompetitionGroup
    ├── tournament_id
    ├── name
    └── order
```

Restricciones:

```text
UNIQUE(tournament_id, name)
```

Cada inscripción puede asignarse a un grupo (`competition_group_id`, opcional).
Si el grupo se elimina, la inscripción queda sin grupo (no se pierde el historial
de la inscripción). El orden dentro del grupo es el dorsal
(`registration_number`).

**La cola de salida no se persiste**: se calcula bajo demanda. Orden:

1. Primero las inscripciones cuya ronda está activa (las que salen en este momento).
2. Luego por `order` del grupo.
3. Los no asignados a ningún grupo salen al final.

### Orden desde el segundo día

Desde el segundo día el orden deja de ser por grupos y pasa a ser **deportivo,
por categoría**, según el rendimiento acumulado: salen primero los que menos
lazos han acertado y, entre quienes van iguales, los que más tiempo tienen
registrado.

1. Primero las inscripciones cuya ronda está activa.
2. Luego cada categoría agrupa a sus vaqueros:
   - `total_successful_rounds` ascendente (menos lazos acertados primero).
   - `total_time_ms` descendente (más tiempo registrado primero).
   - Dorsal como desempate final.
4. Dentro del grupo, por dorsal.

Quien ya registró en la ronda activa no vuelve a la cola hasta que la ronda
cierre; entonces pasa a la siguiente ronda pendiente. En el **lazo final** solo
entran a la cola los finalistas (top 10 de la clasificación). El cierre es
**automático** cuando todos los inscritos activos (o todos los finalistas en la
final) registraron su resultado: la ronda se finaliza en ese momento y la cola
avanza a la siguiente ronda sin intervención manual. La consola del torneo
(`/admin/tournaments/{torneo}/competition`) es la pantalla única de captura y
clasificación, y escucha el canal `tournament.{id}` para mantenerse en vivo.

---

# 11. Días de competencia

Cada categoría tendrá días de competencia.

```text
CompetitionDay
```

## Datos

```text
id
tournament_category_id
day_number
date
status
created_at
updated_at
```

Ejemplo:

```text
Categoría Profesional

Día 1 → 2026-08-20
Día 2 → 2026-08-21
Día 3 → 2026-08-22
```

---

# 12. Rondas

Cada día contiene varias rondas.

```text
CompetitionDay
    │
    ├── Round 1
    ├── Round 2
    ├── Round 3
    └── Round 4
```

## Datos

```text
id
competition_day_id
round_number
is_final
status
started_at
finished_at
created_at
updated_at
```

Estados:

```text
pending
active
finished
cancelled
```

### Lazo final

El último día de cada categoría tiene una **ronda extra** (el "lazo final") que
`BuildCategorySchedule` crea con `round_number = rounds_per_day + 1` e
`is_final = true`. Su resultado sí cuenta para el acumulado de la categoría.

Solo la corren los **10 primeros de la clasificación** (`category_standings` con
posición dentro del top 10). Si hay menos de 10 inscritos con resultados
registrados, pasan todos. La consola y la cola solo listan finalistas en esta
ronda, y al terminar la final el día no deja pendientes: la cola queda vacía.

```text
Categoría con 3 días × 4 lazos:

Día 3 → R1  R2  R3  R4  F (lazo final, top 10)
```

---

# 13. Resultados de ronda

Cada participante inscrito tendrá un resultado por ronda.

```text
RoundResult
```

## Datos

```text
id
round_id
registration_id
time_ms
successful
notes
created_at
updated_at
```

---

# 14. Almacenamiento del tiempo

El tiempo **NO debe almacenarse como FLOAT**.

Debe almacenarse como entero en milisegundos.

Ejemplo:

```text
8.342 segundos
```

se almacena como:

```text
8342
```

Ejemplo:

```text
12.005 segundos
```

se almacena como:

```text
12005
```

Esto evita errores de precisión.

La interfaz puede mostrar:

```text
8.342
```

pero la base de datos almacena:

```text
8342
```

---

# 15. Ronda fallida

Cuando una ronda es fallida:

```text
successful = false
time_ms = 0
```

Ejemplo:

```text
Ronda 1 → 8.342 → acertada
Ronda 2 → 7.521 → acertada
Ronda 3 → 0.000 → fallida
Ronda 4 → 9.112 → acertada
```

Resultado:

```text
Tiempo total = 24.975
Rondas acertadas = 3
```

El tiempo de una ronda fallida no debe acumularse.

---

# 16. Resultados diarios

Los resultados diarios deben calcularse a partir de los resultados de las rondas.

Para un participante:

```text
daily_time_ms =
SUM(time_ms WHERE successful = true)

daily_successful_rounds =
COUNT(successful = true)
```

Ejemplo:

```text
Día 1

R1 → 8.342 ✓
R2 → 7.521 ✓
R3 → 0.000 ✗
R4 → 9.112 ✓

Tiempo: 24.975
Acertadas: 3
```

---

# 17. Resultado acumulado del torneo

El resultado acumulado se calcula sumando los resultados diarios.

```text
total_time_ms =
SUM(daily_time_ms)

total_successful_rounds =
SUM(daily_successful_rounds)
```

Ejemplo:

```text
Día 1
3 aciertos
24.975 segundos

Día 2
4 aciertos
29.421 segundos

Día 3
3 aciertos
21.812 segundos
```

Total:

```text
10 aciertos
76.208 segundos
```

---

# 18. Clasificación

La clasificación de una categoría debe ordenar primero por:

1. Mayor cantidad de rondas acertadas.
2. Menor tiempo acumulado.

Orden lógico:

```text
successful_rounds DESC
total_time_ms ASC
```

Ejemplo:

| Posición | Jinete         | Acertadas | Tiempo |
| -------: | -------------- | --------: | -----: |
|        1 | Carlos Pérez   |        10 | 76.208 |
|        2 | Juan Rodríguez |        10 | 81.421 |
|        3 | Pedro López    |         9 | 68.100 |

Pedro tiene menor tiempo que Carlos, pero tiene menos rondas acertadas.

Por lo tanto Carlos gana.

---

# 19. Empates

El MVP debe soportar empate en:

```text
successful_rounds
total_time_ms
```

Cuando ambos valores son iguales:

```text
1. Cantidad de rondas acertadas
2. Tiempo acumulado
3. Posición empatada
```

No debe inventarse un tercer criterio hasta que las reglas oficiales del deporte lo definan.

---

# 20. Retos

Un reto representa un enfrentamiento entre dos participantes.

```text
Challenge
```

## Datos

```text
id
tournament_id
category_id
competition_day_id
participant_a_id
participant_b_id
winner_id
status
scheduled_at
completed_at
notes
created_at
updated_at
```

---

# 21. Nivel del reto

Un reto puede estar asociado a:

### Torneo

```text
tournament_id
```

### Categoría

```text
tournament_id
category_id
```

### Día

```text
tournament_id
category_id
competition_day_id
```

La lógica de negocio debe garantizar que las relaciones sean coherentes.

---

# 22. Resultado de reto

El reto debe almacenar:

```text
participant_a
participant_b
winner
```

Estados:

```text
scheduled
in_progress
completed
cancelled
```

Ejemplo:

```text
Carlos Pérez
      VS
Juan Rodríguez

🏆 Carlos Pérez
```

---

# 23. Historial del participante

Toda participación debe alimentar automáticamente el historial del participante.

El perfil público debe poder mostrar:

```text
Participante
├── Datos básicos
├── Torneos
├── Categorías
├── Resultados
├── Victorias
├── Rondas
├── Rondas acertadas
├── Tiempo acumulado
└── Retos
```

---

# 24. Ranking

El ranking general del participante no debe implementarse completamente en el MVP si las reglas de puntuación nacional todavía no están definidas.

El MVP sí debe permitir:

* Ranking por categoría.
* Ranking por torneo.
* Clasificación diaria.
* Clasificación acumulada.

Posteriormente se podrá agregar:

```text
Ranking Nacional
Ranking por temporada
Ranking por categoría
Ranking histórico
```

---

# 25. Landing pública

La landing pública debe mostrar información deportiva, no solamente información corporativa.

Secciones iniciales:

```text
Hero
Torneo activo
Clasificación en vivo
Retos
Próximos torneos
Últimos resultados
Jinetes destacados
```

---

# 26. Torneo en vivo

Cuando exista un torneo en progreso, la landing debe destacar:

```text
🔴 EN VIVO

Torneo Nacional de Lazo

Categoría:
Profesional

Día:
2

Ronda:
4
```

Y mostrar la clasificación actualizada.

---

# 27. RIENDA TV

RIENDA debe tener una aplicación/interfaz exclusiva para transmisiones.

Ruta sugerida:

```text
/tv
```

O:

```text
/tv/tournament/{tournament}/category/{category}
```

También debe existir un modo overlay:

```text
/tv/overlay/{token}
```

El objetivo es utilizar la URL como fuente de navegador en OBS, Streamlabs u otro software de transmisión.

---

# 28. Diseño del RIENDA TV Overlay

El overlay no debe mostrar toda la plataforma.

Debe ser un componente visual compacto.

Debe poder mostrar:

```text
RIENDA
🔴 EN VIVO

TORNEO NACIONAL
CATEGORÍA PROFESIONAL

DÍA 2 · RONDA 4

CARLOS PÉREZ
🐎 RELÁMPAGO

08.342

✓ RONDA ACERTADA
```

También puede mostrar:

```text
CLASIFICACIÓN

1  Carlos Pérez       10    76.208
2  Juan Rodríguez      9    72.102
3  Pedro López          9    78.210
```

---

# 29. Realtime

El realtime será implementado utilizando **Laravel Reverb**.

Flujo:

```text
Admin
   │
   ▼
Laravel
   │
   ├── Guarda resultado
   │
   ├── Recalcula clasificación
   │
   └── Emite evento
           │
           ▼
        Reverb
           │
       ┌───┴─────────────┐
       ▼                 ▼
   Landing            RIENDA TV
```

---

# 30. Eventos realtime

Eventos iniciales:

```text
TournamentStarted
TournamentFinished

RoundStarted
RoundCompleted

RoundResultRecorded
RoundResultUpdated

LeaderboardUpdated

ChallengeStarted
ChallengeCompleted
ChallengeUpdated
```

El frontend debe actualizar la información sin necesidad de recargar la página.

---

# 31. Actualización de clasificación

Cuando se registre o modifique un resultado:

```text
RoundResult
      ↓
Recalculate Daily Result
      ↓
Recalculate Tournament Result
      ↓
Recalculate Leaderboard
      ↓
Broadcast LeaderboardUpdated
```

La clasificación nunca debe depender de información manual introducida por el administrador.

---

# 32. Corrección de resultados

El administrador debe poder corregir una ronda.

Ejemplo:

```text
Antes:
8.342 segundos

Después:
7.842 segundos
```

El sistema debe:

1. Actualizar el resultado.
2. Recalcular el día.
3. Recalcular el acumulado.
4. Recalcular la clasificación.
5. Emitir los eventos realtime correspondientes.

---

# 33. Auditoría

Los resultados oficiales son información sensible.

Toda modificación de un resultado debe registrar:

```text
user_id
action
entity
entity_id
old_value
new_value
created_at
```

Ejemplo:

```text
Administrador:
Carlos Admin

Acción:
Actualizó RoundResult

Anterior:
8.342

Nuevo:
7.842

Fecha:
2026-08-20 19:42:12
```

Esto permitirá mantener trazabilidad.

---

# 34. Arquitectura Laravel

La aplicación debe construirse siguiendo una separación clara entre:

```text
Domain
Application
Infrastructure
Presentation
```

No colocar toda la lógica de negocio directamente en Controllers.

---

# 35. Servicios de dominio

Servicios iniciales sugeridos:

```text
CalculateDailyResult
CalculateTournamentResult
CalculateLeaderboard
RegisterRoundResult
UpdateRoundResult
CompleteRound
CompleteChallenge
```

Ejemplo conceptual:

```text
RegisterRoundResult
    ↓
Validate Result
    ↓
Persist Result
    ↓
Recalculate Results
    ↓
Broadcast Events
```

---

# 36. API

El frontend administrativo y frontend público deben consumir una API.

Endpoints conceptuales:

```text
GET    /api/tournaments
POST   /api/tournaments
GET    /api/tournaments/{id}
PUT    /api/tournaments/{id}

GET    /api/participants
POST   /api/participants
GET    /api/participants/{id}
PUT    /api/participants/{id}

GET    /api/horses
POST   /api/horses

GET    /api/tournaments/{id}/categories
POST   /api/tournaments/{id}/categories

GET    /api/categories/{id}/participants
POST   /api/categories/{id}/registrations

GET    /api/categories/{id}/leaderboard

POST   /api/rounds/{id}/results
PUT    /api/round-results/{id}

GET    /api/challenges
POST   /api/challenges
PUT    /api/challenges/{id}

GET    /api/tv/{tournament}/{category}
```

La implementación final de endpoints puede ajustarse a la arquitectura utilizada en Laravel.

---

# 37. Seguridad

El administrador debe estar autenticado.

Todas las operaciones administrativas deben requerir autorización.

Especialmente:

```text
Crear torneo
Modificar torneo
Registrar participante
Registrar inscripción
Registrar resultado
Modificar resultado
Crear reto
Modificar reto
Publicar torneo
```

La interfaz pública solamente debe permitir lectura.

---

# 38. Reglas de integridad

El sistema debe impedir:

* Participante duplicado.
* Inscripción duplicada en una categoría.
* Resultado duplicado para una misma inscripción y ronda.
* Ronda perteneciente a otro día.
* Día perteneciente a otra categoría.
* Categoría perteneciente a otro torneo.
* Participante inactivo inscrito en una competencia.
* Participante enfrentándose contra sí mismo en un reto.
* Resultado de una ronda inexistente.
* Resultado registrado para una inscripción que no pertenece a la categoría.

---

# 39. Identificadores públicos

Las entidades públicas deben tener identificadores seguros.

Ejemplo:

```text
Participant: RND-000184
Tournament: T-2026-001
Horse: H-000421
```

Los identificadores internos de la base de datos no deben utilizarse como identificadores públicos cuando exista riesgo de enumeración.

---

# 40. MVP — Prioridad de desarrollo

## Fase 1

### Administración

* [ ] Autenticación de administrador.
* [ ] CRUD de participantes.
* [ ] CRUD de caballos.
* [ ] CRUD de torneos.
* [ ] CRUD de categorías.
* [ ] Configuración de días.
* [ ] Configuración de rondas.
* [ ] Inscripción de participantes.
* [ ] Asociación de caballo.

## Fase 2

### Competencia

* [x] Iniciar día.
* [x] Iniciar ronda.
* [x] Registrar resultado.
* [x] Marcar ronda como acertada/fallida.
* [x] Registrar tiempo en milisegundos.
* [x] Calcular resultado diario.
* [x] Calcular resultado acumulado.
* [x] Calcular clasificación.
* [x] Finalizar ronda.
* [x] Finalizar día.
* [x] Finalizar categoría.
* [x] Grupos de salida y cola de la consola del torneo.

## Fase 3

### Público

* [x] Landing.
* [x] Torneos.
* [x] Detalle del torneo.
* [x] Categorías.
* [x] Clasificación.
* [x] Participantes.
* [x] Perfil público.
* [x] Resultados.

## Fase 4

### Retos

* [ ] Crear reto.
* [ ] Programar reto.
* [ ] Registrar ganador.
* [ ] Historial.
* [ ] Mostrar reto en landing.

## Fase 5

### Realtime

* [x] Configurar Laravel Reverb.
* [x] Eventos de rondas.
* [x] Eventos de resultados.
* [x] Evento de clasificación (la clasificación se refresca en vivo a partir de los mismos eventos de resultado).
* [x] Actualización realtime de landing.
* [x] Actualización realtime de la consola del torneo (canal `tournament.{id}`).
* [ ] Actualización realtime de RIENDA TV (la landing ya escucha el canal público; el overlay de OBS queda para la Fase 6).

## Fase 6

### RIENDA TV

* [x] Overlay para OBS (fuente de navegador, `/tv` y `/tv/{torneo}/{categoria}`).
* [x] Vista compacta (tabla vertical, alta y angosta, fondo negro traslúcido).
* [x] Clasificación.
* [x] Actualización realtime.
* [ ] Vista de transmisión (pantalla de torneo completa para el programa).
* [ ] Información de ronda.
* [ ] Resultado del participante.
* [ ] Retos.

---

# 41. Fuera del MVP

No implementar inicialmente:

* Pagos.
* Inscripción pública.
* Aplicación móvil.
* Chat.
* Sistema social.
* Ranking nacional con puntuación propia.
* Marketplace de caballos.
* Streaming de video.
* Gestión de transmisiones.
* Sistema avanzado de estadísticas.
* Cuenta del jinete.
* Reclamo de perfiles.

Estas funcionalidades podrán agregarse posteriormente.

---

# 42. Evolución futura

La evolución esperada del producto es:

```text
MVP
│
├── Torneos
├── Resultados
└── Realtime
      │
      ▼
Plataforma deportiva
│
├── Perfiles
├── Historial
├── Rankings
└── Retos
      │
      ▼
Ecosistema RIENDA
│
├── RIENDA ID
├── Ranking nacional
├── Temporadas
├── Estadísticas
├── Perfiles verificados
├── Inscripciones online
└── RIENDA TV
```

---

# 43. Visión del producto

RIENDA no debe posicionarse únicamente como un software para administrar torneos.

La visión del producto es:

> **Convertirse en la infraestructura digital oficial para registrar, medir y mostrar la trayectoria competitiva de los jinetes de lazo.**

Cada competencia genera datos.

Cada dato alimenta el historial del jinete.

Cada torneo genera rankings.

Cada reto genera enfrentamientos.

Y cada resultado puede ser mostrado en tiempo real a través de RIENDA TV.

El activo principal de RIENDA a largo plazo será el **historial deportivo confiable de los jinetes**.

---

# 44. Regla fundamental del sistema

La fuente de verdad debe ser:

```text
PARTICIPANTE
      ↓
INSCRIPCIÓN
      ↓
RONDA
      ↓
RESULTADO
      ↓
CÁLCULOS
      ↓
CLASIFICACIÓN
      ↓
HISTORIAL
      ↓
RIENDA TV
```

Los resultados derivados nunca deben ser la única fuente de información.

Los datos originales de cada ronda deben mantenerse para poder reconstruir cualquier clasificación, corregir resultados y auditar el historial deportivo.
