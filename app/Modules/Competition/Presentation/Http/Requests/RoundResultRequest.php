<?php

declare(strict_types=1);

namespace App\Modules\Competition\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoundResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'successful' => $this->boolean('successful'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // El tiempo se captura en segundos con hasta tres decimales
            // ("8.342") y el dominio lo convierte a milisegundos enteros.
            'time' => [
                'nullable',
                'required_if:successful,true',
                'regex:/^\d{1,4}([.,]\d{1,3})?$/',
            ],
            // La penalización es opcional y se escribe igual que el tiempo;
            // un lazo fallido la descarta en el dominio.
            'penalty' => ['nullable', 'regex:/^\d{1,4}([.,]\d{1,3})?$/'],
            'successful' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'time.regex' => 'Use segundos con hasta tres decimales, por ejemplo 8.342.',
            'time.required_if' => 'Ingrese el tiempo o marque el lazo como fallido.',
            'penalty.regex' => 'Use segundos con hasta tres decimales, por ejemplo 5.000.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'time' => 'tiempo',
            'penalty' => 'penalización',
            'successful' => 'lazo acertado',
            'notes' => 'observaciones',
        ];
    }
}
