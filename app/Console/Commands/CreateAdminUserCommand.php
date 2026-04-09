<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * @deprecated Используйте {@see CreateStaffCommand} (`php artisan staff:create`).
 */
class CreateAdminUserCommand extends Command
{
    protected $signature = 'user:create-admin {email?} {--name=} {--password=}';

    protected $description = '[Устарело] Создание админа перенесено в staff:create';

    public function handle(): int
    {
        $this->warn('Команда user:create-admin устарела. Персонал теперь в таблице staff.');
        $this->line('Пример: php artisan staff:create your@email.com --role=admin');

        return self::FAILURE;
    }
}
