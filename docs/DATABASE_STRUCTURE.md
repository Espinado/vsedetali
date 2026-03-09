# Детальная структура базы данных

На основе утверждённой архитектуры. Условные обозначения:
- **Сейчас** — создаём и используем в MVP.
- **Будущее** — создаём структуру/поля сейчас, заполняем позже (B2B / Marketplace).

Типы приведены для MySQL 8 / Laravel migrations.

---

## 1. Пользователи и авторизация

### 1.1 `users`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| name | string(255) | NO | — | — | — | |
| email | string(255) | NO | — | index | YES | |
| email_verified_at | timestamp | YES | null | — | — | |
| password | string(255) | NO | — | — | — | |
| phone | string(50) | YES | null | index | — | **Сейчас** |
| remember_token | string(100) | YES | null | — | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |
| **company_id** | **bigInteger unsigned** | **YES** | **null** | **FK → companies** | — | **Будущее (B2B): основная компания** |

**Индексы:** `PRIMARY(id)`, `users_email_unique`, `index(email)`, `index(phone)`.

**Сейчас:** без `company_id`. **Будущее:** добавить колонку `company_id` (nullable) при этапе B2B или заложить в первой миграции.

---

### 1.2 `roles` (Spatie Laravel Permission)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| name | string(255) | NO | — | — | YES | guard_name + name |
| guard_name | string(255) | NO | — | — | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Unique:** `(name, guard_name)`.

---

### 1.3 `permissions`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| name | string(255) | NO | — | — | YES | (name, guard_name) |
| guard_name | string(255) | NO | — | — | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Unique:** `(name, guard_name)`.

---

### 1.4 `model_has_roles`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| role_id | bigInteger unsigned | NO | — | PK, FK → roles | — | |
| model_type | string(255) | NO | — | index | — | |
| model_id | bigInteger unsigned | NO | — | PK, index | — | |

**PK:** `(role_id, model_id, model_type)`. **Unique:** `(model_id, model_type)` (один набор ролей на модель). **Индекс:** `(model_type, model_id)`.

---

### 1.5 `role_has_permissions`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| permission_id | bigInteger unsigned | NO | — | PK, FK → permissions | — | |
| role_id | bigInteger unsigned | NO | — | PK, FK → roles | — | |

**PK:** `(permission_id, role_id)`.

---

### 1.6 `model_has_permissions` (Spatie, прямая привязка permission к model)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| permission_id | bigInteger unsigned | NO | — | PK, FK | — | |
| model_type | string(255) | NO | — | index | — | |
| model_id | bigInteger unsigned | NO | — | PK, index | — | |

**PK:** `(permission_id, model_id, model_type)`.

---

## 2. Каталог

### 2.1 `categories`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| parent_id | bigInteger unsigned | YES | null | FK → categories, index | — | **Сейчас** |
| name | string(255) | NO | — | index | — | |
| slug | string(255) | NO | — | index | YES | в рамках parent или глобально |
| description | text | YES | null | — | — | |
| image | string(500) | YES | null | — | — | |
| sort | smallInteger unsigned | NO | 0 | index | — | порядок вывода |
| is_active | boolean | NO | true | index | — | **Сейчас** |
| meta_title | string(255) | YES | null | — | — | **Будущее (SEO)** |
| meta_description | string(500) | YES | null | — | — | **Будущее (SEO)** |
| meta_keywords | string(500) | YES | null | — | — | **Будущее (SEO)** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Уникальность slug:** обычно `unique(slug)` в рамках одного уровня или глобально — на выбор (например, `unique(parent_id, slug)` с null в parent_id).  
**Индексы:** `index(parent_id)`, `index(is_active)`, `index(sort)`.

---

### 2.2 `brands`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| name | string(255) | NO | — | index | — | |
| slug | string(255) | NO | — | — | YES | **Сейчас** |
| logo | string(500) | YES | null | — | — | |
| is_active | boolean | NO | true | index | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индексы:** `index(is_active)`.

---

