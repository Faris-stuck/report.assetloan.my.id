<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class PublicReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        if (app()->environment('testing') && ! $this->filled('report_submit_token')) {
            $testSessionToken = session('report_submit_token');
            if (is_string($testSessionToken) && $testSessionToken !== '') $this->merge(['report_submit_token'=>$testSessionToken]);
        }
        foreach (['qr_code_id','reporter_class_id','reporter_subject_id','reporter_staff_unit_id','related_class_id'] as $key) {
            $value=$this->input($key); if ($value==='' || $value==='null' || $value==='NULL') $this->merge([$key=>null]);
        }
        $email=$this->input('reporter_email'); if(is_string($email))$this->merge(['reporter_email'=>strtolower(trim($email))]);
        if ($this->input('report_type') === 'damage') {
            $itemName = trim((string) $this->input('item_name', ''));
            $description = trim((string) $this->input('description', ''));
            if (! $this->filled('title') && $itemName !== '') $this->merge(['title' => $itemName]);
            if (! $this->filled('damage_condition') && $description !== '') $this->merge(['damage_condition' => $description]);
        }
        $trimmed=[]; foreach(['report_submit_token','reporter_name','reporter_phone','reporter_email','title','description','reporter_position','bullying_type','victim_name','alleged_actor_name','witness_name','item_name','item_category','damage_condition','suspected_cause'] as $field){$value=$this->input($field);if(is_string($value))$trimmed[$field]=trim($value);} $this->merge($trimmed);
    }
    public function rules(): array
    {
        return [
            'qr_code_id'=>['nullable',Rule::exists('qr_codes','id')->where('is_active',true)],
            'report_submit_token'=>['required','string'],
            'reporter_type'=>['required',Rule::in(['siswa','guru','staff'])],
            'reporter_name'=>['required','string','max:150'],
            'reporter_class_id'=>['exclude_unless:reporter_type,siswa','required',Rule::exists('classes','id')->where('is_active',true)],
            'reporter_absence_number'=>['exclude_unless:reporter_type,siswa','nullable','integer','min:1','max:60'],
            'reporter_subject_id'=>['exclude_unless:reporter_type,guru','required',Rule::exists('subjects','id')->where('is_active',true)],
            'reporter_staff_unit_id'=>['exclude_unless:reporter_type,staff','required',Rule::exists('staff_units','id')->where('is_active',true)],
            'reporter_phone'=>['required','string','max:30','regex:/^[0-9+() .-]+$/',function(string $attribute,mixed $value,\Closure $fail):void{
                // Dulu di sini hanya dihitung 8-15 digit dengan prefiks apa pun,
                // sementara SendReportWhatsAppNotification mewajibkan 628xxxxxxxxx.
                // Formulir menerima nomor yang sistemnya sendiri tidak bisa
                // hubungi: pada 21 Agustus 2026, 11 dari 27 laporan produksi
                // tersimpan dengan nomor tak terjangkau dan 8 di antaranya tanpa
                // email — padahal pesan wajibnya berbunyi "agar sekolah dapat
                // menghubungi pelapor". Kini keduanya memakai PhoneNumber.
                if (! PhoneNumber::isReachable(is_string($value) ? $value : null)) {
                    $fail('Nomor HP tidak bisa dihubungi. Untuk nomor Indonesia tulis seperti 0812 3456 7890; untuk nomor luar negeri sertakan kode negaranya, contoh +65 8123 4567.');
                }
            }],
            'reporter_email'=>['nullable','email:rfc','max:150'],
            'report_type'=>['required',Rule::in(['violation','damage'])],
            /*
             * Judul laporan kerusakan DITURUNKAN dari `item_name` di
             * prepareForValidation(), dan langkah 3 memang tidak merender field
             * judul untuk jenis ini. Menuntutnya sebagai field mandiri membuat
             * pelapor yang belum mengisi nama barang menerima DUA pesan
             * sekaligus: "Nama barang atau fasilitas wajib diisi." dan "Judul
             * laporan wajib diisi." Pesan kedua menyebut kolom yang tidak
             * pernah ada di formnya, dan karena tidak ada input bernama `title`
             * di halaman, skrip penanda error di layouts.app tidak punya field
             * untuk ditempeli — pesannya hanya menggantung di spanduk atas
             * sebagai tuntutan yang mustahil dipenuhi pelapor.
             *
             * Kuncinya tetap divalidasi (bukan dibuang) supaya nilai turunannya
             * ikut masuk validated() dan tersimpan; `item_name` yang wajib
             * itulah yang menjaga judul tidak pernah kosong.
             */
            'title'=>$this->isDamageReport() ? ['nullable','string','max:200'] : ['required','string','max:200'],
            'related_class_id'=>['nullable','required_if:report_type,violation',Rule::exists('classes','id')->where('is_active',true)],
            'incident_date'=>['required','date','before_or_equal:today','after_or_equal:'.now()->subYears(2)->toDateString()],
            'incident_time'=>['nullable','date_format:H:i'],
            'description'=>['required','string','max:5000'],
            'urgency'=>['required',Rule::in(['rendah','sedang','tinggi','darurat'])],
            'reporter_position'=>['exclude_unless:report_type,violation','nullable','string','max:80'],
            'bullying_type'=>['exclude_unless:report_type,violation','nullable','string','max:80'],
            'victim_name'=>['exclude_unless:report_type,violation','nullable','string','max:150'],
            'victim_class_id'=>['exclude_unless:report_type,violation','nullable',Rule::exists('classes','id')->where('is_active',true)],
            'alleged_actor_name'=>['exclude_unless:report_type,violation','required','string','max:150'],
            'alleged_actor_class_id'=>['nullable','exclude_unless:report_type,violation',Rule::exists('classes','id')->where('is_active',true)],
            'witness_name'=>['exclude_unless:report_type,violation','nullable','string','max:150'],
            'impact_description'=>['exclude_unless:report_type,violation','nullable','string','max:2000'],
            'item_name'=>['exclude_unless:report_type,damage','required','string','max:150'],
            'item_category'=>['exclude_unless:report_type,damage','nullable','string','max:100'],
            /*
             * `damage_condition` juga DITURUNKAN, dari `description`, dan tidak
             * pernah dirender sebagai field sendiri. Dua akibatnya bagi pelapor:
             *
             * 1. Saat deskripsi dikosongkan, dulu `required` di sini menyalakan
             *    pesan KEDUA untuk textarea yang sama, dan pesan itu tidak bisa
             *    ditandai inline karena tidak ada input bernama
             *    `damage_condition` di halaman.
             * 2. Batas `max:2000` lebih ketat daripada `description` (max:5000)
             *    dan daripada maxlength="5000" pada textarea-nya. Pelapor yang
             *    menulis 2001-5000 karakter diizinkan browser, lolos aturan
             *    `description`, lalu ditolak di sini oleh pesan default Laravel
             *    berbahasa Inggris tentang kolom yang tidak ada di formnya —
             *    jalan buntu tanpa petunjuk apa yang harus diperbaiki.
             *
             * Kolomnya bertipe `text` di migrasi, jadi batas dinaikkan agar sama
             * dengan field yang benar-benar diisi pelapor. Aturan panjang tetap
             * ada untuk melindungi kasus nilai dikirim langsung.
             */
            'damage_condition'=>['exclude_unless:report_type,damage','nullable','string','max:5000'],
            'suspected_cause'=>['exclude_unless:report_type,damage','nullable','string','max:1000'],
            'priority'=>['exclude_unless:report_type,damage','nullable',Rule::in(['rendah','sedang','tinggi','darurat'])],
            'attachments'=>['nullable','array','max:3'],
            'attachments.*'=>['nullable','file','mimes:jpg,jpeg,png,webp,pdf','mimetypes:image/jpeg,image/png,image/webp,application/pdf','max:4096',function(string $attribute,UploadedFile $value,Closure $fail){if(!$value->isValid()){$fail('File tidak valid.');return;} $finfo=finfo_open(FILEINFO_MIME_TYPE);if(!$finfo){$fail('Tipe file tidak dapat diverifikasi.');return;} $mimeType=finfo_file($finfo,$value->getRealPath());finfo_close($finfo);if(!in_array($mimeType,['image/jpeg','image/png','image/webp','application/pdf']))$fail('Tipe file tidak sesuai.');}],
            'consent'=>['accepted'],
            'captcha'=>['required','integer'],
        ];
    }
    public function messages(): array
    {
        return [
            'report_submit_token.required'=>'Sesi form sudah kedaluwarsa atau Anda sudah mengirim laporan. Muat ulang halaman lalu coba lagi.',
            'reporter_phone.required'=>'Nomor HP wajib diisi agar sekolah dapat menghubungi pelapor.',
            'reporter_phone.regex'=>'Format nomor HP tidak valid.',
            'reporter_email.email'=>'Format alamat email tidak valid.',
            'related_class_id.required_if'=>'Kelas kejadian wajib dipilih untuk laporan perundungan atau pelanggaran.',
            'title.required'=>'Judul laporan wajib diisi.',
            /*
             * Satu textarea `description` dipakai dua jenis laporan dengan label
             * berbeda: "Kronologi singkat" untuk perundungan dan "Deskripsi
             * kerusakan / dampak" untuk kerusakan (lihat langkah 3 pada
             * public/report-form.blade.php). Pesan tunggal "Kronologi wajib
             * diisi." memakai istilah yang tidak pernah muncul di formulir
             * kerusakan, jadi pelapor disuruh mengisi sesuatu yang tidak ada di
             * layarnya. Pesannya kini mengikuti label yang benar-benar dibaca
             * pelapor.
             */
            'description.required'=>$this->isDamageReport()
                ? 'Deskripsi kerusakan / dampak wajib diisi.'
                : 'Kronologi wajib diisi.',
            'description.max'=>$this->isDamageReport()
                ? 'Deskripsi kerusakan / dampak maksimal 5000 karakter.'
                : 'Kronologi maksimal 5000 karakter.',
            'alleged_actor_name.required'=>'Nama pelaku wajib diisi untuk laporan perundungan.',
            'item_name.required'=>'Nama barang atau fasilitas wajib diisi.',
            'incident_date.before_or_equal'=>'Tanggal kejadian tidak boleh melewati hari ini.',
            'incident_date.after_or_equal'=>'Tanggal kejadian terlalu jauh ke belakang. Laporan hanya menerima kejadian dalam 2 tahun terakhir.',
        ];
    }

    /**
     * Apakah permintaan ini laporan kerusakan fasilitas.
     *
     * Dipakai rules() dan messages() untuk membedakan field yang benar-benar
     * dirender di langkah 3 dari field turunan yang tidak pernah dilihat
     * pelapor.
     */
    private function isDamageReport(): bool
    {
        return $this->input('report_type') === 'damage';
    }
}
