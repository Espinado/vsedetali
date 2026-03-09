# Критический разбор архитектуры и финальная версия (V2)

## Часть 1: Критический разбор (Senior Solution Architect)

### 1. Избыточное усложнение

| Проблема | Где | Рекомендация |
|----------|-----|--------------|
| **ProductPrice как отдельная сущность на старте** | В архитектуре упоминается ProductPrice для «цен по складам/продавцам» | На MVP достаточно `products.price` (базовая розница). Отдельную таблицу цен вводить только для B2B (`price_lists` + позиции) или маркетплейса (`seller_products.price`). Не плодить таблицы «на будущее». |
| **SeoMeta как отдельная полиморфная таблица** | Контент / SEO | Хранить `meta_title`, `meta_description` в `categories`, `products`, `pages`. Отдельную `seo_meta` добавлять только при реальной потребности (много типов сущностей с разными правилами). |
| **Отдельная таблица Rating** | Отзывы и рейтинг | Рейтинг считать агрегатом из `reviews.rating` (AVG, COUNT). Отдельная сущность Rating не нужна. |
| **Slider как отдельная сущность** | Баннеры | Один тип сущности — `banners` с полем `sort`. Слайдер = вывод баннеров по sort. Не вводить отдельно Slider. |
| **Много ролей в MVP** | Роли: content_manager, manager, super_admin… | На старте: `customer`, `admin`. Остальные роли добавить с включением B2B/Filament. |

### 2. Где B2B не учтён

| Проблема | Риск | Решение |
|----------|------|---------|
| **Companies создаются «потом»** | При включении B2B придётся добавлять FK в orders, users, addresses — миграции и код по всему проекту | Таблицы `companies`, `company_users` и FK `orders.company_id`, `addresses.company_id` заложить в **первую** версию миграций. Поля nullable, логика не используется до этапа B2B. |
| **Нет явного места для реквизитов компании** | Юрлицо: ИНН, КПП, адрес, банк, VAT — потом трудно впихнуть | В `companies` сразу: `name`, `inn`, `legal_address`, `vat_number` (nullable), `bank_details` (text, nullable). Расширять при необходимости. |
| **Цены B2B только через PriceList** | Норм, но нет связи «компания → какой прайс по умолчанию» | В `company_users` или `companies` поле `default_price_list_id` (nullable) — на этапе B2B. На старте таблицу `price_lists` и `price_list_items` создать пустыми. |
| **Нет снапшота заказа** | При смене названия товара/цены/адреса старые заказы «плывут» | **Критично:** в `order_items` хранить снимок: `product_name`, `sku`, `price`, `quantity`, `total`, `vat_rate`, `vat_amount`. В заказе — снимок клиента и доставки (см. ниже). |

### 3. Где маркетплейс может сломать текущую модель

| Проблема | Риск | Решение |
|----------|------|---------|
| **Всё завязано на product_id в корзине/заказе** | В маркетплейсе одна позиция = продукт + продавец; если везде только product_id — нельзя развести по продавцам | В `cart_items` и `order_items` сразу заложить `seller_id` (nullable). На MVP всегда null. В `order_items` — снимок, не только FK. |
| **Цена и остаток только в Product и Stock** | У продавца своя цена и свой остаток | Не дублировать логику: на MVP цена в `products`, остаток в `stocks`. Для маркетплейса добавить `seller_products` (seller_id, product_id, price, quantity). Корзина/заказ тогда: product_id + seller_id nullable; цена/остаток берутся из product или seller_product. |
| **Один склад на магазин** | У продавцов свои склады | В `warehouses` сразу поле `seller_id` (nullable). На MVP null. Позже склады продавцов. |
| **Нет источника цены (offer)** | Концепция «один товар — несколько предложений» не заложена | Явную таблицу `offers` не вводить на MVP. Модель: «предложение» = наш товар (product + warehouse) или позже seller_product. В коде считать «источником» product для MVP; при маркетплейсе — seller_product. В БД достаточно product_id + seller_id в order_items. |

### 4. Какие таблицы лучше объединить

