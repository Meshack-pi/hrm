<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CompanyEvent extends Model
{
    use LogsActivity;

    public const CATEGORIES = [
        'meeting' => 'Meeting',
        'training' => 'Training',
        'farm_activity' => 'Farm activity',
        'deadline' => 'Deadline',
        'celebration' => 'Celebration',
        'other' => 'Other',
    ];

    protected $fillable = [
        'title', 'description', 'category', 'start_date', 'end_date',
        'start_time', 'end_time', 'location', 'farm_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'start_date', 'end_date', 'farm_id'])->logOnlyDirty();
    }
}
