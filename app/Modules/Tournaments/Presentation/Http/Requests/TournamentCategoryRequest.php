<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Presentation\Http\Requests;

use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TournamentCategoryRequest extends FormRequest
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
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:120'],

            // Una categoría no puede repetirse dentro del mismo torneo. La
            // tabla ya lo impone con un índice único; validarlo aquí convierte
            // el choque en un error de formulario en vez de un 500.
            'slug' => [
                'required',
                'string',
                'max:140',
                Rule::unique('tournament_categories', 'slug')
                    ->where('tournament_id', $tournament instanceof Tournament ? $tournament->id : null)
                    ->ignore($category instanceof TournamentCategory ? $category->id : null),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],

            // Estructura competitiva. Los topes son defensivos: una categoría
            // con cientos de rondas casi siempre es un error de captura.
            'number_of_days' => ['required', 'integer', 'min:1', 'max:30'],
            'rounds_per_day' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Ya existe una categoría con ese nombre en este evento.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'number_of_days' => 'número de días',
            'rounds_per_day' => 'lazos por día',
        ];
    }
}
