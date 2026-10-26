<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayslipLine extends Model
{
    protected $fillable = ['payslip_id', 'pay_component_id', 'name', 'type', 'amount', 'is_statutory'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_statutory' => 'boolean',
        ];
    }

    public function payslip()
    {
        return $this->belongsTo(Payslip::class);
    }

    public function payComponent()
    {
        return $this->belongsTo(PayComponent::class);
    }
}
