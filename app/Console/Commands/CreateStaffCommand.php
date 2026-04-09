<?php

namespace App\Console\Commands;

use App\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CreateStaffCommand extends Command
{
    protected $signature = 'staff:create
        {email : E-mail для входа в Filament}
        {--name= : Отображаемое имя (по умолчанию — часть до @)}
        {--password= : Пароль (лучше не указывать — запросит скрытым вводом)}
        {--role=admin : Роль: admin, manager, accountant, warehouse}';

    protected $description = 'Создать или обновить сотрудника (таблица staff, панель /admin)';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $name = $this->option('name') ?: Str::before($email, '@');

        $password = $this->option('password');
        if ($password === null || $password === '') {
            $password = $this->secret('Пароль');
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

        $roleName = (string) $this->option('role');
        $role = Role::query()->where('name', $roleName)->where('guard_name', 'staff')->first();
        if (! $role) {
            $this->error("Роль «{$roleName}» не найдена (guard staff). Сначала выполните php artisan db:seed --class=RolePermissionSeeder");

            return self::FAILURE;
        }

        $staff = Staff::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        $staff->syncRoles([$role]);

        $wasCreated = $staff->wasRecentlyCreated;
        $this->info($wasCreated
            ? "Создан сотрудник: {$email} (роль: {$roleName})"
            : "Обновлён сотрудник: {$email} (пароль и роль применены).");

        return self::SUCCESS;
    }
}
