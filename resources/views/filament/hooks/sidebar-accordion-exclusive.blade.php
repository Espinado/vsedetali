{{-- Один открытый раздел бокового меню: при раскрытии группы остальные сворачиваются (поверх Filament toggleCollapsedGroup). --}}
<script>
    document.addEventListener('alpine:init', function () {
        queueMicrotask(function () {
            if (typeof window.Alpine === 'undefined') {
                return;
            }

            var sidebar = null;
            try {
                sidebar = window.Alpine.store('sidebar');
            } catch (e) {
                return;
            }

            if (!sidebar || typeof sidebar.toggleCollapsedGroup !== 'function') {
                return;
            }

            var original = sidebar.toggleCollapsedGroup.bind(sidebar);

            sidebar.toggleCollapsedGroup = function (group) {
                var nav = document.querySelector('.fi-sidebar-nav-groups');
                var allGroups = nav
                    ? Array.from(nav.querySelectorAll(':scope > .fi-sidebar-group'))
                        .map(function (el) {
                            return el.dataset.groupLabel;
                        })
                        .filter(Boolean)
                    : [];

                if (!allGroups.length) {
                    return original(group);
                }

                var collapsed = sidebar.collapsedGroups;
                if (!Array.isArray(collapsed)) {
                    collapsed = [];
                }

                var wasCollapsed = collapsed.includes(group);

                if (wasCollapsed) {
                    sidebar.collapsedGroups = allGroups.filter(function (g) {
                        return g !== group;
                    });
                } else {
                    original(group);
                }
            };
        });
    });
</script>