| Было | Стало | Причина |
|------|-------|---------|
| **Banner + Slider** | Только `banners` | Слайдер = выборка баннеров по sort. |
| **Rating отдельно** | Не создавать | Рейтинг = агрегат по reviews. |
| **order_addresses vs поля в orders** | Отдельная таблица `order_addresses` | Один заказ может иметь shipping + billing; адрес — структурированные поля (name, city, postcode…). Хранить снимок в `order_addresses` (order_id, type: shipping/billing, все поля). В `orders` можно оставить `shipping_order_address_id` для удобства или брать по order_id + type. |

### 5. Какие поля добавить сразу

| Таблица | Поля | Назначение |
|---------|------|------------|
| **orders** | `customer_name`, `customer_email`, `customer_phone` | Снимок контакта клиента на момент заказа (гость или смена данных). |
| **order_items** | `product_name`, `sku`, `price`, `total`, `vat_rate`, `vat_amount`, `seller_id` (nullable) | Снимок товара; seller_id под маркетплейс. |
| **order_addresses** | Новая таблица | id, order_id, type (shipping/billing), name, full_address, city, region, postcode, country, phone — снимок доставки. |
| **companies** | Создать с самого начала | name, inn, legal_address, vat_number (nullable), bank_details (nullable), credit_limit (nullable), contract_number (nullable), is_active. |
| **addresses** | `company_id` (nullable) | Адрес компании или пользователя. |
| **products** | `vat_rate` (decimal, nullable) | Ставка НДС по умолчанию для снимка в order_items. |
| **warehouses** | `seller_id` (nullable) | Склад продавца позже. |
| **stocks** | `seller_id` (nullable) | Остаток продавца (для маркетплейса). |

### 6. Решения, которые создадут проблемы через 6–12 месяцев

| Решение | Проблема | Что сделать сейчас |
|---------|----------|---------------------|
| Нет снимка в заказе | Отчёты, бухгалтерия, споры: «что именно купили и по какой цене» | Ввести order_addresses и снимок в order_items + customer_* в orders. |
| Companies «потом» | Массовые миграции и рефакторинг | Создать companies, company_users, FK (nullable) сразу. |
| Только product_id в order_items | Невозможно разнести заказ по продавцам без поломки | Добавить seller_id (nullable), снимок (product_name, sku, price). |
| Один тип «адрес» в orders | B2B: юр. адрес компании, доставка на склад — разные типы | order_addresses с type (shipping/billing). |
| Нет таблицы payments | Нет истории оплат (повтор, возврат, частичная оплата) | Таблица `payments`: order_id, amount, status, payment_method_id, paid_at, gateway_reference (nullable). |
| Нет таблицы shipments | Нет трекинга и статуса «отправлено» по отправкам | Таблица `shipments`: order_id, shipping_method_id, tracking_number, shipped_at, status. |

---

## Часть 2: Улучшенная финальная архитектура (V2)

### Модули (без лишнего)

| Модуль | MVP | Описание |
|--------|-----|----------|
| Catalog | ✓ | Категории, бренды, товары, атрибуты, изображения, совместимость (Vehicle), OEM/cross. |
| Inventory | ✓ | Склады, остатки (product + warehouse; seller_id nullable). |
| Pricing | ✓ | Цена в product; скидки, промокоды; основа для B2B (price_lists — таблицы есть, логика потом). |
| Cart | ✓ | Корзина (user/session), позиции (product_id, seller_id nullable). |
| Checkout | ✓ | Оформление, снимок адреса в order_addresses, снимок клиента в orders. |
| Orders | ✓ | Заказ, снимок в order_items и order_addresses, payments, shipments. |
| Customers | ✓ | User, профиль, адреса (customer_addresses = addresses). |
| Companies | ✓ (таблицы) | companies, company_users; логика B2B позже. |
| Admin | ✓ | Роли, Filament, управление каталогом/заказами/клиентами. |
| Content / SEO | ✓ | Страницы, баннеры, meta в категориях/товарах. |
| Marketplace Foundation | (поля) | seller_id в cart_items, order_items, warehouses, stocks; таблицы sellers, seller_products — создать позже или пустыми. |
| B2B Foundation | (таблицы) | companies, company_users, price_lists, price_list_items; логика позже. |