### 2.3 `products`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| category_id | bigInteger unsigned | NO | — | FK → categories, index | — | **Сейчас** |
| brand_id | bigInteger unsigned | YES | null | FK → brands, index | — | **Сейчас** |
| sku | string(100) | NO | — | index | YES | артикул **Сейчас** |
| name | string(500) | NO | — | index, fulltext | — | **Сейчас** |
| slug | string(500) | NO | — | index | YES | **Сейчас** |
| description | text | YES | null | — | — | |
| short_description | string(1000) | YES | null | — | — | |
| weight | decimal(10,3) | YES | null | — | — | кг |
| price | decimal(12,2) | NO | 0 | index | — | базовая цена **Сейчас** |
| is_active | boolean | NO | true | index | — | **Сейчас** |
| type | enum('part','consumable','accessory') | NO | 'part' | index | — | **Сейчас**: запчасть/расходник/аксессуар |
| **supplier_id** | **bigInteger unsigned** | **YES** | **null** | **FK → suppliers** | — | **Будущее** |
| **lead_time_days** | **smallInteger unsigned** | **YES** | **null** | — | — | **Будущее**: дни поставки |
| **country_of_origin** | **string(2)** | **YES** | **null** | — | — | **Будущее**: ISO код |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индексы:** `index(category_id)`, `index(brand_id)`, `index(is_active)`, `index(type)`, `index(price)`, при необходимости `FULLTEXT(name, description)` для поиска.  
**Сейчас:** без supplier_id, lead_time_days, country_of_origin. **Будущее:** добавить колонки или заложить nullable в первой миграции.

---

### 2.4 `product_attributes`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| product_id | bigInteger unsigned | NO | — | FK → products, index | — | **Сейчас** |
| name | string(255) | NO | — | index | — | название атрибута |
| value | string(500) | NO | — | — | — | значение |
| sort | smallInteger unsigned | NO | 0 | — | — | **Сейчас** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индексы:** `index(product_id)`, составной `index(product_id, name)` для фильтров.

---

### 2.5 `product_images`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| product_id | bigInteger unsigned | NO | — | FK → products, index | — | **Сейчас** |
| path | string(500) | NO | — | — | — | путь/URL |
| alt | string(255) | YES | null | — | — | |
| sort | smallInteger unsigned | NO | 0 | index | — | |
| is_main | boolean | NO | false | — | — | **Сейчас** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индексы:** `index(product_id)`, `index(product_id, sort)`.

---

### 2.6 `vehicles`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| make | string(100) | NO | — | index | — | марка **Сейчас** |
| model | string(100) | NO | — | index | — | модель |
| generation | string(100) | YES | null | index | — | поколение |
| year_from | smallInteger unsigned | YES | null | index | — | год с |
| year_to | smallInteger unsigned | YES | null | index | — | год по |
| engine | string(100) | YES | null | index | — | двигатель |
| body_type | string(50) | YES | null | index | — | кузов **Сейчас** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индексы:** составной для подбора: `index(make, model)`, `index(year_from, year_to)`, при необходимости `index(engine)`, `index(body_type)`.

---

### 2.7 `product_vehicle` (pivot)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Сейчас** (удобнее для Eloquent) |
| product_id | bigInteger unsigned | NO | — | FK → products, index | — | |
| vehicle_id | bigInteger unsigned | NO | — | FK → vehicles, index | — | |
| oem_number | string(100) | YES | null | index | — | OEM номер для этого авто |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Unique:** `(product_id, vehicle_id)`. **Индексы:** `index(product_id)`, `index(vehicle_id)`, `index(oem_number)`.

---

## 3. Корзина и заказы

### 3.1 `carts`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| user_id | bigInteger unsigned | YES | null | FK → users, index | — | **Сейчас** |
| session_id | string(255) | YES | null | index | — | для гостя |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индексы:** `index(user_id)`, `index(session_id)`. Один активный корзина на user или на session — логика в приложении.

---

### 3.2 `cart_items`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| cart_id | bigInteger unsigned | NO | — | FK → carts, index | — | **Сейчас** |
| product_id | bigInteger unsigned | NO | — | FK → products, index | — | |
| quantity | integer unsigned | NO | 1 | — | — | |
| price | decimal(12,2) | NO | — | — | — | цена на момент добавления |
| **seller_product_id** | **bigInteger unsigned** | **YES** | **null** | **FK → seller_products** | — | **Будущее (marketplace)** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Unique (опционально):** `(cart_id, product_id)` или `(cart_id, product_id, seller_product_id)` — если один товар от разных продавцов. **Сейчас:** без seller_product_id.

---

### 3.3 `order_statuses`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| name | string(100) | NO | — | — | — | **Сейчас** |
| slug | string(50) | NO | — | — | YES | new, confirmed, shipped, delivered, cancelled |
| color | string(20) | YES | null | — | — | для UI |
| sort | smallInteger unsigned | NO | 0 | index | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Enum-логика:** храним в slug (string), не enum в БД — гибкость.

---

