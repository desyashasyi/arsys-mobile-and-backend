<?php

use App\Http\Controllers\Auth\CasController;
use App\Http\Controllers\Specialization\FinalDefenseRoomsPdfController;
use App\Http\Controllers\Specialization\PreDefensePdfController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\ArSys\Research;

// Root → redirect ke login atau halaman sesuai role
Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }
    $user = Auth::user();
    if ($user->hasRole('student')) {
        return redirect()->route('student.home');
    }
    return redirect()->route('home');
});

// Login page (Livewire single-file component)
Route::livewire('/login', 'pages::auth.⚡login')->name('login')->middleware('guest');

// Home / dashboard (staff, admin, dll)
Route::livewire('/home', 'pages::⚡idx')->name('home')->middleware('auth');

// Student routes
Route::middleware('auth')->prefix('student')->name('student.')->group(function () {
    Route::livewire('/home', 'pages::student.home.⚡idx')->name('home');
    Route::livewire('/research', 'pages::student.research.⚡idx')->name('research');
    Route::livewire('/research/create', 'pages::student.research.⚡create')->name('research.create');
    Route::livewire('/research/{id}', 'pages::student.research.⚡view')->name('research.show');
    Route::livewire('/research/{id}/edit', 'pages::student.research.⚡edit')->name('research.edit');
    Route::livewire('/research/{id}/remark', 'pages::student.research.⚡remark')->name('research.remark');
    Route::livewire('/profile', 'pages::student.profile.⚡idx')->name('profile');
    Route::livewire('/events', 'pages::student.events.⚡idx')->name('events');
});

// Staff routes
Route::middleware('auth')->prefix('staff')->name('staff.')->group(function () {
    Route::livewire('/supervise',          'pages::staff.supervise.⚡idx')->name('supervise');
    Route::livewire('/supervise/{id}',     'pages::staff.supervise.⚡view')->name('supervise.detail');
    Route::livewire('/review',             'pages::staff.review.⚡idx')->name('review');
    Route::livewire('/review/{id}',        'pages::staff.review.⚡view')->name('review.detail');
    Route::livewire('/pre-defense',                    'pages::staff.pre-defense.⚡idx')->name('pre-defense');
    Route::livewire('/pre-defense/participant/{id}',   'pages::staff.pre-defense.⚡participant')->name('pre-defense.applicant');
    Route::livewire('/pre-defense/{id}',               'pages::staff.pre-defense.⚡view')->name('pre-defense.event');
    Route::livewire('/final-defense',                  'pages::staff.final-defense.⚡idx')->name('final-defense');
    Route::livewire('/final-defense/{id}',             'pages::staff.final-defense.⚡view')->name('final-defense.event');
});

// Program (Kaprodi) routes
Route::middleware('auth')->prefix('program')->name('program.')->group(function () {
    Route::livewire('/pre-defense',                                        'pages::program.pre-defense.⚡idx')->name('pre-defense');
    Route::livewire('/pre-defense/{id}',                                   'pages::program.pre-defense.⚡view')->name('pre-defense.event');
    Route::livewire('/pre-defense/{eventId}/participant/{applicantId}',    'pages::program.pre-defense.⚡participant')->name('pre-defense.participant');
    Route::livewire('/final-defense',                                      'pages::program.final-defense.⚡idx')->name('final-defense');
    Route::livewire('/final-defense/{id}',                                 'pages::program.final-defense.⚡rooms')->name('final-defense.event');
    Route::livewire('/final-defense/{eventId}/room/{roomId}',              'pages::program.final-defense.⚡room')->name('final-defense.room');
    Route::livewire('/final-defense/{eventId}/participant/{applicantId}',  'pages::program.final-defense.⚡participant')->name('final-defense.participant');
    Route::livewire('/approval',                            'pages::program.approval.⚡idx')->name('approval');
    Route::livewire('/approval/{id}',                       'pages::program.approval.⚡view')->name('approval.detail');
});

// Admin (Program Admin) routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/staff',   'pages::admin.staff.⚡idx')->name('staff');
    Route::livewire('/student', 'pages::admin.student.⚡idx')->name('student');
    Route::livewire('/config',  'pages::admin.config.⚡idx')->name('config');
});

// Super Admin routes
Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::livewire('/staff', 'pages::super-admin.staff.⚡idx')->name('staff');
    Route::livewire('/staff-web', 'pages::super-admin.staff.⚡web')->name('staff.web');
    Route::livewire('/config/program', 'pages::super-admin.config.program.⚡idx')->name('config.program');
    Route::livewire('/student', 'pages::super-admin.student.⚡idx')->name('student');
    Route::livewire('/clients', 'pages::super-admin.clients.⚡idx')->name('clients');
});