### Ключевые сущности и таблицы (финальный список)

- **users** — как было; без company_id в первой версии (привязка только через company_users).
- **companies** — с самого начала (name, inn, legal_address, vat_number, bank_details, credit_limit, contract_number, is_active).
- **company_users** — company_id, user_id, role (viewer/buyer/admin), is_default.
- **roles, permissions, model_has_roles, role_has_permissions, model_has_permissions** — Spatie.
- **categories** — дерево, meta_title/description/keywords для SEO.
- **brands** — без изменений.
- **products** — category_id, brand_id, sku, name, slug, description, short_description, weight, price, vat_rate (nullable), is_active, type (part/consumable/accessory); без supplier_id на MVP.
- **product_images** — без изменений.
- **product_attributes** — product_id, name, value, sort (плоская структура на MVP; нормализованные attributes/attribute_values можно добавить позже для фильтров).
- **product_oem_numbers** — product_id, oem_number (поиск по OEM).
- **product_cross_numbers** — product_id, cross_number (аналоги, кроссы).
- **vehicles** — make, model, generation, year_from, year_to, engine, body_type.
- **product_vehicle** (совместимость) — product_id, vehicle_id, oem_number (nullable).
- **warehouses** — name, code, is_default, is_active, seller_id (nullable).
- **stocks** — product_id, warehouse_id, quantity, reserved_quantity, seller_id (nullable); unique (product_id, warehouse_id).
- **price_lists** — company_id, name, currency, type (retail/wholesale), is_default — для B2B; на MVP пустая.
- **price_list_items** — price_list_id, product_id, price — для B2B.
- **carts** — user_id (nullable), session_id (nullable).
- **cart_items** — cart_id, product_id, quantity, price, seller_id (nullable).
- **order_statuses** — справочник.
- **orders** — user_id, company_id (nullable), status_id, subtotal, shipping_cost, total, customer_name, customer_email, customer_phone, shipping_method_id, payment_method_id, comment; без delivery_address_id — доставка из order_addresses.
- **order_addresses** — order_id, type (shipping/billing), name, full_address, city, region, postcode, country, phone.
- **order_items** — order_id, product_id, product_name, sku, quantity, price, total, vat_rate, vat_amount, seller_id (nullable).
- **payments** — order_id, amount, status, payment_method_id, paid_at, gateway_reference (nullable).
- **shipments** — order_id, shipping_method_id, tracking_number, shipped_at, status.
- **addresses** (customer_addresses) — user_id, company_id (nullable), type (shipping/billing), name, full_address, city, region, postcode, country, phone, is_default.
- **shipping_methods**, **payment_methods** — без изменений.
- **discounts**, **promo_codes** — без изменений; company_id (nullable) в discounts для B2B.
- **pages**, **banners**, **settings** — без изменений.
- **sellers**, **seller_products** — не создавать на MVP или создать пустыми с минимальными полями; использовать seller_id в cart_items/order_items/warehouses/stocks.

### Концепция Product vs Offer

- **Product** — карточка товара: название, описание, атрибуты, изображения, совместимость, OEM/кроссы. Базовая цена и vat_rate — для отображения и для «нашего» предложения.
- **Offer (логически)** — на MVP одно предложение = один продукт (наш склад, наша цена). Цена/остаток: product.price + stocks. В маркетплейсе: предложение = seller_product (seller_id + product_id + price + quantity). В корзине и заказе храним product_id и seller_id (nullable); при seller_id=null — наше предложение.
- Отдельную таблицу `offers` не вводим; B2B-цены — через price_lists/price_list_items.

### Снимки в заказе (обязательно)

- **orders:** customer_name, customer_email, customer_phone.
- **order_addresses:** полный снимок адреса доставки (и при необходимости billing).
- **order_items:** product_id (ссылка), product_name, sku, price, quantity, total, vat_rate, vat_amount, seller_id (nullable).

Так каталог и адреса можно менять без потери истории заказов.

---

Итог: в коде и миграциях ниже используются именно эта архитектура V2: компании и снимки с первого этапа, seller_id заложен, лишние сущности убраны.