### 3.4 `orders`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| user_id | bigInteger unsigned | NO | — | FK → users, index | — | **Сейчас** |
| status_id | bigInteger unsigned | NO | — | FK → order_statuses, index | — | **Сейчас** |
| subtotal | decimal(12,2) | NO | 0 | — | — | сумма товаров |
| shipping_cost | decimal(10,2) | NO | 0 | — | — | **Сейчас** |
| total | decimal(12,2) | NO | 0 | index | — | итого |
| shipping_method_id | bigInteger unsigned | YES | null | FK → shipping_methods | — | **Сейчас** |
| payment_method_id | bigInteger unsigned | YES | null | FK → payment_methods | — | **Сейчас** |
| delivery_address_id | bigInteger unsigned | YES | null | FK → addresses | — | **Сейчас** |
| comment | text | YES | null | — | — | комментарий к заказу |
| **company_id** | **bigInteger unsigned** | **YES** | **null** | **FK → companies** | — | **Будущее (B2B)** |
| **invoice_number** | **string(50)** | **YES** | **null** | **index** | — | **Будущее (B2B)** |
| **deferred_payment_until** | **date** | **YES** | **null** | — | — | **Будущее (B2B)** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индексы:** `index(user_id)`, `index(status_id)`, `index(created_at)`, `index(total)`. **Сейчас:** без company_id, invoice_number, deferred_payment_until. **Будущее:** добавить колонки.

---

### 3.5 `order_items`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| order_id | bigInteger unsigned | NO | — | FK → orders, index | — | **Сейчас** |
| product_id | bigInteger unsigned | NO | — | FK → products, index | — | **Сейчас** |
| quantity | integer unsigned | NO | 1 | — | — | |
| price | decimal(12,2) | NO | — | — | — | цена за единицу |
| total | decimal(12,2) | NO | — | — | — | price * quantity |
| **seller_id** | **bigInteger unsigned** | **YES** | **null** | **FK → sellers** | — | **Будущее (marketplace)** |
| **seller_product_id** | **bigInteger unsigned** | **YES** | **null** | **FK → seller_products** | — | **Будущее (marketplace)** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индексы:** `index(order_id)`, `index(product_id)`. **Сейчас:** без seller_id, seller_product_id.

---

### 3.6 `addresses`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| user_id | bigInteger unsigned | YES | null | FK → users, index | — | **Сейчас** (для физлиц) |
| **company_id** | **bigInteger unsigned** | **YES** | **null** | **FK → companies** | — | **Будущее (B2B)** |
| type | enum('shipping','billing') | NO | 'shipping' | index | — | **Сейчас** |
| name | string(255) | YES | null | — | — | ФИО или название |
| full_address | string(500) | NO | — | — | — | улица, дом и т.д. |
| city | string(100) | NO | — | index | — | |
| region | string(100) | YES | null | — | — | область/регион |
| postcode | string(20) | YES | null | — | — | |
| country | string(2) | NO | 'LV' | index | — | ISO |
| phone | string(50) | YES | null | — | — | |
| is_default | boolean | NO | false | — | — | **Сейчас** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индексы:** `index(user_id)`, `index(company_id)`, `index(type)`. **Сейчас:** без company_id (колонку можно заложить nullable).

---

### 3.7 `shipping_methods`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| name | string(255) | NO | — | — | — | **Сейчас** |
| description | text | YES | null | — | — | |
| cost | decimal(10,2) | NO | 0 | — | — | |
| free_from | decimal(10,2) | YES | null | — | — | бесплатно от суммы |
| is_active | boolean | NO | true | index | — | **Сейчас** |
| sort | smallInteger unsigned | NO | 0 | index | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

---

### 3.8 `payment_methods`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| name | string(255) | NO | — | — | — | **Сейчас** |
| code | string(50) | NO | — | — | YES | bank_transfer, card, etc. |
| config | json | YES | null | — | — | настройки интеграции |
| is_active | boolean | NO | true | index | — | **Сейчас** |
| sort | smallInteger unsigned | NO | 0 | index | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

---

## 4. Цены и скидки

### 4.1 `discounts`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| name | string(255) | NO | — | — | — | **Сейчас** |
| type | enum('percent','fixed') | NO | 'percent' | index | — | **Сейчас** |
| value | decimal(10,2) | NO | — | — | — | процент или сумма |
| min_order | decimal(10,2) | YES | null | — | — | мин. сумма заказа |
| category_id | bigInteger unsigned | YES | null | FK → categories | — | скидка по категории **Сейчас** |
| product_id | bigInteger unsigned | YES | null | FK → products | — | по товару **Сейчас** |
| **company_id** | **bigInteger unsigned** | **YES** | **null** | **FK → companies** | — | **Будущее (B2B)** |
| **seller_id** | **bigInteger unsigned** | **YES** | **null** | **FK → sellers** | — | **Будущее (marketplace)** |
| starts_at | timestamp | YES | null | index | — | **Сейчас** |
| ends_at | timestamp | YES | null | index | — | **Сейчас** |
| is_active | boolean | NO | true | index | — | **Сейчас** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Сейчас:** без company_id, seller_id. **Будущее:** добавить колонки.

