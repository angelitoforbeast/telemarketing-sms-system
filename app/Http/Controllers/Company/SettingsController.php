<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $company = $request->user()->company;
        return view('settings.edit', compact('company'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'cod_fee_rate' => 'required|numeric|min:0|max:1',
            'cod_vat_rate' => 'required|numeric|min:0|max:1',
        ]);

        $company = $request->user()->company;
        $company->update([
            'cod_fee_rate' => $request->cod_fee_rate,
            'cod_vat_rate' => $request->cod_vat_rate,
        ]);

        return redirect()->route('settings.edit')->with('success', 'Settings updated successfully.');
    }
}
