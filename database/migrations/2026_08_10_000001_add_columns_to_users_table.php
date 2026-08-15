<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users','phone'))               $t->string('phone')->nullable()->after('email');
            if (!Schema::hasColumn('users','role'))                $t->string('role')->default('participant')->after('phone');
            if (!Schema::hasColumn('users','location'))            $t->string('location')->nullable()->after('role');
            if (!Schema::hasColumn('users','verification_status')) $t->string('verification_status')->default('unverified')->after('location');
            if (!Schema::hasColumn('users','avatar_path'))         $t->string('avatar_path')->nullable();
            // organization fields (org = user with role=organization)
            if (!Schema::hasColumn('users','organization_name'))         $t->string('organization_name')->nullable();
            if (!Schema::hasColumn('users','organization_type'))         $t->string('organization_type')->nullable();
            if (!Schema::hasColumn('users','registration_documents_path')) $t->string('registration_documents_path')->nullable();
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            foreach (['phone','role','location','verification_status','avatar_path','organization_name','organization_type','registration_documents_path'] as $c)
                if (Schema::hasColumn('users',$c)) $t->dropColumn($c);
        });
    }
};
