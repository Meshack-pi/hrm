<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class EmployeeBankDetail extends Model
{
    use LogsActivity;

    protected $fillable = [
        'employee_id', 'payment_method', 'bank_name', 'bank_code',
        'branch_name', 'branch_code', 'account_name', 'account_number',
        'mpesa_number', 'is_primary',
    ];

    /**
     * The encrypted casts decrypt on serialisation, so these are hidden from
     * array/JSON output and never reach an Inertia payload. Server-side code
     * that genuinely needs the full number (bank and M-Pesa payment files)
     * still reads $model->account_number directly.
     */
    protected $hidden = ['account_number', 'mpesa_number'];

    protected $appends = ['masked_account_number', 'masked_mpesa_number'];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'mpesa_number' => 'encrypted',
            'is_primary' => 'boolean',
        ];
    }

    public function getMaskedAccountNumberAttribute(): ?string
    {
        return $this->mask($this->account_number);
    }

    public function getMaskedMpesaNumberAttribute(): ?string
    {
        return $this->mask($this->mpesa_number);
    }

    private function mask(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $visible = substr($value, -4);

        return str_repeat('•', max(strlen($value) - 4, 0)).$visible;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['payment_method', 'bank_name', 'is_primary'])
            ->logOnlyDirty();
    }
}
