<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\QrCode;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QR;

class QRCodeController extends Controller
{
    public function index(): View
    {
        $query = QrCode::query();
        
        // Search by name
        if ($search = request('search')) {
            $query->where('qr_name', 'like', "%{$search}%");
        }
        
        // Filter by type
        if ($type = request('type')) {
            if (in_array($type, ['general', 'class', 'location'], true)) {
                $query->where('qr_type', $type);
            }
        }
        
        // Filter by status
        if ($status = request('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return view('admin.qrcodes.index', [
            'qrs' => $query->latest()->paginate(20),
            'classes' => SchoolClass::where('is_active', true)->orderBy('class_name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('location_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'qr_name' => ['required', 'string', 'max:150', 'regex:/^[\pL\pN ._\-()]+$/u'],
            'qr_type' => ['required', Rule::in(['general', 'class', 'location'])],
            'class_id' => ['required_if:qr_type,class', 'prohibited_unless:qr_type,class', Rule::exists('classes', 'id')->where('is_active', true)],
            'location_id' => ['required_if:qr_type,location', 'prohibited_unless:qr_type,location', Rule::exists('locations', 'id')->where('is_active', true)],
        ], [
            'class_id.required_if' => 'Kelas wajib dipilih untuk QR tipe kelas.',
            'class_id.prohibited_unless' => 'Kelas hanya boleh diisi untuk QR tipe kelas.',
            'location_id.required_if' => 'Lokasi wajib dipilih untuk QR tipe lokasi.',
            'location_id.prohibited_unless' => 'Lokasi hanya boleh diisi untuk QR tipe lokasi.',
            'qr_name.regex' => 'Nama QR hanya boleh berisi huruf, angka, spasi, titik, underscore, strip, dan tanda kurung.',
        ]);

        $identifier = Str::slug($data['qr_name']).'-'.Str::lower(Str::random(6));
        $target = route('public.report.qr', $identifier);

        QrCode::create([
            'qr_identifier' => $identifier,
            'qr_name' => $data['qr_name'],
            'qr_type' => $data['qr_type'],
            'class_id' => $data['qr_type'] === 'class' ? $data['class_id'] : null,
            'location_id' => $data['qr_type'] === 'location' ? $data['location_id'] : null,
            'target_url' => $target,
            'created_by' => $request->user()->id,
            'is_active' => true,
        ]);

        return back()->with('status', 'QR dibuat.');
    }

    public function download(QrCode $qrCode)
    {
        $png = QR::format('png')->size(300)->margin(2)->generate($qrCode->target_url);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$qrCode->qr_identifier.'.png"',
        ]);
    }

    public function deactivate(QrCode $qrCode): RedirectResponse
    {
        $qrCode->update(['is_active' => false]);

        return back()->with('status', 'QR dinonaktifkan.');
    }
}
