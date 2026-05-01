<?php
/**
 * Base Layout Template
 * Replicates the React app layout with sidebar and main content
 */

require_once __DIR__ . '/config.php';

function renderLayout(string $title, callable $contentCallback, ?array $extraData = null): void {
    $user = getCurrentUser();
    $role = getUserRole();
    $isAdmin = isAdmin();
    $isAgent = isAgent();
    
    // Get current page for active state
    $currentPage = $_GET['page'] ?? 'dashboard';
    
    // Calculate open tickets badge (in real app, query Firestore)
    $openTicketsCount = 0;
    
    // Flash messages
    $flash = getFlash();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Connect IT</title>
    <!-- Apply dark mode before render to avoid flash -->
    <script>if(localStorage.getItem('sn-dark-mode')==='true')document.documentElement.classList.add('dark');</script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <!-- Lucide Icons via CDN -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-gray-50">
    <?php if (isLoggedIn()): ?>
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="bg-sn-sidebar text-white flex flex-col h-screen sticky top-0 transition-all duration-300 border-r border-white/10 w-64">
            <!-- Header -->
            <div class="p-4 flex items-center justify-between border-b border-white/10 h-16">
                <div class="flex items-center gap-2" id="sidebar-logo">
                    <div class="w-8 h-8 bg-sn-green rounded flex items-center justify-center font-bold text-sn-dark">C</div>
                    <span class="text-xl font-bold tracking-tight">Connect</span>
                </div>
                <button onclick="toggleSidebar()" class="p-1.5 hover:bg-white/10 rounded transition-colors">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Filter Navigator -->
            <div class="p-4" id="sidebar-search">
                <div class="relative group">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-text-dim group-focus-within:text-sn-green transition-colors"></i>
                    <input 
                        type="text"
                        placeholder="Filter navigator"
                        onkeyup="filterMenu(this.value)"
                        class="w-full bg-white/5 border border-white/10 rounded py-2 pl-9 pr-3 text-sm outline-none focus:ring-1 focus:ring-sn-green focus:bg-white/10 transition-all"
                    />
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-grow overflow-y-auto py-2" id="sidebar-nav">
                <?php
                $menuSections = [
                    [
                        'label' => 'Favorites',
                        'items' => [
                            ['icon' => 'layout-dashboard', 'label' => $isAgent ? 'Incident Dashboard' : 'Service Portal', 'page' => 'dashboard'],
                            ['icon' => 'message-square', 'label' => 'Conversations', 'page' => 'conversations'],
                            ['icon' => 'clock', 'label' => 'My Timesheet', 'page' => 'timesheet'],
                            ['icon' => 'bar-chart-2', 'label' => 'Timesheet Reports', 'page' => 'timesheet_reports'],
                        ]
                    ],
                    [
                        'label' => 'Timesheet',
                        'items' => [
                            ['icon' => 'clock', 'label' => 'My Timesheet', 'page' => 'timesheet'],
                            ['icon' => 'bar-chart-2', 'label' => 'Timesheet Reports', 'page' => 'timesheet_reports'],
                        ]
                    ],
                    [
                        'label' => 'Service Desk',
                        'items' => [
                            ['icon' => 'shopping-cart', 'label' => 'Service Catalog', 'page' => 'catalog'],
                            ['icon' => 'book-open',     'label' => 'Knowledge Base',  'page' => 'kb'],
                            ['icon' => 'clipboard-list','label' => 'Tasks',           'page' => 'tasks'],
                            ['icon' => 'help-circle',   'label' => 'My Approvals',    'page' => 'approvals'],
                            ['icon' => 'history',       'label' => 'System Activity', 'page' => 'history'],
                        ]
                    ],
                    [
                        'label' => 'Incident',
                        'items' => [
                            ['icon' => 'plus-circle',   'label' => 'Create New Incident',  'page' => 'tickets', 'params' => 'action=new'],
                            ['icon' => 'user-check',    'label' => 'Assigned to Me',        'page' => 'my_tickets'],
                            ['icon' => 'folder-open',   'label' => 'Open Incidents',        'page' => 'tickets', 'params' => 'filter=open', 'badge' => $openTicketsCount],
                            ['icon' => 'user-minus',    'label' => 'Open - Unassigned',     'page' => 'tickets', 'params' => 'filter=unassigned'],
                            ['icon' => 'check-circle',  'label' => 'Resolved',              'page' => 'tickets', 'params' => 'filter=resolved'],
                            ['icon' => 'check-circle-2','label' => 'Approved Tickets',      'page' => 'approved_tickets'],
                            ['icon' => 'list',          'label' => 'All Incidents',         'page' => 'tickets'],
                            ['icon' => 'map',           'label' => 'Critical Map',          'page' => 'reports'],
                        ]
                    ],
                    [
                        'label' => 'Problem & Change',
                        'items' => [
                            ['icon' => 'alert-octagon', 'label' => 'Problem Management', 'page' => 'problem'],
                            ['icon' => 'git-pull-request', 'label' => 'Change Management', 'page' => 'change'],
                        ]
                    ],
                    [
                        'label' => 'IT Infrastructure',
                        'items' => [
                            ['icon' => 'database', 'label' => 'CMDB Assets', 'page' => 'cmdb'],
                        ]
                    ],
                ];

                if ($isAdmin) {
                    $menuSections[] = [
                        'label' => 'System Administration',
                        'items' => [
                            ['icon' => 'users',         'label' => 'User Management',      'page' => 'users'],
                            ['icon' => 'key-round',     'label' => 'Access Control',        'page' => 'access_control'],
                            ['icon' => 'clock',         'label' => 'SLA Policies',          'page' => 'sla'],
                            ['icon' => 'settings',      'label' => 'Configuration',         'page' => 'settings'],
                            ['icon' => 'check-square',  'label' => 'Timesheet Approvals',   'page' => 'timesheet_approvals'],
                        ]
                    ];
                }

                foreach ($menuSections as $section):
                    // Create a safe ID (no spaces or special chars)
                    $sectionId = preg_replace('/[^a-z0-9]/', '-', strtolower($section['label']));
                ?>
                <div class="mb-1 menu-section" data-label="<?= strtolower($section['label']) ?>">
                    <button onclick="toggleSection('<?= $sectionId ?>')" class="w-full flex items-center justify-between px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-text-dim hover:text-white transition-colors group">
                        <span><?= $section['label'] ?></span>
                        <i data-lucide="chevron-down" class="w-3 h-3 transition-transform" id="icon-<?= $sectionId ?>"></i>
                    </button>
                    <div id="section-<?= $sectionId ?>" style="display:block;">
                        <?php foreach ($section['items'] as $item): 
                            $url = BASE_URL . '/?page=' . $item['page'];
                            if (isset($item['params'])) {
                                $url .= '&' . $item['params'];
                            }
                            $isActive = $currentPage === $item['page'];
                        ?>
                        <a href="<?= $url ?>" class="flex items-center gap-3 px-4 py-2.5 transition-all relative group menu-item <?= $isActive ? 'active-menu-item' : 'text-text-dim hover:bg-white/5 hover:text-white' ?>" data-label="<?= strtolower($item['label']) ?>">
                            <i data-lucide="<?= $item['icon'] ?>" class="w-4 h-4 shrink-0"></i>
                            <span class="text-sm truncate flex-grow"><?= $item['label'] ?></span>
                            <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                            <span class="bg-sn-green text-sn-dark text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[1.25rem] text-center"><?= $item['badge'] ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </nav>

            <!-- Footer -->
            <div class="p-4 border-t border-white/10 space-y-2">
                <div class="px-4 py-2 text-xs text-text-dim">
                    <div class="truncate"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
                    <div class="text-[10px] opacity-70"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                </div>
                <button onclick="toggleDarkMode()" id="darkModeBtn" class="flex items-center gap-3 px-4 py-2.5 w-full text-text-dim hover:text-white transition-colors rounded hover:bg-white/5">
                    <i data-lucide="sun" class="w-4 h-4" id="darkModeIcon"></i>
                    <span class="text-sm" id="darkModeLabel">Light Mode</span>
                </button>
                <a href="<?= BASE_URL ?>/?page=logout" class="flex items-center gap-3 px-4 py-2.5 w-full text-text-dim hover:text-white transition-colors rounded hover:bg-white/5">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    <span class="text-sm">Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-grow flex flex-col overflow-hidden">
            <!-- Top Navbar -->
            <header class="h-16 bg-white border-b border-border flex items-center justify-between px-6 sticky top-0 z-40">
                <div class="flex items-center gap-4">
                    <h2 class="text-lg font-semibold text-sn-dark"><?= htmlspecialchars($title) ?></h2>
                </div>
                <div class="flex items-center gap-4">
                    <button class="p-2 text-muted-foreground hover:text-foreground transition-colors">
                        <i data-lucide="search" class="w-5 h-5"></i>
                    </button>
                    <button class="p-2 text-muted-foreground hover:text-foreground transition-colors relative">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    </button>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-sn-green flex items-center justify-center text-sn-dark font-bold text-sm">
                            <?= substr($user['name'] ?? 'U', 0, 1) ?>
                        </div>
                        <span class="text-sm font-medium hidden md:block"><?= htmlspecialchars($user['name'] ?? 'User') ?></span>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-grow p-6 overflow-y-auto bg-background">
                <?php if ($flash): ?>
                <div class="mb-4 p-4 rounded-lg <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
                <?php endif; ?>

                <?php $contentCallback($extraData); ?>
            </main>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Sidebar toggle
        let sidebarCollapsed = false;
        function toggleSidebar() {
            sidebarCollapsed = !sidebarCollapsed;
            const sidebar = document.getElementById('sidebar');
            const logo = document.getElementById('sidebar-logo');
            const search = document.getElementById('sidebar-search');
            
            if (sidebarCollapsed) {
                sidebar.classList.remove('w-64');
                sidebar.classList.add('w-16');
                logo.style.display = 'none';
                search.style.display = 'none';
            } else {
                sidebar.classList.remove('w-16');
                sidebar.classList.add('w-64');
                logo.style.display = 'flex';
                search.style.display = 'block';
            }
        }

        // Section toggle — sections start open, toggle on click
        function toggleSection(id) {
            const section = document.getElementById('section-' + id);
            const icon = document.getElementById('icon-' + id);
            const isHidden = section.style.display === 'none';
            section.style.display = isHidden ? 'block' : 'none';
            icon.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(-90deg)';
        }

        // Menu filter
        function filterMenu(query) {
            const items = document.querySelectorAll('.menu-item');
            const sections = document.querySelectorAll('.menu-section');
            const lowerQuery = query.toLowerCase();
            
            items.forEach(item => {
                const label = item.getAttribute('data-label');
                item.style.display = label.includes(lowerQuery) ? 'flex' : 'none';
            });

            sections.forEach(section => {
                const visibleItems = section.querySelectorAll('.menu-item[style="display: flex;"], .menu-item:not([style*="display: none"])');
                section.style.display = visibleItems.length > 0 ? 'block' : 'none';
            });
        }

        // Dark mode — persisted in localStorage
        (function() {
            if (localStorage.getItem('sn-dark-mode') === 'true') {
                document.documentElement.classList.add('dark');
            }
        })();

        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('sn-dark-mode', isDark);
            const icon = document.getElementById('darkModeIcon');
            const label = document.getElementById('darkModeLabel');
            if (isDark) {
                icon.setAttribute('data-lucide', 'moon');
                if (label) label.textContent = 'Dark Mode';
            } else {
                icon.setAttribute('data-lucide', 'sun');
                if (label) label.textContent = 'Light Mode';
            }
            lucide.createIcons();
        }

        // Sync button label on load
        window.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('sn-dark-mode') === 'true') {
                const icon = document.getElementById('darkModeIcon');
                const label = document.getElementById('darkModeLabel');
                if (icon) icon.setAttribute('data-lucide', 'moon');
                if (label) label.textContent = 'Dark Mode';
                lucide.createIcons();
            }
        });
    </script>
    <?php else: ?>
    <!-- Not logged in - show only content -->
    <main>
        <?php if ($flash): ?>
        <div class="m-4 p-4 rounded-lg <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>
        <?php $contentCallback($extraData); ?>
    </main>
    <?php endif; ?>
</body>
</html>
    <?php
}