---

### 4.2 `promo_codes`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| code | string(50) | NO | — | — | YES | **Сейчас** |
| discount_id | bigInteger unsigned | NO | — | FK → discounts, index | — | **Сейчас** |
| uses_left | integer unsigned | YES | null | — | — | null = без лимита |
| used_count | integer unsigned | NO | 0 | — | — | **Сейчас** |
| starts_at | timestamp | YES | null | index | — | |
| ends_at | timestamp | YES | null | index | — | |
| is_active | boolean | NO | true | index | — | **Сейчас** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индексы:** `unique(code)`.

---

## 5. Склады и остатки

### 5.1 `warehouses`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| name | string(255) | NO | — | — | — | **Сейчас** |
| code | string(50) | NO | — | — | YES | **Сейчас** |
| is_default | boolean | NO | false | index | — | **Сейчас** |
| is_active | boolean | NO | true | index | — | **Сейчас** |
| **seller_id** | **bigInteger unsigned** | **YES** | **null** | **FK → sellers** | — | **Будущее (marketplace)** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Сейчас:** без seller_id. **Будущее:** добавить seller_id (nullable).

---

### 5.2 `stocks`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| product_id | bigInteger unsigned | NO | — | FK → products, index | — | **Сейчас** |
| warehouse_id | bigInteger unsigned | NO | — | FK → warehouses, index | — | **Сейчас** |
| quantity | integer unsigned | NO | 0 | — | — | **Сейчас** |
| reserved_quantity | integer unsigned | NO | 0 | — | — | зарезервировано **Сейчас** |
| **seller_id** | **bigInteger unsigned** | **YES** | **null** | **FK → sellers** | — | **Будущее (marketplace)** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Unique:** `(product_id, warehouse_id)` — один остаток на пару товар+склад. **Сейчас:** без seller_id.

---

## 6. Контент

### 6.1 `pages`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| title | string(255) | NO | — | — | — | **Сейчас** |
| slug | string(255) | NO | — | — | YES | **Сейчас** |
| body | longText | YES | null | — | — | |
| is_active | boolean | NO | true | index | — | **Сейчас** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

---

### 6.2 `banners`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Сейчас (для главной)** |
| name | string(255) | YES | null | — | — | |
| image | string(500) | NO | — | — | — | |
| link | string(500) | YES | null | — | — | |
| sort | smallInteger unsigned | NO | 0 | index | — | |
| is_active | boolean | NO | true | index | — | **Сейчас** |
| starts_at | timestamp | YES | null | — | — | |
| ends_at | timestamp | YES | null | — | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

---

## 7. Настройки

### 7.1 `settings`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | |
| key | string(255) | NO | — | — | YES | **Сейчас** |
| value | text | YES | null | — | — | |
| group | string(100) | YES | 'general' | index | — | **Сейчас** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Unique:** `key` (или `(group, key)` — по желанию).

---

## 8. Таблицы под будущее (структура сразу)

### 8.1 `companies` (B2B)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Будущее** |
| name | string(255) | NO | — | index | — | |
| inn | string(50) | YES | null | index | YES | ИНН/код плательщика |
| legal_address | text | YES | null | — | — | |
| credit_limit | decimal(12,2) | YES | null | — | — | лимит отсрочки |
| contract_number | string(100) | YES | null | index | — | |
| contract_date | date | YES | null | — | — | |
| is_active | boolean | NO | true | index | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Создать таблицу сейчас**, заполнять при этапе B2B. В **users** и **orders** заложить `company_id` (nullable).

---

### 8.2 `company_users` (B2B)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Будущее** |
| company_id | bigInteger unsigned | NO | — | FK → companies, index | — | |
| user_id | bigInteger unsigned | NO | — | FK → users, index | — | |
| role | enum('viewer','buyer','admin') | NO | 'buyer' | index | — | роль в компании |
| is_default | boolean | NO | false | — | — | компания по умолчанию |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Unique:** `(company_id, user_id)`.

---

### 8.3 `price_lists` (B2B)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Будущее** |
| company_id | bigInteger unsigned | NO | — | FK → companies, index | — | |
| name | string(255) | NO | — | — | — | |
| currency | string(3) | NO | 'EUR' | — | — | |
| type | enum('retail','wholesale') | NO | 'retail' | index | — | |
| is_default | boolean | NO | false | — | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

Нужна отдельная таблица **price_list_products** (company price list → product → price) или хранить в product_prices с привязкой к price_list_id. Для простоты: **Будущее** — таблицу price_lists создать сейчас; детали прайсов (product_id, price_list_id, price) — на этапе B2B.

