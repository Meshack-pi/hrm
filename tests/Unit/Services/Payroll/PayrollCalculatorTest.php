<?php

namespace Tests\Unit\Services\Payroll;

use App\Models\StatutoryRate;
use App\Models\TaxBand;
use App\Services\Payroll\AhlCalculator;
use App\Services\Payroll\NssfCalculator;
use App\Services\Payroll\PayeCalculator;
use App\Services\Payroll\ShifCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private NssfCalculator $nssf;

    private ShifCalculator $shif;

    private AhlCalculator $ahl;

    private PayeCalculator $paye;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedStatutoryRates();

        $this->nssf = new NssfCalculator;
        $this->shif = new ShifCalculator;
        $this->ahl = new AhlCalculator;
        $this->paye = new PayeCalculator;
    }

    private function seedStatutoryRates(): void
    {
        $date = '2026-02-01';

        StatutoryRate::create([
            'code' => 'NSSF', 'name' => 'NSSF',
            'employee_rate' => 0.06, 'employer_rate' => 0.06,
            'lower_limit' => 9000, 'upper_limit' => 108000,
            'max_amount' => 6480, 'effective_from' => $date,
        ]);

        StatutoryRate::create([
            'code' => 'SHIF', 'name' => 'SHIF',
            'employee_rate' => 0.0275, 'min_amount' => 300,
            'effective_from' => $date,
        ]);

        StatutoryRate::create([
            'code' => 'AHL', 'name' => 'AHL',
            'employee_rate' => 0.015, 'employer_rate' => 0.015,
            'effective_from' => $date,
        ]);

        StatutoryRate::create([
            'code' => 'PERSONAL_RELIEF', 'name' => 'Personal Relief',
            'fixed_amount' => 2400, 'effective_from' => $date,
        ]);

        StatutoryRate::create([
            'code' => 'INSURANCE_RELIEF', 'name' => 'Insurance Relief',
            'employee_rate' => 0.15, 'max_amount' => 5000,
            'effective_from' => $date,
        ]);

        StatutoryRate::create([
            'code' => 'PENSION_CAP', 'name' => 'Pension Cap',
            'max_amount' => 30000, 'effective_from' => $date,
        ]);

        StatutoryRate::create([
            'code' => 'DISABILITY_EXEMPTION', 'name' => 'Disability Exemption',
            'max_amount' => 150000, 'effective_from' => $date,
        ]);

        $bands = [
            ['lower' => 0, 'upper' => 24000, 'rate' => 0.10],
            ['lower' => 24000, 'upper' => 32333, 'rate' => 0.25],
            ['lower' => 32333, 'upper' => 500000, 'rate' => 0.30],
            ['lower' => 500000, 'upper' => 800000, 'rate' => 0.325],
            ['lower' => 800000, 'upper' => null, 'rate' => 0.35],
        ];

        foreach ($bands as $band) {
            TaxBand::create(array_merge($band, ['effective_from' => $date]));
        }
    }

    private function fullCalculation(float $gross): array
    {
        $date = '2026-10-01';

        $nssf = $this->nssf->calculate($gross, $date);
        $shif = $this->shif->calculate($gross, $date);
        $ahl = $this->ahl->calculate($gross, $date);

        $taxablePay = max(0, round($gross - $nssf['employee'] - $shif - $ahl['employee'], 2));

        $payeResult = $this->paye->calculate($taxablePay, $date);

        $totalDeductions = $nssf['employee'] + $shif + $ahl['employee'] + $payeResult['paye'];
        $netPay = round($gross - $totalDeductions, 2);

        return [
            'gross' => $gross,
            'nssf' => $nssf['employee'],
            'shif' => $shif,
            'ahl' => $ahl['employee'],
            'taxable' => $taxablePay,
            'paye' => $payeResult['paye'],
            'net' => $netPay,
        ];
    }

    public function test_gross_20000(): void
    {
        $r = $this->fullCalculation(20000);

        $this->assertEquals(1200.00, $r['nssf']);
        $this->assertEquals(550.00, $r['shif']);
        $this->assertEquals(300.00, $r['ahl']);
        $this->assertEquals(17950.00, $r['taxable']);
        $this->assertEquals(0.00, $r['paye']);
        $this->assertEquals(17950.00, $r['net']);
    }

    public function test_gross_30000(): void
    {
        $r = $this->fullCalculation(30000);

        $this->assertEquals(1800.00, $r['nssf']);
        $this->assertEquals(825.00, $r['shif']);
        $this->assertEquals(450.00, $r['ahl']);
        $this->assertEquals(26925.00, $r['taxable']);
        $this->assertEquals(731.25, $r['paye']);
        $this->assertEquals(26193.75, $r['net']);
    }

    public function test_gross_100000(): void
    {
        $r = $this->fullCalculation(100000);

        // 100,000 is below the 108,000 UEL, so NSSF is 6% of actual pay, not the 6,480 ceiling.
        $this->assertEquals(6000.00, $r['nssf']);
        $this->assertEquals(2750.00, $r['shif']);
        $this->assertEquals(1500.00, $r['ahl']);
        $this->assertEquals(89750.00, $r['taxable']);

        $this->assertEqualsWithDelta(19308.35, $r['paye'], 0.01);
        $this->assertEqualsWithDelta(70441.65, $r['net'], 0.01);
    }

    public function test_gross_150000(): void
    {
        $r = $this->fullCalculation(150000);

        $this->assertEquals(6480.00, $r['nssf']);
        $this->assertEquals(4125.00, $r['shif']);
        $this->assertEquals(2250.00, $r['ahl']);
        $this->assertEquals(137145.00, $r['taxable']);

        $this->assertEqualsWithDelta(33526.85, $r['paye'], 0.01);
        $this->assertEqualsWithDelta(103618.15, $r['net'], 0.01);
    }

    public function test_nssf_below_lel(): void
    {
        $r = $this->nssf->calculate(5000, '2026-10-01');
        $this->assertEquals(300.00, $r['employee']); // 6% × 5000
    }

    public function test_nssf_above_uel(): void
    {
        $r = $this->nssf->calculate(200000, '2026-10-01');
        $this->assertEquals(6480.00, $r['employee']); // capped
    }

    public function test_shif_minimum(): void
    {
        $amount = $this->shif->calculate(5000, '2026-10-01');
        $this->assertEquals(300.00, $amount); // min 300
    }

    public function test_ahl_calculation(): void
    {
        $r = $this->ahl->calculate(50000, '2026-10-01');
        $this->assertEquals(750.00, $r['employee']);
        $this->assertEquals(750.00, $r['employer']);
    }
}
