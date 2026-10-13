<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Fail2banController;
use App\Http\Controllers\AmavisController;
use App\Http\Controllers\UserManagementController;
use App\Services\MailServerService;
use App\Http\Controllers\RustdeskDeviceController;

// Trasy publiczne (Uwierzytelnianie)
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

// Trasy chronione dla administratora
Route::middleware(['admin.auth'])->group(function () {

    // Wylogowanie — wymaga zalogowania
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ============================================================
    // MODUŁ FAIL2BAN
    // ============================================================
    Route::get('/fail2ban', [Fail2banController::class, 'index'])->name('fail2ban.index');
    Route::post('/fail2ban/unban', [Fail2banController::class, 'unban'])->name('fail2ban.unban');
    Route::post('/fail2ban/refresh', [Fail2banController::class, 'refresh'])->name('fail2ban.refresh');
    Route::get('/fail2ban/status', [Fail2banController::class, 'status'])->name('fail2ban.status');

    // Operacje na konkretnym IP — trasa z regexem, żeby nie łapała innych
    Route::get('/fail2ban/ip/{ip}', [Fail2banController::class, 'showIp'])
        ->where('ip', '[0-9a-fA-F:\.]+')
        ->name('fail2ban.ip.show');

    Route::post('/fail2ban/ip/{ip}/note', [Fail2banController::class, 'saveNote'])
        ->where('ip', '[0-9a-fA-F:\.]+')
        ->name('fail2ban.ip.note');

    Route::post('/fail2ban/ip/{ip}/fortigate', [Fail2banController::class, 'toggleFortigate'])
        ->where('ip', '[0-9a-fA-F:\.]+')
        ->name('fail2ban.ip.fortigate');

    // Feed FortiGate — zarządzanie plikiem public/f2b-ips.txt
    Route::get('/fail2ban/fortigate-feed/preview', [Fail2banController::class, 'previewFortigateFeed'])
        ->name('fail2ban.fortigate-feed.preview');

    Route::post('/fail2ban/fortigate-feed/sync', [Fail2banController::class, 'syncFortigateFeed'])
        ->name('fail2ban.fortigate-feed.sync');

    // ============================================================
    // MODUŁ AMAVIS
    // ============================================================
    Route::get('/amavis', [AmavisController::class, 'index'])->name('amavis.index');

    // ID to nazwa pliku z kwarantanny (np. spam-xxx, virus-xxx) — NIE numer
    Route::post('/amavis/release/{id}', [AmavisController::class, 'release'])
        ->where('id', '[a-zA-Z0-9_\-\.]+')
        ->name('amavis.release');

    Route::post('/amavis/release-all', [AmavisController::class, 'releaseAll'])->name('amavis.release-all');

    Route::get('/amavis/preview/{id}', [AmavisController::class, 'preview'])
        ->where('id', '[a-zA-Z0-9_\-\.]+')
        ->name('amavis.preview');

    Route::delete('/amavis/delete/{id}', [AmavisController::class, 'deleteQuarantine'])
        ->where('id', '[a-zA-Z0-9_\-\.]+')
        ->name('amavis.delete');

    // ============================================================
    // ZARZĄDZANIE UŻYTKOWNIKAMI POCZTY (Roundcube / Cyrus)
    // ============================================================

    // API JSON użytkowników poczty
    Route::get('/mail-users-json', function (MailServerService $mailService) {
        try {
            return response()->json($mailService->getRoundcubeUsers());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // Zarządzanie użytkownikami poczty
    Route::get('/mail-users', [MailUserController::class, 'index'])->name('mail-users.index');
    Route::post('/mail-users', [MailUserController::class, 'store'])->name('mail-users.store');
    Route::put('/mail-users/{id}', [MailUserController::class, 'update'])
        ->whereNumber('id')
        ->name('mail-users.update');
    Route::delete('/mail-users/{id}', [MailUserController::class, 'destroy'])
        ->whereNumber('id')
        ->name('mail-users.destroy');

    // ============================================================
    // ZARZĄDZANIE UŻYTKOWNIKAMI SYSTEMOWYMI (LINUX)
    // ============================================================

    // Użytkownicy systemowi
    Route::get('/system-users', [UserManagementController::class, 'users'])->name('system.users.index');
    Route::post('/system-users/lock/{username}', [UserManagementController::class, 'lockUser'])->name('system.users.lock');
    Route::post('/system-users/unlock/{username}', [UserManagementController::class, 'unlockUser'])->name('system.users.unlock');
    Route::post('/system-users/expire/{username}', [UserManagementController::class, 'setExpire'])->name('system.users.expire');
    Route::get('/system-users-json', [UserManagementController::class, 'getUsersJson'])->name('system.users.json');

    // Grupy systemowe
    Route::get('/system-groups', [UserManagementController::class, 'groups'])->name('system.groups.index');
    Route::post('/system-groups/add', [UserManagementController::class, 'addGroup'])->name('system.groups.add');
    Route::delete('/system-groups/delete/{name}', [UserManagementController::class, 'deleteGroup'])->name('system.groups.delete');
    Route::post('/system-groups/rename/{oldName}', [UserManagementController::class, 'renameGroup'])->name('system.groups.rename');

    // Zarządzanie członkami grup
    Route::post('/system-users/add-to-group', [UserManagementController::class, 'addUserToGroup'])->name('system.users.addToGroup');
    Route::post('/system-users/remove-from-group', [UserManagementController::class, 'removeUserFromGroup'])->name('system.users.removeFromGroup');

    // ============================================================
    // MODUŁ ALIASÓW POCZTOWYCH (valiastable)
    // ============================================================
    Route::get('/aliases', [\App\Http\Controllers\AliasController::class, 'index'])->name('aliases.index');
    Route::post('/aliases', [\App\Http\Controllers\AliasController::class, 'store'])->name('aliases.store');
    Route::put('/aliases', [\App\Http\Controllers\AliasController::class, 'update'])->name('aliases.update');
    Route::delete('/aliases', [\App\Http\Controllers\AliasController::class, 'destroy'])->name('aliases.destroy');

    // ============================================================
    // MODUŁ UPRAWNIEŃ NADAWCÓW (vsendertable)
    // ============================================================
    Route::get('/sender-rules', [\App\Http\Controllers\SenderRuleController::class, 'index'])->name('sender-rules.index');
    Route::post('/sender-rules', [\App\Http\Controllers\SenderRuleController::class, 'store'])->name('sender-rules.store');
    Route::put('/sender-rules', [\App\Http\Controllers\SenderRuleController::class, 'update'])->name('sender-rules.update');
    Route::delete('/sender-rules', [\App\Http\Controllers\SenderRuleController::class, 'destroy'])->name('sender-rules.destroy');

    // ============================================================
    // MODUŁ ODBIORCÓW WEWNĘTRZNYCH (internal_rcpts_only)
    // ============================================================
    Route::get('/internal-rcpts', [\App\Http\Controllers\InternalRcptController::class, 'index'])->name('internal-rcpts.index');
    Route::post('/internal-rcpts', [\App\Http\Controllers\InternalRcptController::class, 'store'])->name('internal-rcpts.store');
    Route::put('/internal-rcpts', [\App\Http\Controllers\InternalRcptController::class, 'update'])->name('internal-rcpts.update');
    Route::delete('/internal-rcpts', [\App\Http\Controllers\InternalRcptController::class, 'destroy'])->name('internal-rcpts.destroy');

    // ============================================================
    // MODUŁ RUSTDESK
    // ============================================================
    Route::resource('rustdesk', RustdeskDeviceController::class)->except(['create', 'edit', 'show']);

});