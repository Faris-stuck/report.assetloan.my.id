<?php

namespace Database\Seeders;

use App\Models\DamageCategory;
use App\Models\HomeroomClass;
use App\Models\SchoolClass;
use App\Models\StaffUnit;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\ViolationType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $super = User::updateOrCreate(['email' => 'admin@laporin.local'], ['name' => 'SuperAdmin LAPORIN', 'password' => Hash::make('password123'), 'role' => 'superadmin', 'is_active' => true]);
        $kesiswaan = User::updateOrCreate(['email' => 'kesiswaan@laporin.local'], ['name' => 'Petugas Kesiswaan', 'password' => Hash::make('password123'), 'role' => 'kesiswaan', 'is_active' => true]);
        $sarpras = User::updateOrCreate(['email' => 'sarpras@laporin.local'], ['name' => 'Petugas Sarpras', 'password' => Hash::make('password123'), 'role' => 'sarpras', 'is_active' => true]);
        $wali = User::updateOrCreate(['email' => 'wali@laporin.local'], ['name' => 'Wali Kelas Sample', 'password' => Hash::make('password123'), 'role' => 'wali_kelas', 'is_active' => true]);

        $this->call(TarunaBangsaClassSeeder::class);
        $class = SchoolClass::firstOrCreate(['class_name' => 'Kelas 10 RPL 1', 'academic_year' => '2026/2027'], ['grade_level' => '10', 'major' => 'RPL', 'room_name' => 'Ruang 10 RPL 1', 'is_active' => true]);
        $subject = Subject::firstOrCreate(['subject_name' => 'Informatika'], ['is_active' => true]);
        StaffUnit::firstOrCreate(['unit_name' => 'Tata Usaha'], ['is_active' => true]);
        HomeroomClass::firstOrCreate(['homeroom_user_id' => $wali->id, 'class_id' => $class->id, 'academic_year' => '2026/2027']);
        Student::updateOrCreate(['nis' => '242510311'], ['user_id' => null, 'name' => 'Siswa Sample', 'class_id' => $class->id, 'parent_phone' => '', 'point' => 100]);
        foreach ([['Terlambat', 5, 'Datang terlambat ke sekolah/kelas.'], ['Bullying Fisik', 30, 'Melakukan kekerasan/perundungan fisik.'], ['Merokok', 25, 'Merokok atau membawa rokok di area sekolah.']] as $v) {
            ViolationType::firstOrCreate(['violation_name' => $v[0]], ['point_reduction' => $v[1], 'description' => $v[2], 'created_by' => $super->id, 'is_active' => true]);
        }
        foreach (['Elektronik', 'Furnitur', 'Sanitasi', 'Bangunan', 'Listrik', 'Lainnya'] as $cat) {
            DamageCategory::firstOrCreate(['category_name' => $cat], ['is_active' => true]);
        }
    }
}
