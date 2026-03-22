# Структура Laravel-проекта (vsedetalki)

Структура приложения для интернет-магазина автозапчастей на Laravel 12 + Livewire с разделением на витрину (storefront), личный кабинет (account) и админку (admin).

---

## 1. Models (`app/Models/`)

Модели — представление таблиц БД, связи, скоупы, аксессоры. Вся работа с данными каталога, заказов, пользователей идёт через модели.

```
app/Models/
├── User.php
├── Role.php                    # Spatie (если не используем модель)
├── Permission.php
│
├── Category.php                # Дерево категорий, связь с Product
├── Brand.php
├── Product.php                 # category, brand, attributes, images, vehicles, stocks
├── ProductAttribute.php
├── ProductImage.php
├── Vehicle.php                 # Справочник авто (make, model, generation, year…)
├── ProductVehicle.php          # Pivot: совместимость товар ↔ авто
│
├── Cart.php                    # user_id | session_id, items
├── CartItem.php                # cart_id, product_id, quantity, price
│
├── Order.php                   # user, status, items, address, shipping, payment
├── OrderItem.php               # order_id, product_id, quantity, price [, seller_id]
├── OrderStatus.php             # Справочник статусов
│
├── Address.php                 # user_id | company_id, type (shipping/billing)
├── ShippingMethod.php
├── PaymentMethod.php
│
├── Discount.php                # type, value, category_id, product_id [, company_id]
├── PromoCode.php               # code, discount_id, uses
│
├── Warehouse.php
├── Stock.php                   # product_id, warehouse_id, quantity, reserved
│
├── Page.php                    # Контент: статические страницы
├── Banner.php
├── Setting.php
│
├── Company.php                 # B2B
├── CompanyUser.php             # Pivot: user ↔ company, роль в компании
├── PriceList.php               # B2B прайс-листы
│
├── Seller.php                  # Marketplace
├── SellerProduct.php           # Товар продавца на площадке
├── Commission.php              # Правила комиссии
│
├── Review.php                  # Полиморф: Product | Seller
├── Invoice.php                 # B2B счета
└── Supplier.php                # Опционально
```

**Зачем:** единая точка работы с БД, переиспользование в контроллерах, Livewire, сервисах. Связи и скоупы держим в моделях, а не размазываем по коду.

---

## 2. Livewire-компоненты (`app/Livewire/`)

Компоненты витрины, ЛК и общие — всё, что реагирует на действия пользователя без полной перезагрузки страницы (корзина, фильтры, формы).

Рекомендация: группировать по **зонам** (Storefront / Account / Seller / общие) и по **модулям** (Catalog, Cart, Checkout и т.д.).

```
app/Livewire/
│
├── Storefront/                 # Публичная витрина (гости + покупатели)
│   ├── Catalog/
│   │   ├── CategoryList.php    # Дерево категорий в сайдбаре/меню
│   │   ├── ProductGrid.php     # Сетка товаров + фильтры (категория, бренд, авто, цена)
│   │   ├── ProductCard.php     # Карточка товара в списке (или Blade)
│   │   ├── ProductShow.php    # Страница товара: галерея, цена, «В корзину», совместимость
│   │   └── VehicleSelector.php # Подбор по марке/модели/году (для фильтра)
│   ├── Cart/
│   │   ├── CartIcon.php        # Иконка корзины в шапке (количество, ссылка)
│   │   ├── CartDrawer.php      # Мини-корзина (drawer/slideout)
│   │   └── CartPage.php        # Страница корзины: список, итог, промокод
│   ├── Checkout/
│   │   └── CheckoutWizard.php  # Пошаговое оформление: контакты → доставка → оплата → подтверждение
│   ├── Search/
│   │   └── SearchBar.php       # Поиск с автодополнением (опционально)
│   └── Home/
│       └── HomePage.php        # Главная: баннеры, категории, хиты (если нужна логика)
│
├── Account/                    # Личный кабинет (auth)
│   ├── Dashboard.php           # Сводка: последние заказы, профиль
│   ├── Profile/
│   │   ├── ProfileShow.php     # Просмотр профиля
│   │   └── ProfileEdit.php     # Редактирование: имя, email, телефон, пароль
│   ├── Addresses/
│   │   ├── AddressList.php     # Список адресов доставки
│   │   └── AddressForm.php     # Создание/редактирование адреса
│   ├── Orders/
│   │   ├── OrderList.php       # Список заказов пользователя
│   │   └── OrderShow.php       # Детали одного заказа
│   └── Reviews/                # (этап «Отзывы»)
│       └── ReviewForm.php      # Написать отзыв к товару
│
├── Seller/                     # Кабинет продавца (marketplace, этап 5)
│   ├── Dashboard.php
│   ├── Products/
│   │   ├── SellerProductList.php
│   │   └── SellerProductForm.php
│   ├── Orders/
│   │   └── SellerOrderList.php # Заказы по позициям продавца
│   └── ...
│
├── Shared/                     # Общие компоненты (если не хочется дублировать)
│   ├── AddToCartButton.php     # Кнопка «В корзину» (используется в ProductShow и ProductCard)
│   └── PriceDisplay.php        # Вывод цены с учётом валюты/формата
│
└── Admin/                      # Админка на Filament — компоненты только если кастом
    └── ...                     # Обычно Filament Resources, без отдельных Livewire
```

