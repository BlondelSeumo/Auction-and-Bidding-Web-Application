<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'trial_plan')) {

        Schema::table('users', function (Blueprint $table) {
            $table->integer('trial_plan')->default(0)->after('requested_plan');                                
            $table->date('trial_expire_date')->nullable()->after('trial_plan'); 
        });
    }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
