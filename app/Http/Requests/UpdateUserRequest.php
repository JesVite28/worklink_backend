<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
            'nombre' => 'sometimes|string|max:100',
            'apellido' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $this->route('id'),
            'password' => 'sometimes|string|min:8|confirmed',
            'tipo_cuenta' => 'sometimes|in:Cliente,Freelancer,Empresa',
            'telefono' => 'nullable|string|max:20',
            'foto_perfil' => 'nullable|string|max:255',
            'activo' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.max' => 'El nombre no puede exceder 100 caracteres',
            'apellido.max' => 'El apellido no puede exceder 100 caracteres',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'El email ya está registrado',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'La confirmación de contraseña no coincide',
            'tipo_cuenta.in' => 'El tipo de cuenta debe ser Cliente, Freelancer o Empresa',
        ];
    }
}
