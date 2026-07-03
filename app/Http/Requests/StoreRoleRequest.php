<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z_]+$/',
                'unique:roles,name',
            ],
            'description' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del rol es obligatorio',
            'name.string' => 'El nombre del rol debe ser texto',
            'name.max' => 'El nombre del rol no puede exceder 80 caracteres',
            'name.regex' => 'El nombre del rol solo puede contener letras minúsculas y guiones bajos',
            'name.unique' => 'El nombre del rol ya existe',
            'description.string' => 'La descripción debe ser texto',
            'description.max' => 'La descripción no puede exceder 500 caracteres',
        ];
    }
}