**Зачем:** витрина и ЛК на Livewire дают быстрый отклик (корзина, фильтры, чекаут) без перезагрузки; разделение по Storefront/Account/Seller упрощает навигацию и middleware (гость/авторизованный/продавец).

---

## 3. Form classes / Requests (`app/Http/Requests/` и опционально `app/Forms/`)

**Form Request** — валидация и авторизация входящих данных для контроллеров и (при необходимости) для Livewire.

```
app/Http/Requests/
├── Auth/
│   ├── LoginRequest.php
│   └── RegisterRequest.php
├── Account/
│   ├── UpdateProfileRequest.php
│   ├── UpdatePasswordRequest.php
│   └── StoreAddressRequest.php
├── Checkout/
│   ├── StoreCheckoutContactRequest.php   # Контакты на шаге чекаута
│   └── StoreCheckoutShippingRequest.php  # Адрес и способ доставки
├── Review/
│   └── StoreReviewRequest.php
└── Admin/                      # Если админка не только Filament
    ├── StoreCategoryRequest.php
    ├── UpdateCategoryRequest.php
    ├── StoreProductRequest.php
    └── UpdateProductRequest.php
```

Опционально можно вынести **Form Objects** (DTO + правила) в отдельную папку, если хочется переиспользовать одни и те же правила и в Request, и в Livewire:

```
app/Forms/                      # Опционально
├── CheckoutContactForm.php     # Свойства + rules()
├── CheckoutShippingForm.php
└── ProfileForm.php
```

**Зачем:** один раз описать правила и сообщения, использовать в контроллерах и Livewire; авторизацию (can edit) можно держать в том же Request.

---

## 4. Services / Actions (`app/Services/`, `app/Actions/`)

**Services** — фасады над доменной логикой (каталог, корзина, заказ, цены, остатки). **Actions** — одна операция в одном классе (CreateOrder, AddToCart, ApplyPromoCode).

```
app/Services/
├── Cart/
│   └── CartService.php         # getOrCreateCart(), addItem(), updateQuantity(), removeItem(), mergeGuestCart()
├── Order/
│   └── OrderService.php        # createFromCart(), recalculateTotals(), changeStatus()
├── Pricing/
│   └── PricingService.php      # getPriceForProduct(), getPriceForUser(), applyDiscounts(), applyPromo()
├── Inventory/
│   └── StockService.php       # availableQuantity(), reserve(), release(), deduct()
├── Checkout/
│   └── CheckoutService.php     # Оркестрация: валидация корзины, расчёт доставки, создание заказа
├── Search/
│   └── CatalogSearchService.php # Поиск товаров по запросу + фильтрам (без Scout)
└── B2B/                        # Этап 4
    └── CompanyService.php      # Привязка пользователя к компании, лимиты, прайсы
```

```
app/Actions/
├── Cart/
│   ├── AddToCartAction.php
│   ├── UpdateCartItemAction.php
│   └── RemoveFromCartAction.php
├── Order/
│   ├── CreateOrderAction.php   # Вызывается из CheckoutService
│   └── SendOrderConfirmationAction.php
├── Checkout/
│   ├── ApplyPromoCodeAction.php
│   └── ValidateCartForCheckoutAction.php
├── Account/
│   └── UpdateDefaultAddressAction.php
└── Catalog/
    └── GetProductsForCategoryAction.php  # Категория + фильтры → query
```

