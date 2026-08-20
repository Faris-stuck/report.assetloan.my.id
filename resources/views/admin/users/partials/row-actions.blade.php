{{-- Aksi baris pengguna: tombol Edit + form Hapus beserta proteksinya.
     Dipakai dua kali (tabel desktop dan kartu mobile) supaya proteksi hapus
     hanya hidup di satu tempat dan kedua breakpoint tidak bisa berbeda.

     $u                     : pengguna pada baris ini
     $activeSuperadminCount : jumlah SuperAdmin yang masih aktif
     $stretch               : true untuk kartu mobile (tombol selebar kartu)
--}}
@php($stretch = $stretch ?? false)

<button type="button" class="btn btn-sm btn-outline-laporin{{ $stretch ? ' flex-grow-1' : '' }}"
    x-on:click="openEdit(@js([ 'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'phone' => $u->phone, 'is_active' => $u->is_active ]))"
    aria-label="Edit pengguna {{ $u->name }}"
>Edit</button>

<form
    method="POST"
    action="{{ route('admin.users.destroy', $u) }}"
    @if($stretch) class="flex-grow-1" @endif
    onsubmit="
        @if(auth()->id() === $u->id)
            alert('Anda tidak dapat menghapus akun yang sedang digunakan.');
            return false;
        @elseif(
            $u->role === 'superadmin'
            && $u->is_active
            && $activeSuperadminCount <= 1
        )
            alert('SuperAdmin aktif terakhir tidak dapat dihapus.');
            return false;
        @else
            return confirm('Yakin ingin menghapus pengguna {{ addslashes($u->name) }}?');
        @endif
    "
>
    @csrf
    @method('DELETE')

    <button
        type="submit"
        class="btn btn-sm btn-outline-danger{{ $stretch ? ' w-100' : '' }}"
        aria-label="Hapus pengguna {{ $u->name }}"
    >
        Hapus
    </button>
</form>
