<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RustdeskDevice extends Model
{
    protected $fillable = [
        'user_name',
        'computer_number',
        'rustdesk_id',
        'notes',
    ];
}