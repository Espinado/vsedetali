<?php

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff') || ! Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        $map = [];

        User::query()->where('is_admin', true)->orderBy('id')->each(function (User $user) use (&$map): void {
            $id = DB::table('staff')->insertGetId([
                'name' => $user->name,
                'email' => $user->email,
                'password' => $user->getRawOriginal('password'),
                'email_verified_at' => $user->email_verified_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $map[(int) $user->id] = $id;
        });

        if ($map !== []) {
            ChatMessage::query()
                ->where('sender', ChatMessage::SENDER_STAFF)
                ->whereNotNull('user_id')
                ->each(function (ChatMessage $message) use ($map): void {
                    $sid = $map[(int) $message->user_id] ?? null;
                    if ($sid === null) {
                        return;
                    }
                    $message->forceFill([
                        'staff_id' => $sid,
                        'user_id' => null,
                    ])->saveQuietly();
                });
        }

        Schema::table('users', function ($table) {
            $table->dropColumn('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function ($table) {
            $table->boolean('is_admin')->default(false)->after('phone');
        });

        if (! Schema::hasColumn('chat_messages', 'staff_id')) {
            return;
        }

        ChatMessage::query()
            ->where('sender', ChatMessage::SENDER_STAFF)
            ->whereNotNull('staff_id')
            ->each(function (ChatMessage $message): void {
                $row = DB::table('staff')->where('id', $message->staff_id)->first();
                if (! $row) {
                    return;
                }
                $userId = DB::table('users')->where('email', $row->email)->value('id');
                if ($userId) {
                    $message->forceFill([
                        'user_id' => $userId,
                        'staff_id' => null,
                    ])->saveQuietly();
                }
            });

        DB::table('staff')->truncate();
    }
};
