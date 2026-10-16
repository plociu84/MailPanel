<?php

namespace App\Http\Controllers;

use App\Services\MailServerService;
use Illuminate\Http\Request;

class InternalRcptController extends Controller
{
    /**
     * Dozwolone akcje wpisu pliku /etc/postfix/internal_rcpts_only.
     *
     * Plik jest mapą dostępu Postfixa (main.cf: `check_recipient_access hash:/etc/postfix/internal_rcpts_only`),
     * więc prawa kolumna to nazwa klasy restrykcji z `smtpd_restriction_classes`.
     * Panel tworzy wyłącznie wpisy blokujące odbiór poczty ze świata — inne wartości
     * (np. `OK`, `REJECT`) są odrzucane, żeby nie nadpisać blokady przypadkiem.
     */
    private const ACTION_RULES = 'in:banned_from_world';

    public function index(MailServerService $mailService)
    {
        try {
            $items = $mailService->getInternalRcpts();
        } catch (\Exception $e) {
            $items = [];
            session()->flash('error', 'Błąd pobierania listy blokad zewnętrznych: '.$e->getMessage());
        }

        return view('internal-rcpts.index', compact('items'));
    }

    public function store(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'recipient' => 'required|'.self::MAP_KEY_RULES.'|max:191',
            'action' => 'nullable|'.self::ACTION_RULES,
        ]);

        $action = $validated['action'] ?? 'banned_from_world';

        try {
            $log = $mailService->addInternalRcpt($validated['recipient'], $action);

            return redirect()->route('internal-rcpts.index')->with([
                'success' => 'Adres został dodany do listy blokad zewnętrznych.',
                'server_log' => $log,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function update(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'old_recipient' => 'required|'.self::MAP_KEY_RULES,
            'recipient' => 'required|'.self::MAP_KEY_RULES.'|max:191',
            'action' => 'nullable|'.self::ACTION_RULES,
        ]);

        $action = $validated['action'] ?? 'banned_from_world';

        try {
            $log = $mailService->updateInternalRcpt($validated['old_recipient'], $validated['recipient'], $action);

            return redirect()->route('internal-rcpts.index')->with([
                'success' => 'Wpis blokady zewnętrznej został zaktualizowany.',
                'server_log' => $log,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'recipient' => 'required|'.self::MAP_KEY_RULES,
        ]);

        try {
            $log = $mailService->deleteInternalRcpt($validated['recipient']);

            return redirect()->route('internal-rcpts.index')->with([
                'success' => 'Wpis został usunięty z listy blokad.',
                'server_log' => $log,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
