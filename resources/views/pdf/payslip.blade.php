@php
    $m = fn ($v) => number_format((float) $v, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip · {{ $employee['name'] }} · {{ $period }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 10px; color: #1f2937; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .brand { border-bottom: 3px solid #1B5E20; padding-bottom: 10px; }
        .brand td { vertical-align: middle; }
        .logo { height: 72px; }
        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0; font-size: 20px; color: #1B5E20; letter-spacing: 1px; }
        .doc-title .period { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .muted { color: #6b7280; }
        .small { font-size: 8.5px; }

        .info { margin-top: 12px; }
        .info td { padding: 0; }
        .info .box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; }
        .kv td { padding: 1.5px 0; }
        .kv td.k { color: #6b7280; width: 42%; }
        .kv td.v { font-weight: bold; }

        .section { margin-top: 14px; }
        .section-title { background: #1B5E20; color: #fff; font-weight: bold; font-size: 10px;
            padding: 5px 8px; text-transform: uppercase; letter-spacing: 0.6px; }
        .lines td { padding: 4px 8px; border-bottom: 1px solid #f1f5f9; }
        .lines td.amt { text-align: right; white-space: nowrap; width: 28%; }
        .lines tr.sub td { background: #f8faf8; font-weight: bold; border-bottom: 1px solid #d1d5db; }
        .lines tr.group td { color: #1B5E20; font-weight: bold; font-size: 9px; text-transform: uppercase;
            padding-top: 7px; border-bottom: none; }
        .neg { color: #b91c1c; }

        .net { margin-top: 14px; background: #E8F5E9; border: 2px solid #1B5E20; }
        .net td { padding: 10px 12px; vertical-align: middle; }
        .net .label { font-size: 12px; font-weight: bold; color: #1B5E20; text-transform: uppercase; }
        .net .value { font-size: 20px; font-weight: bold; color: #1B5E20; text-align: right; }

        .footer { margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>

{{-- Header --}}
<table class="brand">
    <tr>
        <td style="width: 50%;">
            @if (is_file($company['logo']))
                <img class="logo" src="{{ $company['logo'] }}" alt="{{ $company['name'] }}">
            @else
                <strong style="font-size: 16px; color: #1B5E20;">{{ strtoupper($company['name']) }}</strong>
            @endif
        </td>
        <td class="doc-title">
            <h1>PAYSLIP</h1>
            <div class="period">{{ $period }}</div>
            <div class="muted small">Pay period {{ \Carbon\Carbon::parse($periodStart)->format('1 M Y') }} – {{ \Carbon\Carbon::parse($periodStart)->endOfMonth()->format('j M Y') }}</div>
        </td>
    </tr>
</table>

{{-- Employee & payment details --}}
<table class="info">
    <tr>
        <td style="width: 58%; padding-right: 8px;">
            <div class="box">
                <table class="kv">
                    <tr><td class="k">Employee</td><td class="v">{{ $employee['name'] }}</td></tr>
                    <tr><td class="k">Employee no.</td><td class="v">{{ $employee['number'] }}</td></tr>
                    <tr><td class="k">Job title</td><td class="v">{{ $employee['jobTitle'] ?? '—' }}</td></tr>
                    <tr><td class="k">Department</td><td class="v">{{ $employee['department'] ?? '—' }}</td></tr>
                    <tr><td class="k">Farm</td><td class="v">{{ $employee['farm'] ?? '—' }}</td></tr>
                </table>
            </div>
        </td>
        <td style="width: 42%;">
            <div class="box">
                <table class="kv">
                    <tr><td class="k">KRA PIN</td><td class="v">{{ $employee['kraPin'] ?? 'Not provided' }}</td></tr>
                    <tr><td class="k">NSSF no.</td><td class="v">{{ $employee['nssf'] ?? 'Not provided' }}</td></tr>
                    <tr><td class="k">SHA no.</td><td class="v">{{ $employee['sha'] ?? 'Not provided' }}</td></tr>
                    <tr><td class="k">Paid by</td><td class="v">{{ $payment['method'] ?? '—' }}{{ $payment['bank'] ? ' · '.$payment['bank'] : '' }}</td></tr>
                    <tr><td class="k">Account</td><td class="v">{{ $payment['account'] ?? '—' }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<table style="margin-top: 0;">
    <tr>
        {{-- Earnings --}}
        <td style="width: 50%; padding-right: 8px;">
            <div class="section">
                <div class="section-title">Earnings</div>
                <table class="lines">
                    @foreach ($earnings as $line)
                        <tr><td>{{ $line['name'] }}</td><td class="amt">{{ $m($line['amount']) }}</td></tr>
                    @endforeach
                    @if ($unpaidLeave['amount'] > 0)
                        <tr>
                            <td>Less: unpaid leave ({{ rtrim(rtrim(number_format($unpaidLeave['days'], 2), '0'), '.') }} day{{ $unpaidLeave['days'] == 1 ? '' : 's' }})</td>
                            <td class="amt neg">({{ $m($unpaidLeave['amount']) }})</td>
                        </tr>
                    @endif
                    <tr class="sub"><td>Gross pay</td><td class="amt">{{ $m($gross) }}</td></tr>
                </table>
            </div>

            {{-- How PAYE was worked out --}}
            <div class="section">
                <div class="section-title">How your tax (PAYE) was calculated</div>
                <table class="lines">
                    <tr><td>Gross pay</td><td class="amt">{{ $m($tax['gross']) }}</td></tr>
                    <tr><td>Less: NSSF</td><td class="amt neg">({{ $m($tax['nssf']) }})</td></tr>
                    <tr><td>Less: SHIF</td><td class="amt neg">({{ $m($tax['shif']) }})</td></tr>
                    <tr><td>Less: Housing Levy</td><td class="amt neg">({{ $m($tax['ahl']) }})</td></tr>
                    <tr class="sub"><td>Taxable pay</td><td class="amt">{{ $m($tax['taxable']) }}</td></tr>
                    <tr><td>Tax on taxable pay (KRA bands)</td><td class="amt">{{ $m($tax['payeBeforeRelief']) }}</td></tr>
                    <tr><td>Less: personal relief</td><td class="amt neg">({{ $m($tax['personalRelief']) }})</td></tr>
                    @if ($tax['insuranceRelief'] > 0)
                        <tr><td>Less: insurance relief</td><td class="amt neg">({{ $m($tax['insuranceRelief']) }})</td></tr>
                    @endif
                    <tr class="sub"><td>PAYE payable</td><td class="amt">{{ $m($tax['paye']) }}</td></tr>
                </table>
            </div>
        </td>

        {{-- Deductions --}}
        <td style="width: 50%;">
            <div class="section">
                <div class="section-title">Deductions</div>
                <table class="lines">
                    <tr class="group"><td colspan="2">Statutory</td></tr>
                    @foreach ($statutory as $line)
                        <tr><td>{{ $line['name'] }}</td><td class="amt">{{ $m($line['amount']) }}</td></tr>
                    @endforeach
                    <tr class="sub"><td>Total statutory</td><td class="amt">{{ $m($statutoryTotal) }}</td></tr>

                    <tr class="group"><td colspan="2">Other deductions</td></tr>
                    @forelse ($other as $line)
                        <tr><td>{{ $line['name'] }}</td><td class="amt">{{ $m($line['amount']) }}</td></tr>
                    @empty
                        <tr><td class="muted">None this month</td><td class="amt muted">0.00</td></tr>
                    @endforelse
                    <tr class="sub"><td>Total other</td><td class="amt">{{ $m($otherTotal) }}</td></tr>

                    <tr class="sub" style="font-size: 11px;"><td>Total deductions</td><td class="amt">{{ $m($totalDeductions) }}</td></tr>
                </table>
            </div>

            <div class="section">
                <div class="section-title">Paid by employer on your behalf</div>
                <table class="lines">
                    @foreach ($employer as $line)
                        <tr><td>{{ $line['name'] }}</td><td class="amt">{{ $m($line['amount']) }}</td></tr>
                    @endforeach
                </table>
                <div class="muted small" style="padding: 4px 8px;">Not deducted from your pay.</div>
            </div>
        </td>
    </tr>
</table>

{{-- Net pay --}}
<table class="net">
    <tr>
        <td>
            <div class="label">Net pay</div>
            <div class="muted small">Gross {{ $m($gross) }} − deductions {{ $m($totalDeductions) }}</div>
        </td>
        <td class="value">KES {{ $m($net) }}</td>
    </tr>
</table>

{{-- Footer --}}
<table class="footer">
    <tr>
        <td class="small muted" style="text-align: right;">
            Computer-generated payslip · {{ $company['name'] }} · {{ $generatedAt->format('j M Y, H:i') }}<br>
            Queries: contact HR. Keep this document confidential.
        </td>
    </tr>
</table>

</body>
</html>
