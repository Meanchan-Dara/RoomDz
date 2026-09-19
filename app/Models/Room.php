<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'user_id',
        'name',
        'type',
        'price',
        'price_period',
        'status',
        'rating',
        'reviews_count',
        'address',
        'latitude',
        'longitude',
        'image',
        'is_negotiable',
        'is_featured',
        'listing_type',
        'total_units',
        'available_units',
        'deposit_price',
        'deposit_currency',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'user_id' => 'integer',
        'price' => 'float',
        'deposit_price' => 'float',
        'deposit_currency' => 'string',
        'rating' => 'float',
        'reviews_count' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_negotiable' => 'boolean',
        'is_featured' => 'boolean',
        'total_units' => 'integer',
        'available_units' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detail(): HasOne
    {
        return $this->hasOne(RoomDetail::class);
    }

    public function viewingRequests(): HasMany
    {
        return $this->hasMany(ViewingRequest::class);
    }

    /**
     * Get all favorite records for this room.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Get all users who favorited this room.
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /**
     * Get all payments for this room.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
