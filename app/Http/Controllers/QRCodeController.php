<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Services\QRCodePosterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QRCodeController extends Controller
{
    public function index(): View
    {
        $query = QrCode::query();

        if ($search = request('search')) {
            $query->where('qr_name', 'like', "%{$search}%");
        }

        if ($status = request('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return view('admin.qrcodes.index', [
            'qrs' => $query
                ->latest()
                ->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'qr_name' => [
                'required',
                'string',
                'max:150',
                'regex:/^[\pL\pN ._\-()]+$/u',
            ],
        ], [
            'qr_name.required' => 'Nama QR wajib diisi.',
            'qr_name.regex' => 'Nama QR hanya boleh berisi huruf, angka, spasi, titik, underscore, strip, dan tanda kurung.',
        ]);

        $identifier = Str::slug($data['qr_name'])
            .'-'
            .Str::lower(Str::random(6));

        $target = route(
            'public.report.qr',
            $identifier
        );

        QrCode::create([
            'qr_identifier' => $identifier,
            'qr_name' => $data['qr_name'],
            'qr_type' => 'general',
            'class_id' => null,
            'location_id' => null,
            'target_url' => $target,
            'created_by' => $request->user()->id,
            'is_active' => true,
        ]);

        return back()->with(
            'status',
            'QR umum berhasil dibuat.'
        );
    }

    public function download(
        QrCode $qrCode,
        QRCodePosterService $posterService
    ) {
        abort_unless(
            $qrCode->is_active,
            404
        );

        $svg = $posterService->generate(
            $qrCode
        );

        $filename = 'LAPORIN-'
            .$qrCode->qr_identifier
            .'.svg';

        return response(
            $svg,
            200,
            [
                'Content-Type' => 'image/svg+xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }

    public function deactivate(
        QrCode $qrCode
    ): RedirectResponse {
        $qrCode->update([
            'is_active' => false,
        ]);

        return back()->with(
            'status',
            'QR dinonaktifkan.'
        );
    }
}