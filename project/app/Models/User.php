<?php

namespace App\Models;

use App\Models\Expense;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
    ];

    /**
     * Get the expenses for the user.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get the tags for the user.
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function createdGroups()
{
    return $this->hasMany(Group::class, 'creator_id');
}

public function groups()
{
    return $this->belongsToMany(Group::class, 'group_members')
                ->withTimestamps();
}

public function expenseShares()
{
    return $this->hasMany(ExpenseShare::class);
}

public function paymentsFrom()
{
    return $this->hasMany(Payment::class, 'from_user_id');
}

public function paymentsTo()
{
    return $this->hasMany(Payment::class, 'to_user_id');
}
}