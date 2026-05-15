<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'month',
        'sales_budget',
        'cogs_budget',
    ];

    protected $casts = [
        'sales_budget' => 'decimal:2',
        'cogs_budget' => 'decimal:2',
    ];

    public function getNetProfitBudgetAttribute(): float
    {
        return (float) $this->sales_budget - (float) $this->cogs_budget;
    }
}
