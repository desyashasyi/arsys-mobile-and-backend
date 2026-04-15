<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateUserPasswordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update password for all users except deewahyu@upi.edu
        User::where('email', '!=', 'deewahyu@upi.edu')->each(function ($user) {
            $user->password = Hash::make('123456');
            $user->save();
        });

        $this->command->info('All user passwords have been updated to the default password (123456), except for deewahyu@upi.edu.');

        // Ensure deewahyu@upi.edu has the correct password
        $specialUser = User::where('email', 'deewahyu@upi.edu')->first();

        if ($specialUser) {
            $specialUser->password = Hash::make('Ddw9889##');
            $specialUser->save();

            $this->command->info('Password for deewahyu@upi.edu has been set successfully.');
        } else {
            $this->command->warn('User with email deewahyu@upi.edu not found.');
        }
    }
}