---

### 8.4 `sellers` (Marketplace)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Будущее** |
| user_id | bigInteger unsigned | NO | — | FK → users, index | YES (1:1) | |
| name | string(255) | NO | — | index | — | название магазина |
| slug | string(255) | NO | — | — | YES | |
| inn | string(50) | YES | null | index | — | |
| legal_info | text | YES | null | — | — | реквизиты |
| commission_percent | decimal(5,2) | YES | null | — | — | % комиссии платформы |
| status | enum('pending','active','suspended','rejected') | NO | 'pending' | index | — | **Будущее** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Создать таблицу сейчас**, заполнять при этапе Marketplace. **user_id** unique (один пользователь — один продавец).

---

### 8.5 `seller_products` (Marketplace)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Будущее** |
| seller_id | bigInteger unsigned | NO | — | FK → sellers, index | — | |
| product_id | bigInteger unsigned | NO | — | FK → products, index | — | |
| price | decimal(12,2) | NO | — | index | — | |
| quantity | integer unsigned | NO | 0 | — | — | остаток у продавца |
| warehouse_id | bigInteger unsigned | YES | null | FK → warehouses | — | |
| status | enum('draft','active','paused','rejected') | NO | 'draft' | index | — | **Будущее** |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Unique:** `(seller_id, product_id)` — один товар у продавца один раз.

---

### 8.6 `reviews` (полиморфная)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Будущее** |
| user_id | bigInteger unsigned | NO | — | FK → users, index | — | |
| reviewable_type | string(255) | NO | — | index | — | Product, Seller |
| reviewable_id | bigInteger unsigned | NO | — | index | — | |
| rating | tinyInteger unsigned | NO | — | index | — | 1–5 |
| body | text | YES | null | — | — | |
| is_approved | boolean | NO | false | index | — | модерация |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индекс:** `(reviewable_type, reviewable_id)`. **Unique (опционально):** один отзыв пользователя на одну сущность — `(user_id, reviewable_type, reviewable_id)`.

---

### 8.7 `notifications` (Laravel)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | char(36) | NO | — | PK | — | UUID **Будущее** |
| type | string(255) | NO | — | index | — | |
| notifiable_type | string(255) | NO | — | index | — | |
| notifiable_id | bigInteger unsigned | NO | — | index | — | |
| data | json | NO | — | — | — | |
| read_at | timestamp | YES | null | index | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

**Индекс:** `(notifiable_type, notifiable_id)`.

---

### 8.8 `activity_log`
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Будущее** |
| user_id | bigInteger unsigned | YES | null | FK → users, index | — | |
| subject_type | string(255) | NO | — | index | — | |
| subject_id | bigInteger unsigned | YES | null | index | — | |
| action | string(100) | NO | — | index | — | created, updated, deleted |
| old_values | json | YES | null | — | — | |
| new_values | json | YES | null | — | — | |
| created_at | timestamp | YES | null | index | — | |

**Индекс:** `(subject_type, subject_id)`.

---

### 8.9 `commissions` (Marketplace)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Будущее** |
| seller_id | bigInteger unsigned | YES | null | FK → sellers | — | null = правило по умолчанию |
| category_id | bigInteger unsigned | YES | null | FK → categories | — | null = все категории |
| percent | decimal(5,2) | YES | null | — | — | |
| fixed | decimal(10,2) | YES | null | — | — | фикс с заказа/позиции |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

---

### 8.10 `invoices` (B2B)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Будущее** |
| order_id | bigInteger unsigned | NO | — | FK → orders, index | — | |
| company_id | bigInteger unsigned | NO | — | FK → companies, index | — | |
| number | string(50) | NO | — | — | YES | номер счёта |
| amount | decimal(12,2) | NO | — | — | — | |
| status | enum('draft','sent','paid','cancelled') | NO | 'draft' | index | — | **Будущее** |
| paid_at | timestamp | YES | null | — | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

---

### 8.11 `suppliers` (опционально, для закупок / маркетплейса)
| Поле | Тип | Nullable | Default | Индексы | Unique | Описание |
|------|-----|----------|---------|---------|--------|----------|
| id | bigInteger unsigned | NO | — | PK, AI | — | **Будущее** |
| name | string(255) | NO | — | index | — | |
| code | string(50) | YES | null | index | YES | |
| contact_info | text | YES | null | — | — | |
| is_active | boolean | NO | true | index | — | |
| created_at | timestamp | YES | null | — | — | |
| updated_at | timestamp | YES | null | — | — | |

Таблицу можно не создавать на старте; в **products** поле **supplier_id** добавить при появлении таблицы **suppliers**.

