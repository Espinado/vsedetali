# Архитектура интернет-магазина автозапчастей

**Стек:** Laravel 12 + Livewire  
**Цель:** B2C → B2B → Marketplace

---

## 1. Список модулей

| № | Модуль | Назначение | MVP | B2B | Marketplace |
|---|--------|------------|-----|-----|-------------|
| 1 | **Catalog** | Каталог: категории, товары, бренды, атрибуты, совместимость с авто | ✓ | ✓ | ✓ |
| 2 | **Cart & Checkout** | Корзина, оформление заказа, доставка, оплата | ✓ | ✓ | ✓ |
| 3 | **Users & Auth** | Пользователи, компании, роли, права | ✓ | ✓ | ✓ |
| 4 | **Pricing & Promotions** | Цены, скидки, прайс-листы, B2B-цены | ✓ | ✓ | ✓ |
| 5 | **Inventory** | Остатки, склады, резервы | ✓ | ✓ | ✓ |
| 6 | **Content** | Страницы, блог, баннеры, SEO | ✓ | ✓ | ✓ |
| 7 | **Search & Filters** | Поиск, фильтры по авто/категориям | ✓ | ✓ | ✓ |
| 8 | **Notifications** | Email, in-app, SMS (опционально) | ✓ | ✓ | ✓ |
| 9 | **Reviews & Ratings** | Отзывы и рейтинги товаров/продавцов | — | ✓ | ✓ |
| 10 | **B2B** | Компании, контакты, лимиты, договоры | — | ✓ | ✓ |
| 11 | **Marketplace** | Продавцы, комиссии, мульти-вендор заказы | — | — | ✓ |
| 12 | **Analytics & Reports** | Отчёты по заказам, продажам, B2B | — | ✓ | ✓ |
| 13 | **Import/Export** | Импорт каталога, прайсов, выгрузки | — | ✓ | ✓ |

---

## 2. Список сущностей

### Каталог
- **Category** — дерево категорий (запчасти по узлам/маркам)
- **Brand** — бренды запчастей (OEM, aftermarket)
- **Product** — товар (артикул, название, описание, тип: запчасть/расходник/аксессуар)
- **ProductAttribute** — атрибуты товара (вес, размер, материал и т.д.)
- **ProductImage** — изображения товара
- **ProductPrice** — цены (базовая, по складам/продавцам — для marketplace)
- **Vehicle** — справочник авто (марка, модель, поколение, год, двигатель)
- **ProductVehicle** — связь товар ↔ авто (совместимость/OEM-номера)

### Корзина и заказы
- **Cart** / **CartItem** — корзина и позиции
- **Order** — заказ (статус, суммы, доставка, оплата)
- **OrderItem** — позиции заказа (с привязкой к продавцу для marketplace)
- **OrderStatus** — справочник статусов
- **Address** — адреса доставки/юр. адреса компаний
- **ShippingMethod** — способы доставки
- **PaymentMethod** — способы оплаты

### Пользователи и B2B
- **User** — пользователь (физлицо или контакт компании)
- **Role** — роли (customer, b2b_customer, seller, admin…)
- **Permission** — права (через Laravel Permission или кастом)
- **Company** — юрлицо (B2B): ИНН, реквизиты, лимиты, договор
- **CompanyUser** — связь пользователь ↔ компания (роль в компании)

### Цены и скидки
- **PriceList** — прайс-лист (для B2B: название, валюта, тип)
- **Discount** — скидки (процент/фикс, по категориям/товарам/компаниям, период)
- **PromoCode** — промокоды

### Остатки и склады
- **Warehouse** — склад (свой или продавца)
- **Stock** — остаток: товар + склад + количество (резерв отдельно или в той же таблице)
- **Supplier** — поставщик (для закупок; в marketplace — не обязателен на старте)

### Контент
- **Page** — статические страницы (доставка, оплата, о компании)
- **Banner** / **Slider** — баннеры на главной
- **SeoMeta** — мета-теги для страниц/категорий/товаров (или поля в сущностях)

### Отзывы и рейтинг
- **Review** — отзыв (к товару или к продавцу)
- **Rating** — рейтинг (агрегат или отдельная сущность по желанию)

### Marketplace
- **Seller** — продавец (профиль, реквизиты, комиссия платформы)
- **SellerProduct** — товар продавца (цена, остаток, статус на площадке)
- **Commission** — правило комиссии (%, по категориям/продавцам)

