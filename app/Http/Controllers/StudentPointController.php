<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class StudentPointController extends Controller
{
    public function pdf(Request $request)
    {
        $student = $request->user()->student()->with(['violations.violationType', 'violations.processor'])->firstOrFail();

        return Pdf::loadView('pdf.student-history', ['student' => $student, 'violations' => $student->violations])->download('riwayat-point-'.$student->nis.'.pdf');
    }
}
