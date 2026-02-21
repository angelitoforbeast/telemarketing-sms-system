<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistrationService
{
    /**
     * Register a new company and its owner user in a single transaction.
     */
    public function registerCompanyWithOwner(array $companyData, array $userData): array
    {
        return DB::transaction(function () use ($companyData, $userData) {
            $company = Company::create([
                'name' => $companyData['company_name'],
                'contact_email' => $userData['email'],
            ]);

            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'company_id' => $company->id,
                'is_active' => true,
            ]);

            $user->assignRole('Company Owner');

            return compact('company', 'user');
        });
    }

    /**
     * Invite a new user to an existing company.
     */
    public function inviteUserToCompany(int $companyId, array $userData, string $roleName): User
    {
        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'company_id' => $companyId,
            'is_active' => true,
        ]);

        $user->assignRole($roleName);

        return $user;
    }
}
