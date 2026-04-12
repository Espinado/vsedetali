<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Иначе из .env может прийти SESSION_SECURE_COOKIE=true — cookie не уходит между
        // вызовами $this->get()/post() в тестах, гостевая корзина «теряется» при логине.
        if ($this->app->environment('testing')) {
            config(['session.secure' => false]);
        }
    }
}
