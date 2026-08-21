<?php

namespace App\Console\Commands;

use App\Domain\Platform\TenantProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create
        {--name= : Coaching organization name}
        {--code= : Short unique code used in receipt numbers, e.g. XYZ}
        {--slug= : Public landing page slug, e.g. xyz-coaching}
        {--owner-name= : Owner full name}
        {--owner-email= : Owner login email}
        {--owner-phone= : Owner phone}
        {--password= : Owner password (generated when omitted)}
        {--branch=Main Campus : Primary branch name}
        {--session= : Academic session label, e.g. 2026-27}';

    protected $description = 'Onboard a new coaching organization with its owner login';

    public function handle(TenantProvisioner $provisioner): int
    {
        $name = $this->option('name') ?: $this->ask('Coaching name');
        $code = strtoupper($this->option('code') ?: $this->ask('Short code for receipts (e.g. XYZ)'));
        $slug = Str::slug($this->option('slug') ?: $this->ask('Landing page slug', Str::slug($name)));
        $ownerName = $this->option('owner-name') ?: $this->ask('Owner name');
        $ownerEmail = strtolower($this->option('owner-email') ?: $this->ask('Owner login email'));

        $validator = Validator::make(compact('name', 'code', 'slug', 'ownerName', 'ownerEmail'), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'min:2', 'max:16', 'alpha_num', Rule::unique('tenants', 'code')],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tenants', 'slug')],
            'ownerName' => ['required', 'string', 'max:255'],
            'ownerEmail' => ['required', 'email', Rule::unique('users', 'email')],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $result = $provisioner->provision([
            'name' => $name,
            'code' => $code,
            'slug' => $slug,
            'owner_name' => $ownerName,
            'owner_email' => $ownerEmail,
            'owner_phone' => $this->option('owner-phone'),
            'password' => $this->option('password'),
            'branch' => $this->option('branch'),
            'session' => $this->option('session'),
        ]);

        $tenant = $result['tenant'];

        $this->newLine();
        $this->info("Coaching '{$tenant->name}' created.");
        $this->components->twoColumnDetail('Login email', $ownerEmail);
        $this->components->twoColumnDetail('Password', $result['generated_password'] ? $result['password'] : '(as provided)');
        $this->components->twoColumnDetail('Login URL', url('/login'));
        $this->components->twoColumnDetail('Landing page', url('/c/'.$tenant->slug));
        $this->components->twoColumnDetail('Receipt prefix', $tenant->code.'/{FY}/{SEQ}');
        $this->components->twoColumnDetail('Razorpay webhook', url('/webhooks/razorpay/'.$tenant->id));
        $this->newLine();
        $this->warn('Share the password over a secure channel and ask the owner to change it after first login.');

        return self::SUCCESS;
    }
}
