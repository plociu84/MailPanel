<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserManagementRequest;
use App\Services\SystemUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UserManagementController extends Controller
{
    public function __construct(
        private SystemUserService $systemUser
    ) {}

    public function users()
    {
        try {
            $users = Cache::remember('system_users', 300, fn() => $this->systemUser->getUsers());
            $groups = Cache::remember('system_groups', 300, fn() => $this->systemUser->getGroups());
        } catch (\Throwable $e) {
            $users = [];
            $groups = [];
            session()->flash('error', 'Błąd pobierania danych z serwera: ' . $e->getMessage());
        }

        return view('users.index', compact('users', 'groups'));
    }

    public function lockUser(string $username)
    {
        try {
            $this->systemUser->lockUser($username);
            Cache::forget('system_users');
            return redirect()->back()->with('success', "Konto {$username} zablokowane.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Błąd blokowania konta: {$e->getMessage()}");
        }
    }

    public function unlockUser(string $username)
    {
        try {
            $this->systemUser->unlockUser($username);
            Cache::forget('system_users');
            return redirect()->back()->with('success', "Konto {$username} odblokowane.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Błąd odblokowywania konta: {$e->getMessage()}");
        }
    }

    public function setExpire(UserManagementRequest $request, string $username)
    {
        try {
            $date = $request->input('expire_date');
            $this->systemUser->setExpire($username, $date);
            Cache::forget('system_users');
            return redirect()->back()->with('success', "Data wygaśnięcia dla {$username} ustawiona na {$date}.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Błąd ustawiania daty wygaśnięcia: {$e->getMessage()}");
        }
    }

    public function groups()
    {
        try {
            $groups = Cache::remember('system_groups', 300, fn() => $this->systemUser->getGroups());
        } catch (\Throwable $e) {
            $groups = [];
            session()->flash('error', 'Błąd pobierania grup: ' . $e->getMessage());
        }

        return view('groups.index', compact('groups'));
    }

    public function addGroup(UserManagementRequest $request)
    {
        try {
            $name = $request->input('name');
            $this->systemUser->addGroup($name);
            Cache::forget('system_groups');
            return redirect()->back()->with('success', "Grupa {$name} dodana.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Błąd dodawania grupy: {$e->getMessage()}");
        }
    }

    public function deleteGroup(string $name)
    {
        try {
            $this->systemUser->deleteGroup($name);
            Cache::forget('system_groups');
            return redirect()->back()->with('success', "Grupa {$name} usunięta.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Błąd usuwania grupy: {$e->getMessage()}");
        }
    }

    public function renameGroup(UserManagementRequest $request, string $oldName)
    {
        try {
            $newName = $request->input('new_name');
            $this->systemUser->renameGroup($oldName, $newName);
            Cache::forget('system_groups');
            return redirect()->back()->with('success', "Grupa {$oldName} zmieniona na {$newName}.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Błąd zmiany nazwy grupy: {$e->getMessage()}");
        }
    }

    public function addUserToGroup(UserManagementRequest $request)
    {
        try {
            $username = $request->input('username');
            $group = $request->input('group');
            $this->systemUser->addUserToGroup($username, $group);
            Cache::forget('system_users');
            Cache::forget('system_groups');
            return redirect()->back()->with('success', "Użytkownik {$username} dodany do grupy {$group}.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Błąd dodawania użytkownika do grupy: {$e->getMessage()}");
        }
    }

    public function removeUserFromGroup(UserManagementRequest $request)
    {
        try {
            $username = $request->input('username');
            $group = $request->input('group');
            $this->systemUser->removeUserFromGroup($username, $group);
            Cache::forget('system_users');
            Cache::forget('system_groups');
            return redirect()->back()->with('success', "Użytkownik {$username} usunięty z grupy {$group}.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Błąd usuwania użytkownika z grupy: {$e->getMessage()}");
        }
    }

    public function getUsersJson(): JsonResponse
    {
        try {
            $users = Cache::remember('system_users', 300, fn() => $this->systemUser->getUsers());
            $result = array_map(fn($user) => [
                'username' => $user['username'],
                'uid' => $user['uid'],
                'gecos' => $user['gecos'],
            ], $users);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}