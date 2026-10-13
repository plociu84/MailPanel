<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    /**
     * Tabela w bazie.
     */
    protected $table = 'admins';

    /**
     * Tabela NIE ma `updated_at`.
     */
    public $timestamps = false;

    /**
     * Kolumny, które można masowo przypisywać.
     */
    protected $fillable = [
        'username',
        'password',
    ];

    /**
     * Kolumny ukryte przy serializacji (np. w JSON).
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casty.
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];
}