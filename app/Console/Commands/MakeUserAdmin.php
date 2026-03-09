<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    protected $signature = 'user:admin {email : Email пользователя}';

    protected $description = 'Назначить пользователя администратором (is_admin = true)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Пользователь с email «{$email}» не найден.");
            return self::FAILURE;
        }

        $user->update(['is_admin' => true]);
        $this->info("Пользователь {$user->name} ({$email}) назначен администратором.");
        return self::SUCCESS;
    }
}
