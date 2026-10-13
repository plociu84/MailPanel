<?php

namespace App\Http\Controllers;

use App\Services\MailServerService;
use Illuminate\Http\Request;

class MailUserController extends Controller
{
    public function index(MailServerService $mailService)
    {
        $users = $mailService->getRoundcubeUsers();
        $columns = $mailService->getRoundcubeUsersColumns();

        return view('mail-users', [
            'users' => $users,
            'columns' => $columns
        ]);
    }

    public function store(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'login' => 'required|string|max:128',
            'imie' => 'required|string|max:64',
            'nazwisko' => 'required|string|max:64',
            'email_local_part' => 'required|string|max:64',
            'mail_host' => 'required|string|max:128',
            'password' => 'required|string',
        ]);

        try {
            $outputLog = $mailService->createRoundcubeUser(
                strtolower($validated['login']),
                strtolower($validated['imie']),
                strtolower($validated['nazwisko']),
                strtolower($validated['email_local_part']),
                $validated['mail_host'],
                $validated['password']
            );

            return redirect('/mail-users')->with([
                'success' => 'Kompletne konto pocztowe zostało utworzone.',
                'server_log' => $outputLog
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function update(Request $request, int $id, MailServerService $mailService)
    {
        $validated = $request->validate([
            'login' => 'required|string|max:128',
            'imie' => 'required|string|max:64',
            'nazwisko' => 'required|string|max:64',
            'email_pl' => 'required|email|max:128',
            'email_com_pl' => 'required|email|max:128',
            'mail_host' => 'required|string|max:128',
            'password' => 'nullable|string',
        ]);

        try {
            $outputLog = $mailService->updateRoundcubeUser(
                $id,
                strtolower($validated['login']),
                $validated['imie'],
                $validated['nazwisko'],
                $validated['email_pl'],
                $validated['email_com_pl'],
                $validated['mail_host'],
                $validated['password'] ?? null
            );

            return redirect('/mail-users')->with([
                'success' => 'Dane użytkownika i hasło zostały zaktualizowane.',
                'server_log' => $outputLog
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(int $id, MailServerService $mailService)
    {
        try {
            $outputLog = $mailService->deleteRoundcubeUser($id);
            return redirect('/mail-users')->with([
                'success' => 'Użytkownik został usunięty.',
                'server_log' => $outputLog
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}