---

## 9. Foreign Keys (сводка)

Рекомендуемые действия при удалении (ON DELETE):

| Таблица | FK | Ссылка на | Рекомендация |
|---------|-----|-----------|--------------|
| users | company_id | companies | SET NULL |
| categories | parent_id | categories | SET NULL |
| products | category_id | categories | RESTRICT |
| products | brand_id | brands | SET NULL |
| product_attributes | product_id | products | CASCADE |
| product_images | product_id | products | CASCADE |
| product_vehicle | product_id, vehicle_id | products, vehicles | CASCADE |
| carts | user_id | users | SET NULL (или CASCADE по логике) |
| cart_items | cart_id, product_id | carts, products | CASCADE |
| orders | user_id, status_id, delivery_address_id, shipping_method_id, payment_method_id, company_id | users, order_statuses, addresses, shipping_methods, payment_methods, companies | user_id RESTRICT, status RESTRICT, address SET NULL, company SET NULL |
| order_items | order_id, product_id, seller_id, seller_product_id | orders, products, sellers, seller_products | CASCADE |
| addresses | user_id, company_id | users, companies | CASCADE |
| discounts | category_id, product_id, company_id, seller_id | categories, products, companies, sellers | SET NULL |
| promo_codes | discount_id | discounts | CASCADE |
| stocks | product_id, warehouse_id, seller_id | products, warehouses, sellers | CASCADE |
| warehouses | seller_id | sellers | SET NULL |
| company_users | company_id, user_id | companies, users | CASCADE |
| price_lists | company_id | companies | CASCADE |
| sellers | user_id | users | CASCADE |
| seller_products | seller_id, product_id, warehouse_id | sellers, products, warehouses | CASCADE |
| reviews | user_id | users | CASCADE |
| activity_log | user_id | users | SET NULL |
| commissions | seller_id, category_id | sellers, categories | CASCADE / SET NULL |
| invoices | order_id, company_id | orders, companies | RESTRICT |

---

## 10. Очередность миграций

Ниже — порядок создания миграций с учётом зависимостей (FK). Каждый пункт — одна миграция или логическая группа.

### Фаза 1: Ядро и пользователи
1. **create_users_table** — users (без company_id).
2. **create_permission_tables** (Spatie) — roles, permissions, model_has_roles, role_has_permissions, model_has_permissions.

### Фаза 2: Справочники без зависимостей от «будущих» таблиц
3. **create_categories_table** — categories (все поля, включая meta_* для будущего SEO).
4. **create_brands_table** — brands.
5. **create_vehicles_table** — vehicles.
6. **create_order_statuses_table** — order_statuses (без заказов).
7. **create_shipping_methods_table** — shipping_methods.
8. **create_payment_methods_table** — payment_methods.

### Фаза 3: Компании и B2B (таблицы «под будущее»)
9. **create_companies_table** — companies.
10. **create_company_users_table** — company_users (FK: companies, users).
11. **create_price_lists_table** — price_lists (FK: companies).

Добавить в **users** колонку **company_id** (nullable): отдельная миграция **add_company_id_to_users_table** после companies.

### Фаза 4: Каталог (товары и связи)
12. **create_products_table** — products (category_id, brand_id; без supplier_id или с nullable supplier_id без FK, если suppliers пока нет).
13. **create_product_attributes_table** — product_attributes (FK: products).
14. **create_product_images_table** — product_images (FK: products).
15. **create_product_vehicle_table** — product_vehicle (FK: products, vehicles).

### Фаза 5: Адреса и корзина
16. **create_addresses_table** — addresses (user_id; company_id nullable, FK после companies).
17. **create_carts_table** — carts (user_id, session_id).
18. **create_cart_items_table** — cart_items (cart_id, product_id; seller_product_id nullable — заложить под будущее).

### Фаза 6: Заказы
19. **create_orders_table** — orders (user_id, status_id, shipping_method_id, payment_method_id, delivery_address_id; company_id, invoice_number, deferred_payment_until — nullable, под будущее).
20. **create_order_items_table** — order_items (order_id, product_id; seller_id, seller_product_id — nullable, под будущее).

### Фаза 7: Скидки и промокоды
21. **create_discounts_table** — discounts (category_id, product_id nullable; company_id, seller_id nullable — под будущее; FK на companies/sellers добавить после создания sellers).
22. **create_promo_codes_table** — promo_codes (FK: discounts).

### Фаза 8: Склады и остатки
23. **create_warehouses_table** — warehouses (seller_id nullable — под будущее).
24. **create_stocks_table** — stocks (product_id, warehouse_id; seller_id nullable; unique product_id+warehouse_id).