### Прочее
- **Notification** — уведомления (in-app)
- **Setting** — настройки магазина (key-value или отдельные поля)
- **ActivityLog** — лог действий (опционально, для аудита B2B/marketplace)

---

## 3. Связи между сущностями

```
User ──┬── 1:N ──► Cart, Order, Address, Review
       ├── N:M ──► Company (через CompanyUser), Role
       └── 1:1 ──► Seller (если продавец)

Company ── 1:N ──► CompanyUser, Address, Order (заказ от компании), PriceList

Category ── 1:N (self) ──► children
Category ── N:M ──► Product (или Product ─► category_id)

Brand ── 1:N ──► Product

Product ── 1:N ──► ProductAttribute, ProductImage, ProductPrice, OrderItem, Stock, Review
Product ── N:M ──► Vehicle (ProductVehicle), Category (если N:M)
Product ── 1:N ──► SellerProduct (marketplace)

Vehicle ── N:M ──► Product (ProductVehicle)

Cart ── 1:N ──► CartItem
CartItem ── N:1 ──► Product (и опционально SellerProduct)

Order ── 1:N ──► OrderItem
Order ── N:1 ──► User, Company (nullable), Address (доставка), ShippingMethod, PaymentMethod, OrderStatus
OrderItem ── N:1 ──► Product, Seller (nullable, marketplace)

Warehouse ── 1:N ──► Stock
Stock ── N:1 ──► Product, Warehouse (и опционально Seller для marketplace)

Seller ── 1:1 ──► User
Seller ── 1:N ──► SellerProduct, OrderItem, Warehouse (свои склады)
SellerProduct ── N:1 ──► Product, Seller

PriceList ── N:1 ──► Company (для B2B)
Discount ── полиморфная или связи: Category, Product, Company

Review ── N:1 ──► User, Product (или Seller)
```

Кратко по ключевым связям:
- **User ↔ Company:** многие ко многим через **CompanyUser** (один пользователь может быть в нескольких компаниях; в компании — несколько контактов).
- **Product ↔ Vehicle:** многие ко многим через **ProductVehicle** (одна запчасть — много авто; одно авто — много запчастей).
- **Order ↔ Seller:** в marketplace заказ разбивается на **OrderItem** с привязкой к продавцу; один Order — несколько «подзаказов» по продавцам.

---

## 4. Роли пользователей

| Роль | Описание | Модули доступа |
|------|----------|----------------|
| **guest** | Гость | Каталог, корзина (сессия), просмотр товаров |
| **customer** | Покупатель (B2C) | Всё у гостя + заказы, профиль, адреса, отзывы |
| **b2b_customer** | Покупатель B2B | Как customer + привязка к Company, B2B-цены, лимиты, отложенная оплата (если есть), отчёты по компании |
| **company_admin** | Админ компании | Управление контактами компании, заказами компании, лимитами (в рамках компании) |
| **seller** | Продавец (marketplace) | Кабинет продавца: свои товары, остатки, заказы по своим позициям, финансы |
| **content_manager** | Контент-менеджер | Каталог (редактирование), страницы, баннеры, SEO |
| **manager** | Менеджер заказов/клиентов | Заказы, клиенты, компании (B2B), без настроек системы |
| **admin** | Администратор | Всё кроме критичных настроек (роли, платежи, комиссии) |
| **super_admin** | Суперадмин | Полный доступ, в т.ч. роли, платежи, marketplace-настройки |

Рекомендация: роли и права хранить в **roles**, **permissions**, **role_has_permissions**, **model_has_roles** (пакет `spatie/laravel-permission`). Для B2B дополнительно в **CompanyUser** — роль внутри компании (viewer, buyer, admin).

---

## 5. Таблицы на старте (MVP)

Минимальный набор для запуска B2C без B2B и без marketplace:

