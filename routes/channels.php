<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Aquí se registran los canales de autorización para la difusión en vivo.
| Los canales privados se usan en la consola de competencia; los públicos,
| en la landing «en vivo» y en RIENDA TV.
|
*/

Broadcast::channel('category.{categoryId}', fn ($user, int $categoryId): bool => $user !== null);

// La consola del evento escucha el torneo completo: cualquier resultado de
// cualquiera de sus categorías refresca la cola de salida.
Broadcast::channel('tournament.{tournamentId}', fn ($user, int $tournamentId): bool => $user !== null);

// El canal público usa identificadores públicos (slugs de torneo y categoría)
// para que las páginas públicas puedan suscribirse sin datos internos.
Broadcast::channel('public-category.{tournament}.{category}', fn (): bool => true);
