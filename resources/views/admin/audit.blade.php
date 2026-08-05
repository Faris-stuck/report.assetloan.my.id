@extends('layouts.app')
@section('title','Catatan Audit')
@section('content')
<h1 class="h3 fw-bold">Catatan Audit</h1><div class="card p-3"><table class="table"><thead><tr><th>Waktu</th><th>Aktor</th><th>Aksi</th><th>Model</th></tr></thead><tbody>@foreach($logs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->actor_type }}</td><td>{{ $log->action }}</td><td>{{ $log->model_type }} #{{ $log->model_id }}</td></tr>@endforeach</tbody></table>{{ $logs->links() }}</div>
@endsection
