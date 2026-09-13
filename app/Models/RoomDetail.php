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
        'utilities',
        'rental_terms',
        'rules_permissions',
        'required_documents',
        'payment_methods',
        'payment_cycle',
        'contact_info',
    ];

    protected $casts = [
        'images' => 'array',
        'facilities' => 'array',
        'house_rules' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'utilities' => 'array',
        'rental_terms' => 'array',
        'rules_permissions' => 'array',
        'required_documents' => 'array',
        'payment_methods' => 'array',
        'contact_info' => 'array',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
