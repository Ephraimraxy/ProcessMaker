<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;
use ProcessMaker\Models\Group;
use ProcessMaker\Models\GroupMember;
use ProcessMaker\Models\User;

class UserSeeder extends Seeder
{
    public static $INSTALLER_ADMIN_USERNAME = 'admin';

    public static $INSTALLER_ADMIN_PASSWORD = 'admin';

    public static $INSTALLER_ADMIN_EMAIL = 'admin@processmaker.com';

    public static $INSTALLER_ADMIN_FIRSTNAME = 'Admin';

    public static $INSTALLER_ADMIN_LASTNAME = 'User';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(ClientRepository $clients)
    {
        // Provision Admin User
        $adminUser = env('ADMIN_USER', self::$INSTALLER_ADMIN_USERNAME);
        $adminPass = env('ADMIN_PASSWORD', self::$INSTALLER_ADMIN_PASSWORD);
        $adminEmail = env('ADMIN_EMAIL', self::$INSTALLER_ADMIN_EMAIL);

        User::updateOrCreate([
            'username' => $adminUser,
        ], [
            'password' => Hash::make($adminPass),
            'email' => $adminEmail,
            'firstname' => env('ADMIN_FIRSTNAME', self::$INSTALLER_ADMIN_FIRSTNAME),
            'lastname' => env('ADMIN_LASTNAME', self::$INSTALLER_ADMIN_LASTNAME),
            'is_administrator' => true,
            'status' => 'ACTIVE',
            'language' => 'en',
            'timezone' => 'America/Los_Angeles',
            'datetime_format' => 'm/d/Y H:i',
        ]);

        // Provision Regular User (if variable is set)
        if (env('REGULAR_USER')) {
            User::updateOrCreate([
                'username' => env('REGULAR_USER'),
            ], [
                'password' => Hash::make(env('REGULAR_PASSWORD', 'password')),
                'email' => env('REGULAR_EMAIL', 'user@example.com'),
                'firstname' => env('REGULAR_FIRSTNAME', 'Regular'),
                'lastname' => env('REGULAR_LASTNAME', 'User'),
                'is_administrator' => false,
                'status' => 'ACTIVE',
                'language' => 'en',
                'timezone' => 'America/Los_Angeles',
                'datetime_format' => 'm/d/Y H:i',
            ]);
        }

        // Create clients only if they don't exist to avoid duplicate entries or crashes
        if (\DB::table('oauth_clients')->where('personal_access_client', 1)->count() === 0) {
            $clients->createPersonalAccessClient(
                null,
                'PmApi',
                'http://localhost'
            );
        }

        if (\DB::table('oauth_clients')->where('password_client', 1)->count() === 0) {
            $clients->createPasswordGrantClient(
                null, 'Password Grant', 'http://localhost'
            );
        }

        if (\DB::table('oauth_clients')->where('name', 'Swagger UI Auth')->count() === 0) {
            $clients->create(
                null,
                'Swagger UI Auth',
                env('APP_URL', 'http://localhost') . '/api/oauth2-callback'
            );
        }
    }
}
