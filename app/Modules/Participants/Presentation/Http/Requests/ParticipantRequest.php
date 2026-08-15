<?php

declare(strict_types=1);

namespace App\Modules\Participants\Presentation\Http\Requests;

use App\Modules\Participants\Domain\Enums\ParticipantStatus;
use App\Modules\Participants\Domain\Models\Participant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ParticipantRequest extends FormRequest
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
        $participant = $this->route('participant');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],

            // El documento es el candado contra participantes duplicados.
            'document' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('participants', 'document')->ignore(
                    $participant instanceof Participant ? $participant->id : null
                ),
            ],

            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'status' => ['required', new Enum(ParticipantStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'document' => 'documento',
            'phone' => 'teléfono',
            'city' => 'ciudad',
            'country' => 'país',
            'status' => 'estado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document.unique' => 'Ya existe un participante registrado con este documento.',
        ];
    }
}
