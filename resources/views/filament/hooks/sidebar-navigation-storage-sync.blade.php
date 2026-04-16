{{-- Filament хранит свёрнутость групп в localStorage.collapsedGroups; после смены дефолтов в PHP нужно сбросить один раз. Поднимайте VERSION при изменении желаемого «дефолта». --}}
<script>
    (function () {
        try {
            var VERSION = '3-finance-collapsed-default';
            var key = 'vsedetalki_ru_filament_collapsed_groups_storage_v';
            if (localStorage.getItem(key) !== VERSION) {
                localStorage.removeItem('collapsedGroups');
                localStorage.setItem(key, VERSION);
            }
        } catch (e) {}
    })();
</script>