**Зачем:** контроллеры и Livewire остаются тонкими; бизнес-логика (резерв остатков, расчёт скидок, создание заказа) в одном месте, проще тестировать и переиспользовать (например, API или консольные команды).

---

## 5. Policies (`app/Policies/`)

Политики отвечают на вопрос «может ли пользователь выполнить действие над моделью?» (view, create, update, delete). Используются в контроллерах, Livewire и Blade (`@can`).

```
app/Policies/
├── CategoryPolicy.php          # view, create, update, delete (admin/content_manager)
├── ProductPolicy.php
├── OrderPolicy.php             # view — свой заказ или admin/manager; update status — только админ/менеджер
├── AddressPolicy.php           # view, create, update, delete — только свои
├── ReviewPolicy.php            # create — купил товар; update/delete — свой отзыв или модератор
├── CompanyPolicy.php           # B2B: view/update — участник компании или company_admin
├── CompanyUserPolicy.php       # Управление контактами — company_admin
├── SellerPolicy.php            # Marketplace: свой профиль продавца
├── SellerProductPolicy.php
├── PagePolicy.php
├── BannerPolicy.php
└── UserPolicy.php              # Админ: просмотр/редактирование пользователей
```

**Зачем:** централизованная авторизация по моделям; роли (customer, b2b_customer, seller, admin) и привязка к компании учитываются в одном месте, а не в каждом контроллере.

---

## 6. Enums (`app/Enums/`)

Перечисления для статусов и типов — типобезопасность и единый список значений (вместо «магических» строк в коде и БД).

```
app/Enums/
├── OrderStatusEnum.php         # new, confirmed, shipped, delivered, cancelled (или по slug из order_statuses)
├── AddressTypeEnum.php        # shipping, billing
├── DiscountTypeEnum.php       # percent, fixed
├── ProductTypeEnum.php        # part, consumable, accessory
├── SellerStatusEnum.php       # pending, active, suspended, rejected (marketplace)
├── SellerProductStatusEnum.php # draft, active, paused, rejected
├── CompanyUserRoleEnum.php    # viewer, buyer, admin (роль в компании)
├── InvoiceStatusEnum.php     # draft, sent, paid, cancelled (B2B)
└── PaymentMethodCodeEnum.php  # bank_transfer, card, ... (если храним коды в БД)
```

**Зачем:** автодополнение, меньше опечаток, удобно использовать в миграциях (enum в БД или проверка в коде), в формах и в Filament.

---

## 7. Seeders (`database/seeders/`)

Наполнение БД начальными и справочными данными для разработки и продакшена (минимальный набор).

```
database/seeders/
├── DatabaseSeeder.php              # Вызов сидеров по порядку
├── RoleSeeder.php                  # Роли (справочник)
├── OrderStatusSeeder.php           # Статусы заказа
├── ShippingMethodSeeder.php        # Доставка
├── PaymentMethodSeeder.php         # Оплата
├── SettingSeeder.php               # Настройки магазина
├── WarehouseSeeder.php             # Склад по умолчанию
├── GeelyBambooCatalogSeeder.php    # Каталог из database/data/catalog_geely_bamboo.csv
├── PageSeeder.php                  # Статические страницы
└── BannerSeeder.php                # Баннеры
```

Демо-категории, бренды запчастей, товары и «левые» авто из старых сидеров **убраны** — источник каталога один: CSV Geely Бамбук.

**Зачем:** быстрый старт нового окружения; справочники (статусы заказа, способы доставки/оплаты) должны быть до первого заказа.

---

## 8. Маршруты: Storefront, Account, Admin

Разделение по файлам или группам — витрина, личный кабинет и админка явно разведены (префиксы, middleware, имена).

### 8.1 Storefront routes (`routes/storefront.php` или группа в `web.php`)

Публичная витрина: каталог, товар, корзина, чекаут, страницы контента. Доступно гостям и авторизованным.

