# Деплой и одноразовые команды (прод)

## Администраторы Filament (`/admin`)

Пароли **не хранятся в репозитории**. На сервере после `git pull` выполни (подставь свои данные):

```bash
cd ~/public_html
/usr/local/bin/php8.3 artisan user:create-admin admin@example.com --name="Имя"
# введи пароль дважды (скрытый ввод)
```

Или одной строкой (пароль попадёт в history — хуже для безопасности):

```bash
/usr/local/bin/php8.3 artisan user:create-admin admin@example.com --name="Имя" --password='сложный-пароль'
```

Повтори для второго админа. Существующий пользователь с тем же email будет обновлён (пароль и `is_admin`).

## Импорт остатков (CSV UTF-8, отчёт «Остатки»)

Файл **.xlsx** сначала сохрани в Excel как **CSV UTF-8**, залей на сервер. Удобно класть CSV **в корень проекта** (рядом с `artisan`) или в `storage/app/` — путь в команде указывается **относительно корня Laravel** (не абсолютный путь).

```bash
cd ~/public_html   # или каталог, где лежит artisan

/usr/local/bin/php8.3 artisan import:remains-csv ostatki.csv --dry-run
/usr/local/bin/php8.3 artisan import:remains-csv ostatki.csv
# пример с подпапкой:
# php artisan import:remains-csv storage/app/ostatki.csv
```

Если при импорте или `stock:enrich-catalog` ошибка про строку заголовка «Код» / «Артикул», чаще всего файл в **Windows-1251** (типичный экспорт Excel «CSV (разделители — точка с запятой)»). Повторите с:

`--encoding=cp1251`

Обогащение CSV для анализа (только выходной файл, **без записи в БД**):

```bash
/usr/local/bin/php8.3 artisan stock:enrich-catalog ostatki.csv --sleep=200
/usr/local/bin/php8.3 artisan stock:enrich-catalog ostatki.csv --sleep=200 --encoding=cp1251
```

## Каталог Geely «Бамбук»

Исходный CSV лежит в репозитории: **`database/data/catalog_geely_bamboo.csv`**.

- При **`php artisan migrate --seed`** каталог подтягивается сидером `GeelyBambooCatalogSeeder`.
- На проде после деплоя можно только обновить CSV и выполнить:
  ```bash
  php artisan db:seed --class=GeelyBambooCatalogSeeder
  ```
  либо вручную:
  ```bash
  php artisan import:geely-bamboo database/data/catalog_geely_bamboo.csv
  ```

Пропуск строк настраивается в `config/geely_bamboo_import.php`.

## Дубликаты марок авто (Geely / GEELY)

После старых импортов в фильтре могли остаться разные написания одной марки:

```bash
php artisan vehicles:normalize-labels --dry-run
php artisan vehicles:normalize-labels
```

Категории со slug `import-*` на витрине скрыты (`config/storefront.php`).
