<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function show(Report $report): View
    {
        $this->authorize('view', $report);

        return view('reports.show', ['report' => $report->load(['reporterClass', 'relatedClass', 'location', 'bullyingDetail.allegedActorClass', 'damageDetail', 'attachments', 'notes.user', 'histories'])]);
    }

    public function note(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('comment', $report);
        $data = $request->validate(['note' => ['required', 'string', 'max:3000'], 'visibility' => ['required', 'in:internal,reporter_visible']]);
        ReportNote::create(['report_id' => $report->id, 'user_id' => $request->user()->id, 'author_type' => $request->user()->role, 'note' => $data['note'], 'visibility' => $data['visibility']]);

        return back()->with('status', 'Catatan ditambahkan.');
    }
}
