<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PublicHoliday extends Model
{
    use LogsActivity;

    protected $fillable = ['date', 'name', 'year', 'source', 'is_active', 'is_substitute', 'synced_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_active' => 'boolean',
            'is_substitute' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['date', 'name', 'is_active'])->logOnlyDirty();
    }
}
