<?php

namespace App\Http\Controllers;

use App\Services\MailServerService;
use Illuminate\Http\Request;

class InternalRcptController extends Controller
{
    public function index(MailServerService $mailService)
    {
        try {
            $items = $mailService->getInternalRcpts();
        } catch (\Exception $e) {
            $items = [];
            session()->flash('error', 'Błąd pobierania listy blokad zewnętrznych: ' . $e->getMessage());
        }

        return view('internal-rcpts.index', compact('items'));
    }

    public function store(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'recipient' => 'required|string|max:191',
            'action' => 'nullable|string|max:191',
        ]);

        $action = $validated['action'] ?? 'banned_from_world';

        try {
            $log = $mailService->addInternalRcpt($validated['recipient'], $action);
            return redirect()->route('internal-rcpts.index')->with([
                'success' => 'Adres został dodany do listy blokad zewnętrznych.',
                'server_log' => $log
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function update(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'old_recipient' => 'required|string',
            'recipient' => 'required|string|max:191',
            'action' => 'nullable|string|max:191',
        ]);

        $action = $validated['action'] ?? 'banned_from_world';

        try {
            $log = $mailService->updateInternalRcpt($validated['old_recipient'], $validated['recipient'], $action);
            return redirect()->route('internal-rcpts.index')->with([
                'success' => 'Wpis blokady zewnętrznej został zaktualizowany.',
                'server_log' => $log
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'recipient' => 'required|string',
        ]);

        try {
            $log = $mailService->deleteInternalRcpt($validated['recipient']);
            return redirect()->route('internal-rcpts.index')->with([
                'success' => 'Wpis został usunięty z listy blokad.',
                'server_log' => $log
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}