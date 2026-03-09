# Следующие шаги после базовой реализации

## Что уже сделано

1. **Критический разбор и архитектура V2** — `docs/ARCHITECTURE_REVIEW_AND_V2.md`
2. **Миграции** — все таблицы (users+phone, companies, company_users, permission tables, categories, brands, products, product_attributes, product_images, vehicles, product_vehicle, product_oem_numbers, product_cross_numbers, warehouses, stocks, price_lists, price_list_items, addresses, carts, cart_items, orders, order_addresses, order_items, payments, shipments, discounts, promo_codes, pages, banners, settings, sellers, seller_products, add seller_id to warehouses/stocks)
3. **Модели** — с fillable, casts, связями, скоупами
4. **Сидеры** — RoleSeeder, OrderStatusSeeder, ShippingMethodSeeder, PaymentMethodSeeder, SettingSeeder, WarehouseSeeder
5. **Базовый layout** — `resources/views/layouts/storefront.blade.php`
6. **Livewire** добавлен в `composer.json`

## Команды перед продолжением

```bash
composer update
php artisan migrate
php artisan db:seed
npm install && npm run build
```

## Что сделать дальше (по этапам)

### Этап 5 (завершить): Storefront layout
- В layout убрать или обернуть в `@if(Route::has('login'))` блоки Вход/Регистрация/Личный кабинет, пока не установлен Breeze.
- Либо установить Breeze: `composer require laravel/breeze --dev` и `php artisan breeze:install livewire`.

### Этап 6: Каталог
- Контроллер или Livewire: список категорий (дерево), список товаров по категории, фильтры (бренд, цена).
- Роуты: `/catalog`, `/catalog/{category:slug}`.
- View: сетка товаров, сайдбар с категориями и фильтрами.

### Этап 7: Карточка товара
- Роут `/product/{product:slug}`, вывод продукта с галереей, ценой, кнопкой «В корзину», совместимостью (Vehicle), OEM/кроссы.

### Этап 8: Корзина
- Сервис корзины (получение/создание по user_id или session_id), добавление/обновление/удаление позиций.
- Livewire: CartIcon (в шапке), CartPage (страница корзины), AddToCartButton.

### Этап 9: Checkout
- Пошаговый checkout: контакты → доставка → подтверждение.
- Создание Order с OrderAddress (снимок), OrderItem (снимок: product_name, sku, price, total, vat_rate, vat_amount).
- Список заказов в ЛК.

### Этап 10: Account
- Роуты под префиксом `account` (middleware auth): дашборд, профиль, адреса, заказы.
- Установить Breeze для login/register или сделать простые формы вручную.

### Этап 11: Admin (сделано)
- В `composer.json` добавлен `filament/filament: ^3.2`. Выполнить: `composer update`.
- Провайдер панели: `App\Providers\Filament\AdminPanelProvider` (путь `/admin`).
- Ресурсы Filament: Category, Brand, Product, Order, User, Page, Banner. Доступ в админку только у пользователей с `is_admin = true`.
- Назначить админа: `php artisan user:admin email@example.com` или в админке: Пользователи → редактировать → включить «Администратор».

## Роли (Spatie)

Таблицы `roles`, `permissions`, `model_has_roles` и т.д. созданы. Для привязки ролей к пользователю установите:

```bash
composer require spatie/laravel-permission
```

После установки удалите миграцию `2025_03_08_000004_create_permission_tables.php` и выполните `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"` и миграции Spatie (структура совместима). Либо оставьте свои таблицы и подключите модели Spatie к ним через конфиг.

## Роуты (текущие в web.php)

Сейчас в `routes/web.php` нужно добавить:
- `GET /` → home (storefront)
- `GET /catalog` → catalog
- `GET /product/{product:slug}` → product.show
- `GET /cart` → cart
- `GET /checkout` → checkout (auth)
- `GET /account/*` → account (auth)
- `GET /page/{slug}` → page.show (для доставки/оплаты/контактов)

После установки Breeze появятся маршруты login, register, logout.
