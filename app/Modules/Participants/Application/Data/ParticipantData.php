<?php

declare(strict_types=1);

namespace App\Modules\Participants\Application\Data;

use App\Modules\Participants\Domain\Enums\ParticipantStatus;

final readonly class ParticipantData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $document = null,
        public ?string $phone = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?string $photoPath = null,
        public ParticipantStatus $status = ParticipantStatus::Active,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            firstName: (string) $validated['first_name'],
            lastName: (string) $validated['last_name'],
            document: $validated['document'] ?? null,
            phone: $validated['phone'] ?? null,
            city: $validated['city'] ?? null,
            country: $validated['country'] ?? null,
            photoPath: $validated['photo_path'] ?? null,
            status: ParticipantStatus::from($validated['status'] ?? ParticipantStatus::Active->value),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'document' => $this->document,
            'phone' => $this->phone,
            'city' => $this->city,
            'country' => $this->country,
            'photo_path' => $this->photoPath,
            'status' => $this->status,
        ], fn (mixed $value): bool => $value !== null);
    }
}
