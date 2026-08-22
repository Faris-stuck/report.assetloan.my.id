<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $scope = $this->scopedReports($user);

        $reports = (clone $scope)
            ->with(['relatedClass', 'bullyingDetail', 'damageDetail'])
            ->latest()
            ->paginate(12);

        $userKey = $user->id . '_' . $user->role;
        $stats = \App\Helpers\CacheHelper::remember(
            "laporin:dashboard:stats:{$userKey}",
            300,
            fn () => [
                'total' => (clone $scope)->count(),
                'violation' => (clone $scope)
                    ->where('report_type', 'violation')
                    ->count(),
                'damage' => (clone $scope)
                    ->where('report_type', 'damage')
                    ->count(),
                'pending' => (clone $scope)
                    ->where('status', 'menunggu_verifikasi')
                    ->count(),
                'done' => (clone $scope)
                    ->where('status', 'selesai')
                    ->count(),
            ]
        );

        $chart = \App\Helpers\CacheHelper::remember(
            "laporin:dashboard:chart:{$userKey}",
            300,
            fn () => $this->monthlyChart($scope, $user)
        );

        return view('dashboard.index', [
            'reports' => $reports,
            'stats' => $stats,
            'chart' => $chart,
        ]);
    }

    private function scopedReports(User $user): Builder
    {
        $query = Report::query();

        /*
         * Fail closed.
         *
         * Hanya role yang secara eksplisit dikenal boleh mendapatkan
         * scope laporan.
         */

        if ($user->isRole('superadmin')) {
            return $query;
        }

        if ($user->isRole('kesiswaan')) {
            return $query->where(
                'report_type',
                'violation'
            );
        }

        if ($user->isRole('sarpras')) {
            return $query->where(
                'report_type',
                'damage'
            );
        }

        if ($user->isRole('wali_kelas')) {
            $classIds = $user
                ->homeroomClasses()
                ->pluck('class_id');

            return $query
                ->where('report_type', 'violation')
                ->whereIn('related_class_id', $classIds);
        }

        /*
         * Legacy / unexpected role seperti guru atau siswa tidak boleh
         * mendapatkan semua laporan hanya karena user aktif.
         */
        return $query->whereRaw('1 = 0');
    }

    private function monthlyChart(
        Builder $scope,
        User $user
    ): array {
        $monthNames = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        $labels = [];
        $counts = [];

        $currentMonth = CarbonImmutable::now()
            ->startOfMonth();

        for ($offset = 5; $offset >= 0; $offset--) {
            $start = $currentMonth->subMonths($offset);
            $end = $start->addMonth();

            $labels[] =
                $monthNames[$start->month]
                .' '
                .$start->format('Y');

            $counts[] = (clone $scope)
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $end)
                ->count();
        }

        $titles = [
            'superadmin' => 'Semua Laporan 6 Bulan Terakhir',
            'kesiswaan' => 'Laporan Perundungan 6 Bulan Terakhir',
            'sarpras' => 'Laporan Kerusakan 6 Bulan Terakhir',
            'wali_kelas' => 'Laporan Kelas Terkait 6 Bulan Terakhir',
        ];

        return [
            'title' =>
                $titles[$user->role]
                ?? 'Laporan 6 Bulan Terakhir',

            'labels' => $labels,
            'counts' => $counts,
            'max' => max(1, ...$counts),
        ];
    }
}
