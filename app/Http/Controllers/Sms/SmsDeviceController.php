<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsDevice;
use Illuminate\Http\Request;

class SmsDeviceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $devices = SmsDevice::forCompany($user->company_id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('sms.devices.index', compact('devices'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'device_name' => 'required|string|max:255',
            'sim_number' => 'nullable|string|max:20',
            'carrier' => 'nullable|string|max:50',
            'daily_limit' => 'required|integer|min:1|max:10000',
            'throttle_delay_seconds' => 'required|integer|min:1|max:300',
        ]);

        $user = $request->user();

        SmsDevice::create([
            'company_id' => $user->company_id,
            'device_name' => $request->device_name,
            'device_token' => SmsDevice::generateToken(),
            'sim_number' => $request->sim_number,
            'carrier' => $request->carrier,
            'daily_limit' => $request->daily_limit,
            'throttle_delay_seconds' => $request->throttle_delay_seconds,
        ]);

        return back()->with('success', 'Device registered successfully.');
    }

    public function update(Request $request, SmsDevice $device)
    {
        $this->authorizeCompany($device);

        $request->validate([
            'device_name' => 'required|string|max:255',
            'sim_number' => 'nullable|string|max:20',
            'carrier' => 'nullable|string|max:50',
            'daily_limit' => 'required|integer|min:1|max:10000',
            'throttle_delay_seconds' => 'required|integer|min:1|max:300',
        ]);

        $device->update($request->only([
            'device_name', 'sim_number', 'carrier', 'daily_limit', 'throttle_delay_seconds',
        ]));

        return back()->with('success', 'Device updated.');
    }

    public function toggle(SmsDevice $device)
    {
        $this->authorizeCompany($device);
        $device->update(['is_active' => !$device->is_active]);
        return back()->with('success', 'Device ' . ($device->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function regenerateToken(SmsDevice $device)
    {
        $this->authorizeCompany($device);
        $device->update(['device_token' => SmsDevice::generateToken()]);
        return back()->with('success', 'Device token regenerated.');
    }

    public function destroy(SmsDevice $device)
    {
        $this->authorizeCompany($device);
        $device->delete();
        return back()->with('success', 'Device removed.');
    }

    protected function authorizeCompany(SmsDevice $device): void
    {
        $user = auth()->user();
        if ($user->company_id && $device->company_id !== $user->company_id) {
            abort(403);
        }
    }
}
