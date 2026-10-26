<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class StatutoryRate extends Model
{
    use LogsActivity;

    protected $fillable = [
        'code', 'name', 'employee_rate', 'employer_rate', 'fixed_amount',
        'min_amount', 'max_amount', 'lower_limit', 'upper_limit',
        'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'employee_rate' => 'decimal:4',
            'employer_rate' => 'decimal:4',
            'fixed_amount' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'lower_limit' => 'decimal:2',
            'upper_limit' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function scopeActive($query, $date = null)
    {
        $date ??= now()->toDateString();

        return $query->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    public function scopeForCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