### Фаза 9: Контент и настройки
25. **create_pages_table** — pages.
26. **create_banners_table** — banners.
27. **create_settings_table** — settings.

### Фаза 10: Marketplace (таблицы «под будущее»)
28. **create_sellers_table** — sellers (FK: users).
29. **create_seller_products_table** — seller_products (FK: sellers, products, warehouses).

Добавить FK и nullable колонки в существующие таблицы (если ещё не заложены):
- **add_seller_id_to_warehouses_table**
- **add_seller_id_to_stocks_table**
- **add_seller_id_and_seller_product_id_to_order_items_table**
- **add_seller_product_id_to_cart_items_table** (nullable)
- **add_seller_id_to_discounts_table** (nullable)
- **add_company_id_to_addresses_table** (nullable) — если не было в create_addresses
- **add_company_id_to_orders_table** и т.д. — если не были в create_orders

### Фаза 11: Отзывы, уведомления, аудит
30. **create_reviews_table** — reviews (FK: users).
31. **create_notifications_table** — стандартная Laravel notifications.
32. **create_activity_log_table** — activity_log (FK: users, nullable).

### Фаза 12: B2B и маркетплейс (доп. сущности)
33. **create_commissions_table** — commissions (FK: sellers, categories).
34. **create_invoices_table** — invoices (FK: orders, companies).

### Фаза 13: Поставщики (опционально)
35. **create_suppliers_table** — suppliers.
36. **add_supplier_id_to_products_table** — products.supplier_id (nullable), lead_time_days, country_of_origin.

---

## 11. Краткая последовательность миграций (нумерация для файлов)

Рекомендуемый порядок имён файлов (Laravel timestamp + имя):

1. `*_create_users_table.php`
2. `*_create_permission_tables.php` (Spatie)
3. `*_create_categories_table.php`
4. `*_create_brands_table.php`
5. `*_create_vehicles_table.php`
6. `*_create_order_statuses_table.php`
7. `*_create_shipping_methods_table.php`
8. `*_create_payment_methods_table.php`
9. `*_create_companies_table.php`
10. `*_create_company_users_table.php`
11. `*_create_price_lists_table.php`
12. `*_add_company_id_to_users_table.php`
13. `*_create_products_table.php`
14. `*_create_product_attributes_table.php`
15. `*_create_product_images_table.php`
16. `*_create_product_vehicle_table.php`
17. `*_create_addresses_table.php`
18. `*_create_carts_table.php`
19. `*_create_cart_items_table.php`
20. `*_create_orders_table.php`
21. `*_create_order_items_table.php`
22. `*_create_discounts_table.php`
23. `*_create_promo_codes_table.php`
24. `*_create_warehouses_table.php`
25. `*_create_stocks_table.php`
26. `*_create_pages_table.php`
27. `*_create_banners_table.php`
28. `*_create_settings_table.php`
29. `*_create_sellers_table.php`
30. `*_create_seller_products_table.php`
31. `*_add_marketplace_fields_to_*.php` (несколько миграций: warehouses, stocks, order_items, cart_items, discounts)
32. `*_create_reviews_table.php`
33. `*_create_notifications_table.php` (или через `php artisan notifications:table`)
34. `*_create_activity_log_table.php`
35. `*_create_commissions_table.php`
36. `*_create_invoices_table.php`
37. `*_create_suppliers_table.php` (опционально)
38. `*_add_supplier_fields_to_products_table.php` (опционально)

---

## 12. Сводная таблица: все таблицы

