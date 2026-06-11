<?php

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\AiSummaryController;
use App\Http\Controllers\AttestationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InternController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\InternshipRequestController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RhDocumentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.perform');
    // Réinitialisation du mot de passe par e-mail (lien de réinitialisation).
    Route::get('/mot-de-passe-oublie', [AuthController::class, 'showForgotPassword'])->name('forgot.password');
    Route::post('/mot-de-passe-oublie', [AuthController::class, 'forgotPassword'])->name('forgot.password.perform');
    Route::get('/reinitialiser-mot-de-passe/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reinitialiser-mot-de-passe', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── AI Chat assistant (available to all authenticated users) ──
    Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
    Route::get('/chat/models', [ChatController::class, 'models'])->name('chat.models');
    Route::post('/chat/clear', [ChatController::class, 'clearSession'])->name('chat.clear');
    Route::get('/chat/history', [ChatController::class, 'history'])->name('chat.history');
    Route::get('/chat/sessions', [ChatController::class, 'sessions'])->name('chat.sessions');
    Route::put('/chat/rename', [ChatController::class, 'renameSession'])->name('chat.rename');

    Route::middleware('role:Administrateur')->group(function (): void {
        Route::resource('users', UserController::class)->except(['show']);
    });

    Route::middleware('role:Administrateur,Responsable RH,Responsable de competence')->group(function (): void {
        Route::get('/interns', [InternController::class, 'index'])->name('interns.index');
    });

    Route::middleware('role:Administrateur,Responsable de competence')->group(function (): void {
        Route::redirect('/interns/create', '/users/create')->name('interns.create');
        Route::get('/interns/{intern}/edit', [InternController::class, 'edit'])->name('interns.edit');
        Route::match(['put', 'patch'], '/interns/{intern}', [InternController::class, 'update'])->name('interns.update');
        Route::patch('/interns/{intern}/archive', [InternController::class, 'archive'])->name('interns.archive');
        Route::patch('/interns/{intern}/restore', [InternController::class, 'restore'])->name('interns.restore');
        Route::get('/interns/{intern}/convention', [InternshipController::class, 'internConvention'])->name('interns.convention');
    });

    // RC can quickly add a new intern from the interns list
    Route::middleware('role:Responsable de competence')->group(function (): void {
        Route::get('/interns/nouveau-stagiaire', [UserController::class, 'createIntern'])->name('interns.create-intern');
        Route::post('/interns/nouveau-stagiaire', [UserController::class, 'storeIntern'])->name('interns.store-intern');
    });

    Route::middleware('role:Administrateur,Responsable RH,Responsable de competence')->group(function (): void {
        Route::get('/interns/{intern}', [InternController::class, 'show'])->name('interns.show');
    });

    Route::middleware('role:Administrateur,Responsable de competence')->group(function (): void {
        Route::resource('internships', InternshipController::class)->except(['show']);
        Route::patch('/internships/{internship}/status', [InternshipController::class, 'updateStatus'])->name('internships.status');
        Route::get('/internships/{internship}/convention', [InternshipController::class, 'convention'])->name('internships.convention');

        Route::resource('absences', AbsenceController::class)->except(['index', 'show']);
    });

    Route::middleware('role:Administrateur,Responsable RH,Responsable de competence')->group(function (): void {
        Route::get('/absences', [AbsenceController::class, 'index'])->name('absences.index');
    });

    Route::middleware('role:Encadrant')->group(function (): void {
        Route::get('/mes-stagiaires', [InternController::class, 'supervisorIndex'])->name('supervisor.interns');
        Route::get('/mes-stagiaires/{intern}', [InternController::class, 'supervisorShow'])->name('supervisor.interns.show');
        Route::get('/mes-stages', [InternshipController::class, 'supervisorIndex'])->name('supervisor.internships');
        Route::get('/mes-stages/{internship}', [InternshipController::class, 'supervisorShow'])->name('supervisor.internships.show');
        Route::patch('/mes-stages/{internship}/valider-fin', [InternshipController::class, 'supervisorValidate'])->name('supervisor.internships.validate');
        Route::patch('/mes-stages/{internship}/annuler-fin', [InternshipController::class, 'supervisorUndoValidate'])->name('supervisor.internships.undo');
    });

    // AI Copilot — weekly summary (Admin + Encadrant)
    Route::middleware('role:Administrateur,Encadrant')->group(function (): void {
        Route::get('/ai/resume-hebdo/{intern}', [AiSummaryController::class, 'weeklyReport'])->name('ai.weekly-summary');
        Route::post('/ai/resume-hebdo/{intern}/generate', [AiSummaryController::class, 'generate'])->name('ai.weekly-summary.generate');
        Route::get('/ai/rapport/{report}', [AiSummaryController::class, 'savedReport'])->name('ai.weekly-summary.saved');
    });

    Route::middleware('role:Administrateur,Encadrant,Stagiaire')->group(function (): void {
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    });

    // Interns: weekly comment + daily journal
    Route::middleware('role:Stagiaire')->group(function (): void {
        Route::patch('/tasks/{task}/weekly-comment', [TaskController::class, 'updateWeeklyComment'])->name('tasks.weekly-comment');
        Route::get('/journal', [DailyLogController::class, 'index'])->name('daily-log.index');
        Route::post('/journal', [DailyLogController::class, 'store'])->name('daily-log.store');
    });

    Route::middleware('role:Administrateur,Encadrant')->group(function (): void {
        Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    });

    Route::middleware('role:Administrateur,Responsable de competence,Encadrant,Stagiaire')->group(function (): void {
        Route::get('/requests', [InternshipRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/create', [InternshipRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests', [InternshipRequestController::class, 'store'])->name('requests.store');
        Route::patch('/requests/{requestItem}/process', [InternshipRequestController::class, 'process'])->name('requests.process');
        Route::patch('/requests/{requestItem}/validate-stage', [InternshipRequestController::class, 'supervisorValidate'])->name('requests.supervisor-validate');
        Route::patch('/requests/{requestItem}/validate-report', [InternshipRequestController::class, 'rcValidate'])->name('requests.rc-validate');
        Route::delete('/requests/{requestItem}', [InternshipRequestController::class, 'destroy'])->name('requests.destroy');
    });

    Route::middleware('role:Administrateur,Responsable RH')->group(function (): void {
        Route::redirect('/rh/documents', '/rh/attestations');
        Route::redirect('/rh/rapports', '/rh/attestations')->name('rh.reports.index');
        Route::get('/rh/attestations', [RhDocumentController::class, 'attestations'])->name('rh.attestations.index');
        Route::get('/rh/archives', [RhDocumentController::class, 'archives'])->name('rh.archives.index');
        Route::get('/rh/archives/{requestItem}/pdf', [RhDocumentController::class, 'downloadAttestation'])->name('rh.archives.download');
        Route::get('/rh/profil', [RhDocumentController::class, 'profile'])->name('rh.profile');
        Route::post('/rh/profil', [RhDocumentController::class, 'updateProfile'])->name('rh.profile.update');
        Route::patch('/requests/{requestItem}/attestation-ready', [InternshipRequestController::class, 'rhComplete'])->name('requests.rh-complete');
        Route::patch('/requests/{requestItem}/attestation-printed', [InternshipRequestController::class, 'rhMarkPrinted'])->name('requests.rh-printed');
        Route::patch('/requests/{requestItem}/attestation-recovered', [InternshipRequestController::class, 'rhMarkRecovered'])->name('requests.rh-recovered');
        Route::patch('/requests/{requestItem}/archive-attestation', [InternshipRequestController::class, 'rhArchive'])->name('requests.rh-archive');
    });

    Route::middleware('role:Administrateur,Responsable RH,Responsable de competence,Encadrant,Stagiaire')->group(function (): void {
        Route::get('/requests/{requestItem}/report', [InternshipRequestController::class, 'downloadReport'])->name('requests.report');
        Route::get('/rh/assets/{user}/{type}', [RhDocumentController::class, 'asset'])->name('rh.profile.asset');
    });

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/create', [MessageController::class, 'create'])->name('messages.create');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/{message}', [MessageController::class, 'show'])->name('messages.show');
    Route::patch('/messages/{message}/read', [MessageController::class, 'markAsRead'])->name('messages.read');

    Route::get('/attestations/{intern}', [AttestationController::class, 'show'])->name('attestations.show');
});
