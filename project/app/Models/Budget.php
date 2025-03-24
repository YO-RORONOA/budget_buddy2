<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'amount',
        'warning_threshold',
        'start_date',
        'end_date',
        'recurring'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'warning_threshold' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'recurring' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}