<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\RoleUser;
use App\Models\User;
use App\Models\Role;

class AddDefaultUserAndRoles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $encryption = new \App\Http\Services\Filter\Encryption();
        $encryptService = new \App\Http\Services\EncryptService($encryption);
        $apikey = $encryptService->apikeyGen();
        // Create roles
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);

        $user = User::create([
            'username' => 'admin',
            'password' => Hash::make('12345678'),
            'apikey' => $apikey,
            // Add other required fields here
        ]);

        // Assign admin role to default user
        RoleUser::create([
            'user_id' => $user->id,
            'role_id' => $adminRole->id,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove default user and roles
        User::where('username', 'admin')->delete();
        Role::whereIn('name', ['admin', 'user'])->delete();
    }
}
