{{-- Aksi baris pengguna: tombol Edit + form Hapus beserta proteksinya.
     Dipakai dua kali (tabel desktop dan kartu mobile) supaya proteksi hapus
     hanya hidup di satu tempat dan kedua breakpoint tidak bisa berbeda.

     $u                     : pengguna pada baris ini
     $activeSuperadminCount : jumlah SuperAdmin yang masih aktif
     $stretch               : true untuk kartu mobile (tombol selebar kartu)
--}}
@php
    $stretch = $stretch ?? false;

    // Alasan penolakan dihitung sekali di sini, lalu dipakai untuk DUA hal:
    // penjaga onsubmit dan tooltip tombol. Sebelumnya kondisinya hanya hidup di
    // dalam onsubmit, jadi tombol Hapus tampak normal sampai operator
    // mengkliknya dan baru dimarahi. Dengan title, alasannya sudah terbaca
    // sebelum diklik.
    $deleteBlockReason = null;

    if (auth()->id() === $u->id) {
        $deleteBlockReason = 'Anda tidak dapat menghapus akun yang sedang digunakan.';
    } elseif ($u->role === 'superadmin' && $u->is_active && $activeSuperadminCount <= 1) {
        $deleteBlockReason = 'SuperAdmin aktif terakhir tidak dapat dihapus.';
    }
@endphp

<button type="button" class="btn btn-sm btn-outline-laporin{{ $stretch ? ' flex-grow-1' : '' }}"
    x-on:click="openEdit(@js([ 'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'phone' => $u->phone, 'is_active' => $u->is_active ]))"
    aria-label="Edit pengguna {{ $u->name }}"
>Edit</button>

<form
    method="POST"
    action="{{ route('admin.users.destroy', $u) }}"
    @if($stretch) class="flex-grow-1" @endif
    {{-- Pesan dirakit lewat @js, bukan addslashes(). addslashes() tidak
         mengescape baris baru, dan kolom name hanya divalidasi
         `string|max:150` — nama yang memuat baris baru membuat literal string
         JS ini tidak sah, handler onsubmit gagal diparse, dan form terkirim
         TANPA dialog konfirmasi sama sekali. --}}
    onsubmit="
        @if($deleteBlockReason)
            alert(@js($deleteBlockReason));
            return false;
        @else
            return confirm(@js('Yakin ingin menghapus pengguna '.$u->name.'?'));
        @endif
    "
>
    @csrf
    @method('DELETE')

    <button
        type="submit"
        class="btn btn-sm btn-outline-danger{{ $stretch ? ' w-100' : '' }}"
        @if($deleteBlockReason)
            title="{{ $deleteBlockReason }}"
            aria-label="Hapus pengguna {{ $u->name }} — tidak tersedia: {{ $deleteBlockReason }}"
        @else
            aria-label="Hapus pengguna {{ $u->name }}"
        @endif
    >
        Hapus
    </button>
</form>
