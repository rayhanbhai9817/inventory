<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Support\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\PermissionRegistrar;

/**
 * Interactive, production-safe bootstrap for the first (or an
 * additional) Owner-level administrator account. Deliberately separate
 * from the public /auth/register endpoint: this is meant to be run once
 * over SSH on the production server, so it never needs a working
 * frontend and never sends credentials anywhere but straight into this
 * server's own database.
 *
 * Nothing sensitive is ever echoed, returned, or logged: passwords are
 * read via Command::secret() (masked, never printed) and stored only
 * through User's `password => 'hashed'` cast.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create an Owner-level administrator account for this business';

    public function handle(TenantProvisioningService $provisioner): int
    {
        $this->info('Create Administrator Account');
        $this->line('This creates a user with the highest-level "Owner" role, which has every permission.');
        $this->newLine();

        [$business, $isNewBusiness] = $this->resolveBusiness();
        if ($business === null) {
            return self::FAILURE;
        }

        $name = $this->askRequired('Name');
        $email = $this->askEmail();
        $password = $this->askPassword();

        DB::transaction(function () use ($business, $isNewBusiness, $name, $email, $password, $provisioner) {
            Tenant::set($business->id);
            app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);

            $user = User::create([
                'business_id' => $business->id,
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            if ($isNewBusiness) {
                $provisioner->provision($business, $user);
            } else {
                // An existing business already has its Owner/Manager/Staff
                // role templates from when it was first provisioned.
                $user->assignRole('Owner');
            }
        });

        $this->newLine();
        $this->info('Administrator account created.');
        $this->table(['Field', 'Value'], [
            ['Business', $business->name],
            ['Name', $name],
            ['Email', $email],
            ['Role', 'Owner'],
        ]);
        $this->line('The password was not displayed or logged anywhere — store it securely now.');
        $this->line('Log in at your frontend URL with this email and password.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: ?Business, 1: bool} [business, wasNewlyCreated]
     */
    private function resolveBusiness(): array
    {
        $existing = Business::orderBy('name')->get(['id', 'name', 'slug']);

        if ($existing->isEmpty()) {
            $this->line('No business exists yet — this will be the first one.');
            $name = $this->askRequired('Business name');
            $business = Business::create([
                'name' => $name,
                'slug' => Business::uniqueSlug($name),
            ]);

            return [$business, true];
        }

        $this->line('Existing businesses:');
        $this->table(['#', 'Name', 'Slug'], $existing->map(fn ($b, $i) => [$i + 1, $b->name, $b->slug])->all());

        while (true) {
            $choice = trim((string) $this->ask(
                'Enter a number to add this admin to that business, or type "new" to create a new business'
            ));

            if (strtolower($choice) === 'new') {
                $name = $this->askRequired('New business name');
                $business = Business::create([
                    'name' => $name,
                    'slug' => Business::uniqueSlug($name),
                ]);

                return [$business, true];
            }

            $index = (int) $choice - 1;
            if ($choice !== '' && ctype_digit($choice) && isset($existing[$index])) {
                return [$existing[$index], false];
            }

            $this->error('Enter a valid number from the list above, or "new".');
        }
    }

    private function askRequired(string $label): string
    {
        while (true) {
            $value = trim((string) $this->ask($label));
            if ($value !== '') {
                return $value;
            }
            $this->error("{$label} is required.");
        }
    }

    private function askEmail(): string
    {
        while (true) {
            $email = trim((string) $this->ask('Email'));

            $validator = Validator::make(
                ['email' => $email],
                ['email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')]],
            );

            if ($validator->passes()) {
                return $email;
            }

            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }
        }
    }

    private function askPassword(): string
    {
        while (true) {
            $password = (string) $this->secret('Password');

            $validator = Validator::make(
                ['password' => $password],
                ['password' => ['required', Password::defaults()]],
            );

            if (! $validator->passes()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->error($message);
                }

                continue;
            }

            $confirmation = (string) $this->secret('Confirm Password');

            if (! hash_equals($password, $confirmation)) {
                $this->error('Passwords do not match. Please try again.');

                continue;
            }

            return $password;
        }
    }
}
