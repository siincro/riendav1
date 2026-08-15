<?php

declare(strict_types=1);

namespace App\Modules\Challenges\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChallengeRequest extends FormRequest
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
            // El reto se juega dentro de un evento; la categoría de cada
            // vaquero da igual y se resuelve sola.
            'tournament_slug' => ['required', 'string', 'exists:tournaments,slug'],
            'challenger_public_id' => ['required', 'string', 'exists:participants,public_id'],
            'rival_public_id' => [
                'required',
                'string',
                'exists:participants,public_id',
                'different:challenger_public_id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tournament_slug' => 'evento',
            'challenger_public_id' => 'retador',
            'rival_public_id' => 'rival',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rival_public_id.different' => 'Un vaquero no puede retarse a sí mismo.',
        ];
    }
}
