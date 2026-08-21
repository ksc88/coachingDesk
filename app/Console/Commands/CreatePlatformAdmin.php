<?php

namespace App\Console\Commands;

use App\Domain\Platform\TenantProvisioner;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:admin
        {--name= : Your name}
        {--email= : Login email}
        {--phone= : Phone}
        {--password= : Password (generated when omitted)}';

    protected $description = 'Create a platform (service provider) admin account that manages all coachings';

    public function handle(TenantProvisioner $provisioner): int
    {
        $name = $this->option('name') ?: $this->ask('Your name');
        $email = strtolower($this->option('email') ?: $this->ask('Login email'));
        $password = $this->option('password');
        $generated = ! $password;

        if ($generated) {
            $password = Str::password(14, symbols: false);
        }

        $validator = Validator::make(compact('name', 'email'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $provisioner->ensureRolesExist();

        $admin = User::query()->create([
            'tenant_id' => null,
            'branch_id' => null,
            'name' => $name,
            'email' => $email,
            'phone' => $this->option('phone'),
            'password' => Hash::make($password),
            'role_label' => 'platform_admin',
            'is_platform_admin' => true,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole('platform_admin');

        $this->newLine();
        $this->info('Platform admin created.');
        $this->components->twoColumnDetail('Login email', $email);
        $this->components->twoColumnDetail('Password', $generated ? $password : '(as provided)');
        $this->components->twoColumnDetail('Console URL', url('/platform/coachings'));

        return self::SUCCESS;
    }
}
