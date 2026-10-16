<?php

namespace App\Http\Controllers;

use App\Services\MailServerService;
use Illuminate\Http\Request;

class SenderRuleController extends Controller
{
    public function index(MailServerService $mailService)
    {
        try {
            $rules = $mailService->getSenderRules();
        } catch (\Exception $e) {
            $rules = [];
            session()->flash('error', 'Błąd pobierania uprawnień nadawców: '.$e->getMessage());
        }

        return view('sender-rules.index', compact('rules'));
    }

    public function store(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'sender' => 'required|'.self::MAP_KEY_RULES.'|max:191',
            'allowed_logins' => 'required|'.self::MAP_VALUE_RULES.'|max:500',
        ]);

        try {
            $log = $mailService->addSenderRule($validated['sender'], $validated['allowed_logins']);

            return redirect()->route('sender-rules.index')->with([
                'success' => 'Reguła nadawcy została pomyślnie dodana.',
                'server_log' => $log,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function update(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'old_sender' => 'required|'.self::MAP_KEY_RULES,
            'sender' => 'required|'.self::MAP_KEY_RULES.'|max:191',
            'allowed_logins' => 'required|'.self::MAP_VALUE_RULES.'|max:500',
        ]);

        try {
            $log = $mailService->updateSenderRule($validated['old_sender'], $validated['sender'], $validated['allowed_logins']);

            return redirect()->route('sender-rules.index')->with([
                'success' => 'Reguła nadawcy została zaktualizowana.',
                'server_log' => $log,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'sender' => 'required|'.self::MAP_KEY_RULES,
        ]);

        try {
            $log = $mailService->deleteSenderRule($validated['sender']);

            return redirect()->route('sender-rules.index')->with([
                'success' => 'Reguła nadawcy została usunięta.',
                'server_log' => $log,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
