<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $adminDomain = config('panels.admin.domain');
        // При заданном поддомене панели getUrl() часто даёт путь относительно APP_URL;
        // запрос без хоста админки попадает на витрину (200) вместо Filament.
        $url = filled($adminDomain)
            ? 'https://'.$adminDomain.'/'
            : Filament::getPanel('admin')->getUrl();

        $this->get($url)->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_guest_can_open_admin_login_page(): void
    {
        $this->get(route('filament.admin.auth.login'))->assertOk();
    }
}
