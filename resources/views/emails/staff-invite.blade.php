<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; line-height: 1.5; color: #334155; max-width: 640px; margin: 0 auto; padding: 20px; }
        h1 { font-size: 1.25rem; color: #0f172a; }
        .btn { display: inline-block; margin-top: 1rem; padding: 10px 18px; background: #4f46e5; color: #fff !important; text-decoration: none; border-radius: 6px; font-weight: 600; }
        .muted { color: #64748b; font-size: 0.875rem; margin-top: 1.5rem; }
    </style>
</head>
<body>
    <h1>Здравствуйте, {{ $staff->name }}</h1>
    <p>Для вас создана учётная запись персонала в панели управления <strong>{{ config('app.name') }}</strong>.</p>
    <p>Нажмите кнопку ниже, чтобы задать пароль для входа (потребуется подтверждение пароля).</p>
    <p><a class="btn" href="{{ $inviteUrl }}">Задать пароль</a></p>
    <p class="muted">Ссылка действительна до {{ $staff->invite_expires_at?->timezone(config('app.timezone'))?->format('d.m.Y H:i') ?? '—' }}. Если вы не ждали это письмо, просто проигнорируйте его.</p>
</body>
</html>
