<?php

// Aplikasi berjalan dengan APP_LOCALE=id, tapi tanpa file ini Laravel jatuh ke
// fallback_locale 'en' sehingga tombol halaman memakai teks Inggris.
// AppServiceProvider memanggil Paginator::useBootstrapFive(), dan view paginator
// Bootstrap 5 memakai kunci ini di dua tempat yang dilihat/didengar pengguna:
// teks tombol pada simple paginator dan aria-label pada paginator bernomor.
// Jadi pembaca layar pengguna Indonesia sebelumnya mengumumkan "Previous"/"Next"
// di setiap daftar berhalaman (dashboard, sarpras, kesiswaan, admin, audit log).
return [
    'previous' => 'Sebelumnya',
    'next' => 'Berikutnya',
];
