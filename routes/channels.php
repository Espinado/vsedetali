<?php

// Файл подключается из bootstrap/app.php → withBroadcasting().
// Сами каналы регистрируются в AppServiceProvider::boot() сразу после
// BroadcastManager::extend(), чтобы авторизация всегда шла в GuestAwarePusherBroadcaster.
