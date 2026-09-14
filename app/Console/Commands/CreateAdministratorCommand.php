<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class CreateAdministratorCommand extends Command
{
    protected $signature = 'rfa:create-admin
        {--name= : Full name of the administrator}
        {--email= : Sign-in email address}
        {--password= : Password (generated when omitted)}
        {--office= : Office assignment}';

    protected $description = 'Create an administrator account for the RFA Monitoring System.';

    public function handle(): int
    {
        $role = Role::query()
            ->where('slug', Role::ADMINISTRATOR)
            ->first();

        if ($role === null) {
            $this->error(
                'The administrator role is missing. Run: php artisan db:seed --class=RolePermissionSeeder'
            );

            return self::FAILURE;
        }

        $name = $this->option('name')
            ?: $this->ask('Full name');

        $email = $this->option('email')
            ?: $this->ask('Email address');

        $generated = false;

        $password = $this->option('password');

        if (! $password) {
            $password = Str::password(16, true, true, false);

            $generated = true;
        }

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ],
            [
                'name' =>
                    ['required', 'string', 'max:150'],

                'email' =>
                    ['required', 'email', 'max:255', 'unique:users,email'],

                'password' =>
                    ['required', 'string', 'min:10'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,

            'email' => $email,

            'password' => $password,

            'role_id' => $role->id,

            'office' => $this->option('office'),

            'position' => 'System Administrator',

            'status' => User::STATUS_ACTIVE,

            'must_change_password' => $generated,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $this->info("Administrator account created: {$user->email}");

        if ($generated) {
            $this->line('');

            $this->warn("Temporary password: {$password}");

            $this->line(
                'The account must change this password at first sign-in.'
            );
        }

        return self::SUCCESS;
    }
}
