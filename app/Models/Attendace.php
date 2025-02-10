<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendace extends Model {
    protected $fillable = [
        'user_id',
        'timestamp',
        'punch_type'
    ];

    protected $casts = [
        'timestamp' => 'datetime'
    ];
    
}