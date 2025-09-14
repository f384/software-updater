<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings', [
            'installer_root' => Setting::get('installer_root', ''),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'installer_root' => 'required|string',
        ]);

        Setting::set('installer_root', $data['installer_root']);

        return redirect()->route('settings.edit')->with('success', 'Settings updated!');
    }
}
