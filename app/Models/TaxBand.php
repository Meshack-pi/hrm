<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class TaxBand extends Model
{
    use LogsActivity;

    protected $fillable = ['lower', 'upper', 'rate', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'lower' => 'decimal:2',
            'upper' => 'decimal:2',
            'rate' => 'decimal:4',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
