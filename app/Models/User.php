<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'google_id',
        'is_verified',
        'location_tag',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    /**
     * Get the role associated with the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(role::class);
    }

    /**
     * Get all rooms owned by this user/landlord.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Get all viewing requests requested by this user.
     */
    public function viewingRequests(): HasMany
    {
        return $this->hasMany(ViewingRequest::class);
    }

    /**
     * Get all favorite records for this user.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Get all rooms favorited by this user directly.
     */
    public function favoriteRooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'favorites')->withTimestamps();
    }

    /**
     * Check if the user has the "owner" role.
     */
    public function isOwner(): bool
    {
        return $this->role?->name === 'owner';
    }

    /**
     * Check if the user has the "admin" role.
     */
    public function isAdmin(): bool
    {
        return $this->role?->name === 'admin';
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role?->name, $roles);
    }
}
