<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_expense_id',
        'user_id',
        'paid_amount',
        'share_amount',
        'percentage'
    ];

    protected $casts = [
        'paid_amount' => 'decimal:2',
        'share_amount' => 'decimal:2',
        'percentage' => 'decimal:2'
    ];

    public function groupExpense(): BelongsTo
    {
        return $this->belongsTo(GroupExpense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}