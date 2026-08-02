<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function view(User $user, Report $report): bool
    {
        if ($user->isRole('kesiswaan')) {
            return $report->report_type === 'violation';
        }

        if ($user->isRole('sarpras')) {
            return $report->report_type === 'damage';
        }

        if ($user->isRole('wali_kelas')) {
            if ($report->report_type !== 'violation' || $report->related_class_id === null) {
                return false;
            }

            return $user->homeroomClasses()
                ->where('class_id', $report->related_class_id)
                ->exists();
        }

        return false;
    }

    public function comment(User $user, Report $report): bool
    {
        return ($user->isRole('kesiswaan') && $report->report_type === 'violation')
            || ($user->isRole('sarpras') && $report->report_type === 'damage');
    }

    public function updateStatus(User $user, Report $report): bool
    {
        return false;
    }
}