- **users** — id, name, email, password, phone, email_verified_at, remember_token, timestamps
- **roles**, **permissions**, **model_has_roles**, **role_has_permissions** (Spatie)
- **categories** — id, parent_id, name, slug, description, image, sort, is_active, timestamps
- **brands** — id, name, slug, logo, is_active, timestamps
- **products** — id, category_id, brand_id, sku, name, slug, description, short_description, weight, is_active, timestamps
- **product_attributes** — id, product_id, name, value, sort, timestamps
- **product_images** — id, product_id, path, alt, sort, is_main, timestamps
- **vehicles** — id, make, model, generation, year_from, year_to, engine, body_type (минимальный набор для совместимости)
- **product_vehicle** — product_id, vehicle_id (и опционально oem_number), timestamps
- **carts** — id, user_id (nullable), session_id, timestamps
- **cart_items** — id, cart_id, product_id, quantity, price, timestamps
- **orders** — id, user_id, status_id, total, subtotal, shipping_cost, shipping_method_id, payment_method_id, delivery_address_id, comment, timestamps
- **order_items** — id, order_id, product_id, quantity, price, total, timestamps
- **order_statuses** — id, name, slug, color, sort, timestamps
- **addresses** — id, user_id, type (shipping/billing), full_address, city, region, postcode, country, phone, name, timestamps
- **shipping_methods** — id, name, description, cost, free_from, is_active, timestamps
- **payment_methods** — id, name, code, config (json), is_active, timestamps
- **warehouses** — id, name, code, is_default, is_active, timestamps
- **stocks** — id, product_id, warehouse_id, quantity, reserved_quantity, timestamps (+ unique product_id, warehouse_id)
- **pages** — id, title, slug, body, is_active, timestamps
- **settings** — id, key, value, group, timestamps
- **discounts** — id, name, type (percent/fixed), value, min_order, category_id (nullable), product_id (nullable), starts_at, ends_at, is_active, timestamps
- **promo_codes** — id, code, discount_id, uses_left, used_count, timestamps

При необходимости на старте можно объединить **product_images** и **product_attributes** в JSON-поля в **products**, но отдельные таблицы удобнее для масштаба и фильтрации.

---

## 6. Таблицы и поля под будущее

### Заложить структуру сейчас, заполнять позже

- **companies** — id, name, inn, legal_address, type (b2b), credit_limit, contract_number, contract_date, is_active, timestamps  
  На старте: таблицу можно не использовать или создавать пустой; в **orders** сразу заложить **company_id** (nullable).

- **company_users** — id, company_id, user_id, role (viewer/buyer/admin), is_default, timestamps  
  Нужна с момента включения B2B.

- **price_lists** — id, company_id, name, currency, type (retail/wholesale), is_default, timestamps  
  B2B-прайсы; на MVP не заполнять.

- **sellers** — id, user_id, name, inn, legal_info, commission_percent, status, timestamps  
  Для marketplace; таблицу создать при переходе к этапу маркетплейса или заранее с нулевым использованием.

- **seller_products** — id, seller_id, product_id, price, quantity, warehouse_id (nullable), status, timestamps  
  Товар на площадке от продавца; при MVP **products** и **stocks** — только свои.

- **reviews** — id, user_id, reviewable_type, reviewable_id (Product/Seller), rating, body, is_approved, timestamps  
  Полиморфная связь; можно добавить таблицу и не показывать блок отзывов до этапа «Отзывы».

- **notifications** — id, user_id, type, data (json), read_at, timestamps  
  Laravel notifications; таблица создаётся при первом использовании.

- **activity_log** — id, user_id, subject_type, subject_id, action, old_values (json), new_values (json), timestamps  
  Для аудита B2B и действий продавцов; таблицу заложить, заполнять по мере необходимости.

### Поля в существующих таблицах

- **users:** company_id (nullable) — опционально для «основной компании»; или только через company_users.
- **orders:** company_id (nullable), invoice_number, deferred_payment_until (B2B).
- **order_items:** seller_id (nullable), seller_product_id (nullable) — для разбивки заказа по продавцам.
- **products:** supplier_id (nullable), lead_time (дни поставки), country_of_origin — под закупки и маркетплейс.
- **stocks:** seller_id (nullable) — остатки продавца на своём складе.
- **warehouses:** seller_id (nullable) — склад продавца.
- **categories:** meta_title, meta_description, meta_keywords — SEO; можно вынести в **seo_meta** (morph) позже.
- **discounts:** company_id (nullable), seller_id (nullable) — персональные скидки B2B/продавца.

### Таблицы только под будущее (создавать на этапе B2B/Marketplace)

- **commissions** — id, seller_id (nullable), category_id (nullable), percent, fixed, timestamps  
  Правила комиссии платформы для маркетплейса.

- **invoices** — id, order_id, company_id, number, amount, status, paid_at, timestamps  
  Счета для B2B.

- **contracts** / **agreements** — при необходимости хранения сканов договоров с компаниями или продавцами.

---

## 7. Roadmap разработки по этапам

