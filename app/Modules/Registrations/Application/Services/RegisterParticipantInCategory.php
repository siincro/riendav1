<?php

declare(strict_types=1);

namespace App\Modules\Registrations\Application\Services;

use App\Modules\Competition\Domain\Models\CompetitionGroup;
use App\Modules\Horses\Domain\Models\Horse;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Domain\Enums\RegistrationStatus;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Inscribe a un participante en una categoría.
 *
 * Aquí se concentran las reglas de integridad del documento: no se inscribe
 * un participante inactivo, no se inscribe dos veces en la misma categoría, y
 * el caballo debe estar habilitado para competir.
 */
final class RegisterParticipantInCategory
{
    public function handle(
        TournamentCategory $category,
        Participant $participant,
        ?Horse $horse = null,
        ?int $registrationNumber = null,
        ?CompetitionGroup $group = null,
    ): Registration {
        $this->guardCategoryAcceptsRegistrations($category);
        $this->guardParticipantIsRegisterable($participant);
        $this->guardHorseCanCompete($horse);
        $this->guardGroupBelongsToTournament($category, $group);

        return DB::transaction(function () use ($category, $participant, $horse, $registrationNumber, $group): Registration {
            $this->guardParticipantNotAlreadyRegistered($category, $participant);

            try {
                return Registration::query()->create([
                    'tournament_category_id' => $category->id,
                    'participant_id' => $participant->id,
                    'horse_id' => $horse?->id,
                    'competition_group_id' => $group?->id,
                    'registration_number' => $registrationNumber ?? $this->nextRegistrationNumber($category),
                    'status' => RegistrationStatus::Active,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                throw new DomainException(
                    'No fue posible inscribir al participante: ya existe una inscripción con esos datos en la categoría.',
                    ['participant_public_id' => 'El participante o el dorsal ya están registrados en esta categoría.'],
                );
            }
        });
    }

    private function guardCategoryAcceptsRegistrations(TournamentCategory $category): void
    {
        if (! $category->acceptsRegistrations()) {
            throw new DomainException(
                'Esta categoría ya no admite inscripciones.',
                ['category' => 'La categoría está en progreso, finalizada o pertenece a un evento cerrado.'],
            );
        }
    }

    private function guardParticipantIsRegisterable(Participant $participant): void
    {
        if (! $participant->canBeRegistered()) {
            throw new DomainException(
                "El participante {$participant->full_name} está inactivo y no puede inscribirse en nuevos eventos.",
                ['participant_public_id' => 'El participante está inactivo.'],
            );
        }
    }

    private function guardHorseCanCompete(?Horse $horse): void
    {
        if ($horse !== null && ! $horse->canCompete()) {
            throw new DomainException(
                "El caballo {$horse->name} está inactivo y no puede competir.",
                ['horse_public_id' => 'El caballo está inactivo.'],
            );
        }
    }

    /**
     * El grupo de salida es del torneo, no de la categoría: una inscripción
     * solo puede quedar en un grupo del mismo evento.
     */
    private function guardGroupBelongsToTournament(
        TournamentCategory $category,
        ?CompetitionGroup $group
    ): void {
        if ($group === null) {
            return;
        }

        if ($group->tournament_id !== $category->tournament_id) {
            throw new DomainException(
                'El grupo de salida no pertenece a este evento.',
                ['competition_group_id' => 'Seleccione un grupo del mismo evento.'],
            );
        }
    }

    private function guardParticipantNotAlreadyRegistered(
        TournamentCategory $category,
        Participant $participant
    ): void {
        $exists = Registration::query()
            ->where('tournament_category_id', $category->id)
            ->where('participant_id', $participant->id)
            ->exists();

        if ($exists) {
            throw new DomainException(
                "{$participant->full_name} ya está inscrito en la categoría {$category->name}.",
                ['participant_public_id' => 'El participante ya está inscrito en esta categoría.'],
            );
        }
    }

    /**
     * Dorsal consecutivo dentro de la categoría.
     */
    private function nextRegistrationNumber(TournamentCategory $category): int
    {
        return (int) Registration::query()
            ->where('tournament_category_id', $category->id)
            ->max('registration_number') + 1;
    }
}
