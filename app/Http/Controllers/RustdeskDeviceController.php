<?php

namespace App\Http\Controllers;

use App\Models\RustdeskDevice;
use Illuminate\Http\Request;

class RustdeskDeviceController extends Controller
{
    public function index()
    {
        $devices = RustdeskDevice::orderBy('user_name')->get();
        return view('rustdesk.index', compact('devices'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_name'       => 'required|string|max:255',
            'computer_number' => 'required|string|max:100',
            'rustdesk_id'     => 'required|string|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);

        RustdeskDevice::create($validated);

        return redirect()->route('rustdesk.index')->with('success', 'Wpis został dodany.');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'user_name'       => 'required|string|max:255',
            'computer_number' => 'required|string|max:100',
            'rustdesk_id'     => 'required|string|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);

        $device = RustdeskDevice::findOrFail($id);
        $device->update($validated);

        return redirect()->route('rustdesk.index')->with('success', 'Wpis został zaktualizowany.');
    }

    public function destroy($id)
    {
        $device = RustdeskDevice::findOrFail($id);
        $device->delete();

        return redirect()->route('rustdesk.index')->with('success', 'Wpis został usunięty.');
    }
}