<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
    public function run()
    {
        // Provision Admin User
        $adminUser = env('ADMIN_USER', self::$INSTALLER_ADMIN_USERNAME);
        $adminPass = env('ADMIN_PASSWORD', self::$INSTALLER_ADMIN_PASSWORD);
        $adminEmail = env('ADMIN_EMAIL', self::$INSTALLER_ADMIN_EMAIL);

        $admin = User::where('username', $adminUser)->first() ?: new User();
        $admin->forceFill([
            'username' => $adminUser,
            'password' => Hash::make($adminPass),
            'email' => $adminEmail,
            'firstname' => env('ADMIN_FIRSTNAME', self::$INSTALLER_ADMIN_FIRSTNAME),
            'lastname' => env('ADMIN_LASTNAME', self::$INSTALLER_ADMIN_LASTNAME),
            'is_administrator' => true,
            'status' => 'ACTIVE',
            'language' => 'en',
            'timezone' => 'America/Los_Angeles',
            'datetime_format' => 'm/d/Y H:i',
        ])->save();

        // Provision Regular User (if variable is set)
        if (env('REGULAR_USER')) {
            $regUser = env('REGULAR_USER');
            $user = User::where('username', $regUser)->first() ?: new User();
            $user->forceFill([
                'username' => $regUser,
                'password' => Hash::make(env('REGULAR_PASSWORD', 'password')),
                'email' => env('REGULAR_EMAIL', 'user@example.com'),
                'firstname' => env('REGULAR_FIRSTNAME', 'Regular'),
                'lastname' => env('REGULAR_LASTNAME', 'User'),
                'is_administrator' => false,
                'status' => 'ACTIVE',
                'language' => 'en',
                'timezone' => 'America/Los_Angeles',
                'datetime_format' => 'm/d/Y H:i',
            ])->save();
        }
    }
}
