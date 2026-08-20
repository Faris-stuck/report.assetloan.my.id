<?php

chdir('/var/www/html');
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$ids = DB::table('reports')
    ->where(function ($query) {
        $query->where('title', 'like', 'SMOKE TEST - hapus otomatis%')
            ->orWhere('title', 'like', 'SECURITY_SMOKE%');
    })
    ->pluck('id')
    ->all();

foreach (['student_violations', 'report_notes', 'report_status_histories', 'report_attachments', 'damage_details', 'bullying_details'] as $table) {
    if ($ids) {
        DB::table($table)->whereIn('report_id', $ids)->delete();
    }
}
if ($ids) {
    DB::table('reports')->whereIn('id', $ids)->delete();
}

$userIds = DB::table('users')->where('email', 'like', 'security.audit.%@laporin.local')->pluck('id')->all();
if ($userIds) {
    DB::table('students')->whereIn('user_id', $userIds)->delete();
    DB::table('homeroom_classes')->whereIn('homeroom_user_id', $userIds)->delete();
    DB::table('teacher_assignments')->whereIn('teacher_user_id', $userIds)->delete();
    DB::table('users')->whereIn('id', $userIds)->delete();
}

DB::table('teacher_assignments')->whereIn('class_id', DB::table('classes')->where('class_name', 'SECURITY AUDIT CLASS')->pluck('id'))->delete();
DB::table('homeroom_classes')->whereIn('class_id', DB::table('classes')->where('class_name', 'SECURITY AUDIT CLASS')->pluck('id'))->delete();
DB::table('students')->whereIn('class_id', DB::table('classes')->where('class_name', 'SECURITY AUDIT CLASS')->pluck('id'))->delete();
DB::table('classes')->where('class_name', 'SECURITY AUDIT CLASS')->delete();
DB::table('subjects')->where('subject_name', 'SECURITY AUDIT SUBJECT')->delete();

echo 'deleted_reports='.count($ids).' deleted_users='.count($userIds)."\n";
