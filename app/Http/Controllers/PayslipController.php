<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use App\Services\Payroll\PayslipDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function download(Request $request, Payslip $payslip, PayslipDocument $document)
    {
        $this->authorize('view', $payslip);

        activity()->performedOn($payslip)->causedBy($request->user())->event('downloaded')->log('downloaded');

        $pdf = Pdf::loadView('pdf.payslip', $document->build($payslip))->setPaper('a4');

        return $request->boolean('inline')
            ? $pdf->stream($document->filename($payslip))
            : $pdf->download($document->filename($payslip));
    }
}