```php
// routes/storefront.php (подключить в RouteServiceProvider или bootstrap/app.php)

Route::get('/', HomePage::class)->name('home');
Route::get('/catalog/{category:slug?}', ProductGrid::class)->name('catalog');
Route::get('/product/{product:slug}', ProductShow::class)->name('product.show');
Route::get('/cart', CartPage::class)->name('cart');
Route::get('/checkout', CheckoutWizard::class)->name('checkout')->middleware('auth'); // или guest с ограничениями
Route::get('/page/{page:slug}', PageShow::class)->name('page.show');
// поиск: /search?q=...
```

**Зачем:** один префикс/файл для витрины, проще подключать middleware (например, кэш для категорий) и читать карту сайта.

---

### 8.2 Account routes (`routes/account.php`)

Личный кабинет: только для авторизованных (middleware `auth`). Префикс `account` или `my`.

```php
// routes/account.php

Route::middleware(['auth'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/profile', ProfileShow::class)->name('profile.show');
    Route::get('/profile/edit', ProfileEdit::class)->name('profile.edit');
    Route::get('/addresses', AddressList::class)->name('addresses.index');
    Route::get('/addresses/create', AddressForm::class)->name('addresses.create');
    Route::get('/addresses/{address}/edit', AddressForm::class)->name('addresses.edit');
    Route::get('/orders', OrderList::class)->name('orders.index');
    Route::get('/orders/{order}', OrderShow::class)->name('orders.show');
    // B2B: смена компании, выбор компании в заказе — в тех же или отдельных маршрутах
});
```

**Зачем:** все URL ЛК под одним префиксом, единый middleware и именование (`route('account.orders.index')`).

---

### 8.3 Admin routes (`routes/admin.php` или Filament)

Админ-панель: только для ролей admin / content_manager / manager. Обычно вешается Filament с префиксом `admin`.

```php
// Filament регистрирует маршруты сам (например, /admin). Отдельный файл нужен, если есть кастомные.

Route::middleware(['auth', 'role:admin|content_manager|manager'])->prefix('admin')->name('admin.')->group(function () {
    // Если без Filament:
    // Route::get('/', AdminDashboard::class)->name('dashboard');
    // Route::resource('categories', ...);
    // ...
});
```

В случае Filament 3 ресурсы (Category, Product, Order, User…) подключаются через панели и автоматически получают маршруты под `/admin`.

**Зачем:** изоляция админки по URL и middleware; роли проверяются в Filament или в `admin` middleware.

---

## 9. Сводка по папкам

| Папка / зона        | Назначение |
|---------------------|------------|
| **Models**          | Сущности БД, связи, скоупы; основа для всего приложения. |
| **Livewire**        | Интерактивный UI витрины, ЛК и (при необходимости) кабинета продавца без перезагрузки. |
| **Http/Requests**   | Валидация и авторизация входящих данных для форм и API. |
| **Services**        | Доменная логика (корзина, заказ, цены, остатки); переиспользование в контроллерах и Livewire. |
| **Actions**         | Одна операция = один класс; удобно тестировать и вызывать из сервисов/консоли. |
| **Policies**        | Проверка прав доступа по моделям (view/update/delete) в зависимости от роли и владельца. |
| **Enums**           | Статусы и типы без «магических» строк; согласованность с БД и формами. |
| **Seeders**         | Начальные и справочные данные (роли, статусы заказа, доставка, оплата, настройки). |
| **Storefront routes**| Публичные маршруты: главная, каталог, товар, корзина, чекаут, страницы. |
| **Account routes**  | Маршруты ЛК под префиксом `account` с middleware `auth`. |
| **Admin routes**    | Маршруты админки (часто через Filament) с проверкой ролей. |

---

## 10. Подключение маршрутов (Laravel 11+)

В `bootstrap/app.php` или `AppServiceProvider`:

```php
// В bootstrap/app.php с помощью Route::middleware('web')->group(base_path('routes/web.php'));
// и дополнительно:

Route::middleware('web')->group(base_path('routes/storefront.php'));
Route::middleware('web')->group(base_path('routes/account.php'));
Route::middleware('web')->group(base_path('routes/admin.php'));
```

Либо оставить один `routes/web.php` и внутри разнести по группам:

```php
require __DIR__.'/storefront.php';
require __DIR__.'/account.php';
require __DIR__.'/admin.php';
```

После утверждения структуры можно переходить к созданию папок, моделей, сидеров и первых Livewire-компонентов по этапам из ARCHITECTURE.md.
