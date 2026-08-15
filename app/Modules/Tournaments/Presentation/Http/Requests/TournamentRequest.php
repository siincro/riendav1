<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Presentation\Http\Requests;

use App\Modules\Tournaments\Domain\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->string('slug')->toString() ?: $this->string('name')->toString()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tournament = $this->route('tournament');

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:180',
                Rule::unique('tournaments', 'slug')->ignore(
                    $tournament instanceof Tournament ? $tournament->id : null
                ),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:160'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'cover_image' => ['nullable', 'image', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'slug' => 'identificador',
            'description' => 'descripción',
            'location' => 'lugar',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
        ];
    }
}
