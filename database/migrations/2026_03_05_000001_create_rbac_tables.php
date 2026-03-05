<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Platform Role Permissions: Global ceiling set by Platform Admin.
     * Defines the MAXIMUM permissions each role can have across all companies.
     *
     * Company Role Permissions: Per-company overrides set by Company Owner.
     * Must be a subset of the platform ceiling. If not present, defaults to platform setting.
     */
    public function up(): void
    {
        // Global permission ceiling per role (set by Platform Admin)
        Schema::create('platform_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['role_id', 'permission_id'], 'prp_role_permission_unique');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        });

        // Per-company permission overrides per role (set by Company Owner)
        Schema::create('company_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'role_id', 'permission_id'], 'crp_company_role_permission_unique');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_role_permissions');
        Schema::dropIfExists('platform_role_permissions');
    }
};
