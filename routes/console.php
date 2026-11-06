<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Laravel\Prompts\password;

Artisan::command('darts:admin', function () {
    $user = User::firstOrNew(['username' => 'admin']);
    $user->name ??= 'Admin';
    $user->password = password('Password for the admin login (min '.config('league.password_min').' characters)', required: true, validate: ['password' => 'min:'.config('league.password_min')]);
    $user->save();

    $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated').' the admin login (username: admin)');
})->purpose('Create the admin login, or reset its password');
