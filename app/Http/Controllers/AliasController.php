<?php

namespace App\Http\Controllers;

use App\Services\MailServerService;
use Illuminate\Http\Request;

class AliasController extends Controller
{
    public function index(MailServerService $mailService)
    {
        try {
            $aliases = $mailService->getAliases();
        } catch (\Exception $e) {
            $aliases = [];
            session()->flash('error', 'Błąd pobierania aliasów: '.$e->getMessage());
        }

        return view('aliases.index', compact('aliases'));
    }

    public function store(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'alias' => 'required|'.self::MAP_KEY_RULES.'|max:191',
            'destination' => 'required|'.self::MAP_VALUE_RULES.'|max:500',
        ]);

        try {
            $log = $mailService->addAlias($validated['alias'], $validated['destination']);

            return redirect()->route('aliases.index')->with([
                'success' => 'Alias został pomyślnie dodany.',
                'server_log' => $log,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function update(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'old_alias' => 'required|'.self::MAP_KEY_RULES,
            'alias' => 'required|'.self::MAP_KEY_RULES.'|max:191',
            'destination' => 'required|'.self::MAP_VALUE_RULES.'|max:500',
        ]);

        try {
            $log = $mailService->updateAlias($validated['old_alias'], $validated['alias'], $validated['destination']);

            return redirect()->route('aliases.index')->with([
                'success' => 'Alias został zaktualizowany.',
                'server_log' => $log,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(Request $request, MailServerService $mailService)
    {
        $validated = $request->validate([
            'alias' => 'required|'.self::MAP_KEY_RULES,
        ]);

        try {
            $log = $mailService->deleteAlias($validated['alias']);

            return redirect()->route('aliases.index')->with([
                'success' => 'Alias został usunięty.',
                'server_log' => $log,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
