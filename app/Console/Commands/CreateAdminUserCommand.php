<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdminUserCommand extends Command
{
    protected $signature = 'user:create-admin
        {email : E-mail для входа в Filament}
        {--name= : Отображаемое имя (по умолчанию — часть до @)}
        {--password= : Пароль (лучше не указывать — запросит скрытым вводом)}';

    protected $description = 'Создать или обновить пользователя-администратора (доступ в /admin)';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $name = $this->option('name') ?: Str::before($email, '@');

        $password = $this->option('password');
        if ($password === null || $password === '') {
            $password = $this->secret('Пароль администратора');
            if ($password === '' || $password === null) {
                $this->error('Пароль не может быть пустым.');

                return self::FAILURE;
            }
            $passwordConfirm = $this->secret('Повтор пароля');
            if ($password !== $passwordConfirm) {
                $this->error('Пароли не совпадают.');

                return self::FAILURE;
            }
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $wasCreated = $user->wasRecentlyCreated;
        $this->info($wasCreated
            ? "Создан администратор: {$email}"
            : "Обновлён администратор: {$email} (пароль и is_admin применены).");

        return self::SUCCESS;
    }
}
