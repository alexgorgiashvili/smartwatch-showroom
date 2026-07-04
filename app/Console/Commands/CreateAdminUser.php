<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--name= : Admin display name}
                            {--email= : Admin email address}';

    protected $description = 'Create an admin user without storing credentials in source control';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Admin name'));
        $email = (string) ($this->option('email') ?: $this->ask('Admin email'));
        $password = $this->secret('Admin password');
        $passwordConfirmation = $this->secret('Confirm admin password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make((string) $password),
            'is_admin' => true,
        ]);

        $this->info('Admin user created successfully.');

        return self::SUCCESS;
    }
}
