<?php

declare(strict_types=1);

namespace App\Modules\Horses\Presentation\Http\Requests;

use App\Modules\Horses\Domain\Enums\HorseSex;
use App\Modules\Horses\Domain\Enums\HorseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class HorseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', new Enum(HorseStatus::class)],

            // El jinete se referencia por su identificador público.
            'participant_public_id' => ['nullable', 'string', Rule::exists('participants', 'public_id')],

            'owner' => ['nullable', 'string', 'max:150'],
            'breed' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:60'],
            'sex' => ['nullable', new Enum(HorseSex::class)],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'status' => 'estado',
            'participant_public_id' => 'vaquero',
            'owner' => 'propietario',
            'breed' => 'raza',
            'color' => 'color',
            'sex' => 'sexo',
            'birth_date' => 'fecha de nacimiento',
        ];
    }
}
