@extends('layouts.app')
@section('title','Dasbor Siswa')
@section('content')
<div class="page-header">
    <div><span class="page-kicker">Dasbor siswa</span><h1 class="page-title h2 mt-2">Riwayat Poin dan Pembinaan</h1><p class="page-subtitle">Pantau poin dan riwayat pelanggaran yang tercatat oleh Kesiswaan.</p></div>
</div>
@if(!$student)
    <div class="alert alert-warning">Akun belum terhubung ke data siswa.</div>
@else
<div class="row g-4">
    <div class="col-lg-4"><div class="laporin-card p-4 text-center h-100"><div class="point-circle"><div><span>{{ $student->point }}</span><small>POIN</small></div></div><h2 class="h5 mt-3 mb-1">{{ $student->name }}</h2><p class="text-muted">NIS {{ $student->nis }} · {{ $student->class->class_name }}</p><a class="btn btn-laporin" href="{{ route('siswa.point.pdf') }}">Unduh Riwayat (PDF)</a></div></div>
    <div class="col-lg-8"><div class="laporin-card p-4 h-100"><h2 class="h5 fw-bold">Grafik Riwayat Poin</h2><p class="small-muted">Visualisasi penurunan poin dari riwayat pelanggaran.</p><canvas id="pointChart" height="120"></canvas></div></div>
</div>
<div class="laporin-card mt-4">
    <h2 class="h5 fw-bold mb-3">Riwayat Pelanggaran</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Point</th><th>Petugas</th><th>Catatan</th></tr></thead><tbody>@forelse($violations as $v)<tr><td>{{ $v->created_at->format('d/m/Y') }}</td><td>{{ $v->violationType->violation_name }}</td><td><span class="badge text-bg-danger">-{{ $v->point_reduced }}</span></td><td>{{ $v->processor->name }}</td><td>{{ $v->note }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Belum ada pelanggaran.</td></tr>@endforelse</tbody></table></div>
</div>
@push('scripts')<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>const labels=@json($violations->pluck('created_at')->map(fn($d)=>$d->format('d/m'))->values());let point=100;const data=@json($violations->pluck('point_reduced')->values()).map(v=>point-=v);new Chart(document.getElementById('pointChart'),{type:'line',data:{labels:labels,datasets:[{label:'Point',data:data,borderColor:'#00a651',backgroundColor:'rgba(0,166,81,.12)',tension:.3,fill:true}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:0,max:100}}}});</script>@endpush
@endif
@endsection