// Specialization (KBK) routes
Route::middleware('auth')->prefix('specialization')->name('specialization.')->group(function () {
    Route::livewire('/student/login-as', 'pages::specialization.student.⚡idx')->name('login-as');

    // Research coordinator routes
    Route::livewire('/research/new',        'pages::specialization.research.⚡new')->name('research.new');
    Route::livewire('/research/review',     'pages::specialization.research.⚡review')->name('research.review');
    Route::livewire('/research/in-progress','pages::specialization.research.⚡progress')->name('research.progress');
    Route::livewire('/research/rejected',   'pages::specialization.research.⚡rejected')->name('research.rejected');
    Route::livewire('/research/{id}',       'pages::specialization.research.⚡show')->name('research.show');

    // Defense coordinator routes
    Route::livewire('/defense/events',                                             'pages::specialization.defense.⚡events')->name('defense.events');
    Route::livewire('/defense/pre-defense',                                        'pages::specialization.defense.⚡pre-defense')->name('defense.pre-defense');
    Route::livewire('/defense/pre-defense/{id}',                                   'pages::specialization.defense.⚡pre-defense-event')->name('defense.pre-defense.event');
    Route::livewire('/defense/pre-defense/{eventId}/participant/{participantId}',  'pages::specialization.defense.⚡pre-defense-participant')->name('defense.pre-defense.participant');
    Route::livewire('/defense/final-defense',                                      'pages::specialization.defense.⚡final-defense')->name('defense.final-defense');
    Route::livewire('/defense/final-defense/{id}/rooms',                           'pages::specialization.defense.⚡final-defense-rooms')->name('defense.final-defense.rooms');
    Route::livewire('/defense/final-defense/{eventId}/room/{roomId}',              'pages::specialization.defense.⚡final-defense-room')->name('defense.final-defense.room');
    Route::livewire('/defense/seminar',                                            'pages::specialization.defense.⚡seminar')->name('defense.seminar');

    // PDF exports
    Route::get('/defense/pre-defense/{id}/pdf',    [PreDefensePdfController::class,      'export'])->name('defense.pre-defense.event.pdf');
    Route::get('/defense/final-defense/{id}/pdf',  [FinalDefenseRoomsPdfController::class, 'export'])->name('defense.final-defense.rooms.pdf');
});

// Logout (GET untuk tombol di sidebar, POST untuk form)
Route::match(['GET', 'POST'], '/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout')->middleware('auth');

// CAS SSO routes
Route::prefix('auth/cas')->name('auth.cas.')->group(function () {
    Route::get('/redirect', [CasController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [CasController::class, 'callback'])->name('callback');
    Route::get('/logout', [CasController::class, 'logout'])->name('logout')->middleware('auth');
});

Route::get('/debug-supervise', function () {
    // --- SIMULASI USER LOGIN ---
    $user = User::find(1);
    if (!$user || !$user->staff) {
        dd('DEBUG: User atau relasi Staff tidak ditemukan.');
    }

    // Ambil satu record supervisor dari riset yang aktif
    $firstActiveSupervisorRecord = $user->staff->firstSPVActive()->first();
    if (!$firstActiveSupervisorRecord) {
        dd('DEBUG: Staff ini tidak memiliki riset AKTIF sebagai pembimbing utama.');
    }

    // Ambil objek risetnya
    $research = $firstActiveSupervisorRecord->research;
    if (!$research) {
        dd('DEBUG: Gagal mengakses relasi "research" dari supervisor record.');
    }

    // --- INI ADALAH TES UTAMA ---
    $milestoneIdFromDb = $research->milestone_id;
    $milestoneRelationResult = $research->milestone; // Lazy load the relation

    dd(
        '====== HASIL DEBUG MILESTONE UNTUK RISET AKTIF ======',
        'Research ID: ' . $research->id,
        'Research Title: ' . $research->title,
        '---',
        'Nilai kolom "milestone_id" di database untuk riset ini adalah:',
        $milestoneIdFromDb,
        '---',
        'Hasil dari relasi $research->milestone:',
        $milestoneRelationResult,
        '---',
        'KESIMPULAN: Jika nilai "milestone_id" di atas adalah NULL, maka relasi akan selalu mengembalikan NULL. Ini adalah masalah pada data di tabel `arsys_research`, bukan pada kode.'
    );
});