| № | Таблица | Назначение | Когда |
|---|---------|------------|--------|
| 1 | users | Пользователи | Сейчас |
| 2 | roles | Роли (Spatie) | Сейчас |
| 3 | permissions | Права (Spatie) | Сейчас |
| 4 | model_has_roles | Связь модель–роль | Сейчас |
| 5 | role_has_permissions | Связь роль–право | Сейчас |
| 6 | model_has_permissions | Прямая привязка права к модели | Сейчас |
| 7 | categories | Категории каталога | Сейчас |
| 8 | brands | Бренды | Сейчас |
| 9 | vehicles | Справочник авто (совместимость) | Сейчас |
| 10 | order_statuses | Статусы заказа | Сейчас |
| 11 | shipping_methods | Способы доставки | Сейчас |
| 12 | payment_methods | Способы оплаты | Сейчас |
| 13 | companies | B2B-компании | Будущее (структура сейчас) |
| 14 | company_users | Пользователи компаний | Будущее (структура сейчас) |
| 15 | price_lists | B2B-прайс-листы | Будущее (структура сейчас) |
| 16 | products | Товары | Сейчас |
| 17 | product_attributes | Атрибуты товара | Сейчас |
| 18 | product_images | Изображения товара | Сейчас |
| 19 | product_vehicle | Совместимость товар–авто | Сейчас |
| 20 | addresses | Адреса доставки/выставления счетов | Сейчас |
| 21 | carts | Корзины | Сейчас |
| 22 | cart_items | Позиции корзины | Сейчас |
| 23 | orders | Заказы | Сейчас |
| 24 | order_items | Позиции заказа | Сейчас |
| 25 | discounts | Скидки | Сейчас |
| 26 | promo_codes | Промокоды | Сейчас |
| 27 | warehouses | Склады | Сейчас |
| 28 | stocks | Остатки | Сейчас |
| 29 | pages | Статические страницы | Сейчас |
| 30 | banners | Баннеры | Сейчас |
| 31 | settings | Настройки магазина | Сейчас |
| 32 | sellers | Продавцы (marketplace) | Будущее (структура сейчас) |
| 33 | seller_products | Товары продавцов | Будущее (структура сейчас) |
| 34 | reviews | Отзывы (полиморфные) | Будущее (структура сейчас) |
| 35 | notifications | In-app уведомления | Будущее (структура сейчас) |
| 36 | activity_log | Аудит действий | Будущее (структура сейчас) |
| 37 | commissions | Правила комиссии (marketplace) | Будущее (структура сейчас) |
| 38 | invoices | Счета (B2B) | Будущее (структура сейчас) |
| 39 | suppliers | Поставщики | Будущее (опционально) |

---

## 13. Очередность миграций (чеклист)

Зависимости: миграция с FK должна идти после миграции таблицы, на которую ссылается.

```
Фаза 1 — Пользователи и роли
  1. create_users_table
  2. create_permission_tables (Spatie)

Фаза 2 — Справочники (без FK на «будущие» таблицы)
  3. create_categories_table
  4. create_brands_table
  5. create_vehicles_table
  6. create_order_statuses_table
  7. create_shipping_methods_table
  8. create_payment_methods_table

Фаза 3 — B2B (таблицы под будущее)
  9. create_companies_table
 10. create_company_users_table  (FK: companies, users)
 11. create_price_lists_table    (FK: companies)
 12. add_company_id_to_users_table

Фаза 4 — Каталог
 13. create_products_table       (FK: categories, brands)
 14. create_product_attributes_table
 15. create_product_images_table
 16. create_product_vehicle_table (FK: products, vehicles)

Фаза 5 — Адреса и корзина
 17. create_addresses_table      (FK: users; company_id nullable)
 18. create_carts_table          (FK: users)
 19. create_cart_items_table     (FK: carts, products)

Фаза 6 — Заказы
 20. create_orders_table         (FK: users, order_statuses, addresses, shipping_methods, payment_methods; company_id nullable)
 21. create_order_items_table    (FK: orders, products; seller_id, seller_product_id nullable)

Фаза 7 — Скидки
 22. create_discounts_table      (FK: categories, products; company_id, seller_id nullable — FK на sellers после create_sellers)
 23. create_promo_codes_table    (FK: discounts)

Фаза 8 — Склады
 24. create_warehouses_table     (seller_id nullable)
 25. create_stocks_table         (FK: products, warehouses; seller_id nullable; unique product_id+warehouse_id)

Фаза 9 — Контент и настройки
 26. create_pages_table
 27. create_banners_table
 28. create_settings_table

Фаза 10 — Marketplace (таблицы под будущее)
 29. create_sellers_table        (FK: users)
 30. create_seller_products_table (FK: sellers, products, warehouses)
 31. add_seller_id_to_discounts_table (если FK не был в create_discounts)

Фаза 11 — Отзывы, уведомления, аудит
 32. create_reviews_table
 33. create_notifications_table
 34. create_activity_log_table

Фаза 12 — B2B и маркетплейс (доп.)
 35. create_commissions_table    (FK: sellers, categories)
 36. create_invoices_table       (FK: orders, companies)

Фаза 13 — Поставщики (опционально)
 37. create_suppliers_table
 38. add_supplier_fields_to_products_table
```

**Примечание:** В `create_discounts_table` колонки `company_id` и `seller_id` можно сделать nullable без FK при первой создании; FK на `companies` и `sellers` добавить после создания этих таблиц (companies уже есть к моменту discounts, sellers — после фазы 10). Либо в `create_discounts_table` добавить только `company_id` с FK на companies, а `seller_id` — в миграции `add_seller_id_to_discounts_table` после `create_sellers_table`.

Итог: структура БД и порядок миграций согласованы с архитектурой; что делаем сразу и что закладываем на будущее — помечено в таблицах и в списке миграций.
