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

        $admin = User::where('username', $adminUser)->first();
        if (!$admin) {
            $admin = new User();
            $admin->username = $adminUser;
            $admin->password = Hash::make($adminPass);
        } elseif (!Hash::check($adminPass, $admin->password)) {
            // Only update password if it doesn't match the environment variable
            // This prevents session invalidation on every boot if the password is the same
            $admin->password = Hash::make($adminPass);
        }
        
        $admin->forceFill([
            'email' => $adminEmail,
            'firstname' => env('ADMIN_FIRSTNAME', self::$INSTALLER_ADMIN_FIRSTNAME),
            'lastname' => env('ADMIN_LASTNAME', self::$INSTALLER_ADMIN_LASTNAME),
            'is_administrator' => true,
            'status' => 'ACTIVE',
            'language' => 'en',
            'timezone' => 'America/Los_Angeles',
            'datetime_format' => 'm/d/Y H:i',
        ])->save();

        // Provision Regular User for testing
        $regUser = env('REGULAR_USER', 'tester');
        $regPass = env('REGULAR_PASSWORD', 'password');
        $user = User::where('username', $regUser)->first();
        if (!$user) {
            $user = new User();
            $user->username = $regUser;
            $user->password = Hash::make($regPass);
        } elseif (!Hash::check($regPass, $user->password)) {
            $user->password = Hash::make($regPass);
        }

        $user->forceFill([
            'email' => env('REGULAR_EMAIL', 'tester@example.com'),
            'firstname' => env('REGULAR_FIRSTNAME', 'Test'),
            'lastname' => env('REGULAR_LASTNAME', 'User'),
            'is_administrator' => false,
            'status' => 'ACTIVE',
            'language' => 'en',
            'timezone' => 'America/Los_Angeles',
            'datetime_format' => 'm/d/Y H:i',
        ])->save();
    }
}
