<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayComponent extends Model
{
    protected $fillable = [
        'name', 'code', 'type', 'is_taxable', 'is_pensionable',
        'calculation', 'is_statutory', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_taxable' => 'boolean',
            'is_pensionable' => 'boolean',
            'is_statutory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
