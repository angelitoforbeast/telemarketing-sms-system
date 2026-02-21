<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ImportJob;
use App\Models\Shipment;
use App\Models\SmsSendLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformAdminController extends Controller
{
    /**
     * Platform admin dashboard.
     */
    public function dashboard()
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::active()->count();
        $totalUsers = User::count();
        $totalShipments = Shipment::count();
        $todaySms = SmsSendLog::where('send_date', today())->count();

        $companies = Company::withCount(['users', 'shipments'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('platform.dashboard', compact(
            'totalCompanies',
            'activeCompanies',
            'totalUsers',
            'totalShipments',
            'todaySms',
            'companies'
        ));
    }

    /**
     * List all companies.
     */
    public function companies(Request $request)
    {
        $companies = Company::withCount(['users', 'shipments'])
            ->orderBy('name')
            ->paginate(20);

        return view('platform.companies.index', compact('companies'));
    }

    /**
     * Show a single company detail.
     */
    public function showCompany(Company $company)
    {
        $company->load('users.roles');
        $company->loadCount(['shipments', 'importJobs', 'smsCampaigns']);

        return view('platform.companies.show', compact('company'));
    }

    /**
     * Toggle company status.
     */
    public function toggleCompany(Company $company)
    {
        $newStatus = $company->status === 'active' ? 'suspended' : 'active';
        $company->update(['status' => $newStatus]);

        return back()->with('success', "Company {$newStatus} successfully.");
    }
}
