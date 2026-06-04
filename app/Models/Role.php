<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'descripcion'];

    /**
     * Ocultar timestamps si no se usan
     */
    public $timestamps = false;

    /**
     * Get the permissions for the role.
     */

    /**
     * Get the users with this role.
     * Relación Many-to-Many con tabla pivote 'roles_usuarios'.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'roles_usuarios', 'rol_id', 'usuario_id')
            ->withPivot('asignado_en')
            ->withTimestamps();
    }

    /**
     * Check if role has permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('name', $permission)->exists();
    }
}
