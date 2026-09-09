<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'description',
        'size',
        'floor',
        'deposit',
        'images',
        'facilities',
        'house_rules',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'images' => 'array',
        'facilities' => 'array',
        'house_rules' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
