<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use App\Http\Services\EncryptService;

class CreatePartnerAccountsCommand extends Command
{
    protected $signature = 'accounts:create-partners {count=100}';

    protected $description = 'Create partner accounts';
    private $encryptService;

    public function __construct(EncryptService $encryptService)
    {
        parent::__construct();
        $this->encryptService = $encryptService;
    }

    public function handle()
    {
        $count = $this->argument('count');

        for ($partnerId = 1; $partnerId <= $count; $partnerId++) {
            $username = 'collaborator' . $partnerId;
            $email = $username . '@email.com';
            $password = '12345678';

            // Tạo tài khoản người dùng
            $apikey = $this->encryptService->apikeyGen();

            $user = User::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($password),
                'apikey' => $apikey,
                'partner_id' => $partnerId,
            ]);

            $user->roles()->attach(2); // Gán quyền partner (role_id = 2)

            if ($user) {
                new Registered($user);
            }
        }

        $this->info("Created {$count} partner accounts successfully.");
    }
}
