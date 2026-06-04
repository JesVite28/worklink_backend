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
            'nombre' => 'required|string|max:80|unique:roles,nombre',
            'descripcion' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del rol es obligatorio',
            'nombre.max' => 'El nombre del rol no puede exceder 80 caracteres',
            'nombre.unique' => 'El nombre del rol ya existe',
            'descripcion.max' => 'La descripción no puede exceder 500 caracteres',
        ];
    }
}