### Этап 1: Ядро и каталог (4–6 недель)
- Проект Laravel 12 + Livewire, базовая тема, аутентификация.
- Модуль **Catalog:** категории (дерево), бренды, товары, атрибуты, изображения.
- Справочник **Vehicle** и связь **ProductVehicle** (совместимость).
- Модуль **Search & Filters:** поиск по названию/артикулу, фильтры по категории, бренду, авто.
- Базовые **Content:** главная, страницы доставки/оплаты, футер/хедер.
- Роли: guest, customer, admin (минимально).

**Результат:** каталог с фильтрами по авто, без корзины и заказов.

---

### Этап 2: Корзина и заказ (3–4 недели)
- **Cart & Checkout:** корзина (сессия + привязка к user после логина), Livewire-корзина и чекаут.
- **Orders:** создание заказа, статусы, список заказов в ЛК.
- **Addresses:** адреса доставки пользователя.
- Справочники **ShippingMethod**, **PaymentMethod** (в т.ч. заглушки для оплаты).
- **Inventory:** склады и остатки (**warehouses**, **stocks**), списание при оформлении заказа.
- **Pricing:** базовая цена в товаре; скидки по категории/заказу; **PromoCode**.
- **Notifications:** письма «Заказ создан», «Статус изменён».

**Результат:** полноценный B2C-заказ с доставкой и оплатой (тестовая).

---

### Этап 3: Личный кабинет и контент (2–3 недели)
- ЛК: профиль, адреса, история заказов, смена пароля.
- Расширение **Content:** баннеры, SEO (meta для категорий/товаров).
- Модуль **Reviews & Ratings:** отзывы к товарам, модерация (опционально рейтинг продавца — заглушка).
- Улучшение поиска и фильтров (автодополнение, популярные запросы).

**Результат:** стабильный B2C-магазин с отзывами и нормальным контентом.

---

### Этап 4: B2B (4–6 недель)
- Сущности **Company**, **CompanyUser**, **PriceList**.
- Регистрация компании, верификация (ручная или по ИНН), договор/лимиты.
- Роли **b2b_customer**, **company_admin**; доступ к B2B-ценам и лимитам.
- В заказе: выбор компании, отложенная оплата (если нужно), счёт (таблица **invoices**).
- **Discounts** по company_id; отчёты по заказам компании (модуль **Analytics & Reports** базовый).
- Уведомления для менеджера о новых B2B-заказах/заявках.

**Результат:** B2B-клиенты могут покупать от имени компании по своим ценам и лимитам.

---

### Этап 5: Маркетплейс (6–8 недель)
- Сущности **Seller**, **SellerProduct**, **Commission**; расширение **orders/order_items** под продавцов.
- Регистрация продавца, модерация товаров; кабинет продавца: товары, остатки, заказы по своим позициям.
- Разделение заказа на «подзаказы» по продавцам; логистика (отправки от разных продавцов).
- Расчёт и учёт комиссии; выплаты продавцам (таблицы/модуль выплат).
- **Reviews** к продавцам; рейтинг продавца.
- **Stocks/Warehouses** с **seller_id**; опционально общий каталог с выбором продавца (один Product — много SellerProduct).

**Результат:** мульти-вендор: несколько продавцов, разбивка заказа, комиссии, кабинет продавца.

---

### Этап 6: Масштабирование и интеграции (постоянно)
- **Import/Export:** импорт каталога (Excel/CSV), прайсов от поставщиков; выгрузки для маркетплейсов/агрегаторов.
- Расширенная аналитика: отчёты по продажам, по продавцам, по компаниям, дашборды.
- Интеграции: платёжные системы (полноценные), службы доставки (трекинг), 1С, CRM.
- Оптимизация: кэш каталога, очереди для писем и отчётов, поиск (Scout/Elastic/Meilisearch).

---

## Итог

- **Модули:** 13; на старте активны Catalog, Cart & Checkout, Users & Auth, Pricing, Inventory, Content, Search, Notifications.
- **Сущности:** покрывают каталог, заказы, пользователей, компании, цены, остатки, контент, отзывы, маркетплейс.
- **Связи:** User–Company (N:M), Product–Vehicle (N:M), Order–OrderItem–Seller при маркетплейсе.
- **Роли:** от guest до super_admin с учётом B2B и продавцов.
- **Таблицы на старте:** перечислены в п.5; в п.6 — что заложить под B2B и marketplace (поля и отдельные таблицы).
- **Roadmap:** 6 этапов от ядра и каталога до B2B, маркетплейса и масштабирования.

После утверждения архитектуры можно переходить к миграциям и коду (Laravel 12 + Livewire) по этапам выше.
