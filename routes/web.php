<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KesiswaanController;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SarprasController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicReportController::class, 'create'])->name('public.report');
Route::get('/lapor-pembullyan-smk-taruna-bangsa-bekasi', [SeoController::class, 'bullyingGuide'])->name('seo.bullying-guide');
Route::get('/faq-laporin-smk-taruna-bangsa-bekasi', [SeoController::class, 'faq'])->name('seo.faq');
Route::get('/lapor/{qr?}', [PublicReportController::class, 'create'])->name('public.report.qr');
Route::post('/lapor', [PublicReportController::class, 'store'])->middleware('throttle:public-reports')->name('public.report.store');
Route::get('/lapor-sukses/{report:public_token}', [PublicReportController::class, 'success'])->name('public.report.success');
Route::get('/lacak', [TrackingController::class, 'form'])->name('track.form');
Route::post('/lacak', [TrackingController::class, 'search'])->middleware('throttle:public-tracking')->name('track.search');
Route::post('/lacak/{report}/info', [TrackingController::class, 'addInfo'])->middleware('throttle:public-tracking')->name('track.info');
Route::post('/lacak/{report}/confirm', [TrackingController::class, 'confirmComplete'])->middleware('throttle:public-tracking')->name('track.confirm');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('/reports/{report}/notes', [ReportController::class, 'note'])->name('reports.notes');
    Route::get('/download-attachment/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');

    Route::middleware('role:superadmin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('users.index');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
        Route::get('/qrcodes', [QRCodeController::class, 'index'])->name('qrcodes.index');
        Route::post('/qrcodes', [QRCodeController::class, 'store'])->name('qrcodes.store');
        Route::get('/qrcodes/{qrCode}/download', [QRCodeController::class, 'download'])->name('qrcodes.download');
        Route::post('/qrcodes/{qrCode}/deactivate', [QRCodeController::class, 'deactivate'])->name('qrcodes.deactivate');
        Route::get('/master/{resource}', [AdminController::class, 'master'])->name('master.index');
        Route::post('/master/{resource}', [AdminController::class, 'store'])->name('master.store');
        Route::put('/master/{resource}/{id}', [AdminController::class, 'update'])->name('master.update');
        Route::delete('/master/{resource}/{id}', [AdminController::class, 'destroy'])->name('master.destroy');
    });

    Route::middleware('role:kesiswaan')->prefix('kesiswaan')->name('kesiswaan.')->group(function (): void {
        Route::get('/', [KesiswaanController::class, 'index'])->name('index');
        Route::post('/reports/{report}/process', [KesiswaanController::class, 'process'])->name('process');
        Route::post('/reports/{report}/reject', [KesiswaanController::class, 'reject'])->name('reject');
        Route::post('/reports/{report}/complete', [KesiswaanController::class, 'complete'])->name('complete');
    });

    Route::middleware('role:sarpras')->prefix('sarpras')->name('sarpras.')->group(function () {
        Route::get('/', [SarprasController::class, 'index'])->name('index');
        Route::post('/reports/{report}/process', [SarprasController::class, 'process'])->name('process');
        Route::post('/reports/{report}/reject', [SarprasController::class, 'reject'])->name('reject');
    });

});

require __DIR__.'/auth.php';
