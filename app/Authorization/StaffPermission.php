<?php

namespace App\Authorization;

/**
 * Разрешения guard {@see Staff} (панель /admin).
 */
final class StaffPermission
{
    public const CATALOG_MANAGE = 'catalog.manage';

    public const WAREHOUSE_MANAGE = 'warehouse.manage';

    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_EDIT = 'orders.edit';

    public const SHIPMENTS_MANAGE = 'shipments.manage';

    public const CHAT_MANAGE = 'chat.manage';

    public const FINANCE_VIEW = 'finance.view';

    public const CONTENT_MANAGE = 'content.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const CUSTOMERS_VIEW = 'customers.view';

    public const STAFF_MANAGE = 'staff.manage';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CATALOG_MANAGE,
            self::WAREHOUSE_MANAGE,
            self::ORDERS_VIEW,
            self::ORDERS_EDIT,
            self::SHIPMENTS_MANAGE,
            self::CHAT_MANAGE,
            self::FINANCE_VIEW,
            self::CONTENT_MANAGE,
            self::SETTINGS_MANAGE,
            self::CUSTOMERS_VIEW,
            self::STAFF_MANAGE,
        ];
    }
}
