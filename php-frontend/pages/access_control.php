<?php
/**
 * Access Control Page
 * @var array $allUsers
 */
$levels = ROLE_LEVELS;
$labels = ROLE_LABELS;
$myLevel = getRoleLevel();
$myRole  = getUserRole();

$search      = $_GET['search']      ?? '';
$filterRole  = $_GET['filter_role'] ?? 'all';
$filterAccess= $_GET['filter_access']?? 'all';
$activeTab   = $_GET['tab']         ?? 'access';

$filtered = array_filter($allUsers, function($u) use ($search, $filterRole, $filterAccess) {
    if ($search) {
        $hay = strtolower(($u['name'] ?? '') . ' ' . ($u['email'] ?? ''));
        if (!str_contains($hay, strtolower($search))) return false;
    }
    if ($filterRole !== 'all' && ($u['role'] ?? 'user') !== $filterRole) return false;
    if ($filterAccess === 'active'   && ($u['disabled'] ?? false)) return false;
    if ($filterAccess === 'disabled' && !($u['disabled'] ?? false)) return false;
    return true;
});

$activeCount   = count(array_filter($allUsers, fn($u) => !($u['disabled'] ?? false)));
$disabledCount = count(array_filter($allUsers, fn($u) => ($u['disabled'] ?? false)));

function canManageUser(array $u): bool {
    global $levels, $myLevel;
    $uLevel = $levels[$u['role'] ?? 'user'] ?? 1;
    return $myLevel > $uLevel;
}

function assignableRolesPHP(): array {
    global $levels, $myLevel, $labels;
    $result = [];
    foreach ($levels as $role => $level) {
        if ($level < $myLevel) $result[$role] = $labels[$role] ?? $role;
    }
    return $result;
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-sn-dark rounded-xl flex items-center justify-center">
                <i data-lucide="key-round" class="w-5 h-5 text-sn-green"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-sn-dark">Access Control</h1>
                <p class="text-sm text-muted-foreground">
                    Control system access and feature permissions ·
                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-orange-100 text-orange-700">
                        <?= htmlspecialchars($labels[$myRole] ?? $myRole) ?>
                    </span>
                </p>
            </div>
        </div>
        <!-- Add Login button -->
        <button onclick="document.getElementById('addLoginModal').classList.remove('hidden')"
            class="flex items-center gap-2 px-4 py-2.5 bg-sn-green text-sn-dark rounded-xl font-bold text-sm hover:opacity-90 transition-opacity shadow-sm">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Add Login
        </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4">
        <div class="card p-4 flex items-center gap-3">
            <i data-lucide="users" class="w-8 h-8 text-sn-dark opacity-70"></i>
            <div><div class="text-2xl font-bold text-sn-dark"><?= count($allUsers) ?></div><div class="text-xs text-muted-foreground">Total Users</div></div>
        </div>
        <div class="card p-4 flex items-center gap-3" style="border-color:#bbf7d0">
            <i data-lucide="shield-check" class="w-8 h-8 text-green-600 opacity-70"></i>
            <div><div class="text-2xl font-bold text-green-600"><?= $activeCount ?></div><div class="text-xs text-muted-foreground">Active</div></div>
        </div>
        <div class="card p-4 flex items-center gap-3" style="border-color:#fecaca">
            <i data-lucide="shield-off" class="w-8 h-8 text-red-600 opacity-70"></i>
            <div><div class="text-2xl font-bold text-red-600"><?= $disabledCount ?></div><div class="text-xs text-muted-foreground">Disabled</div></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-border gap-1">
        <a href="?page=access_control&tab=access"
           class="px-6 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors <?= $activeTab === 'access' ? 'border-sn-green text-sn-dark' : 'border-transparent text-muted-foreground hover:text-foreground' ?>">
            Account Access
        </a>
        <a href="?page=access_control&tab=modules"
           class="px-6 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors <?= $activeTab === 'modules' ? 'border-sn-green text-sn-dark' : 'border-transparent text-muted-foreground hover:text-foreground' ?>">
            Feature Access
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" action="<?= BASE_URL ?>/" class="flex items-center gap-3 flex-wrap">
        <input type="hidden" name="page" value="access_control">
        <input type="hidden" name="tab"  value="<?= htmlspecialchars($activeTab) ?>">
        <div class="relative">
            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search users..."
                class="pl-9 pr-4 py-2 border border-border rounded-lg text-sm w-52 outline-none focus:ring-2 focus:ring-sn-green">
        </div>
        <select name="filter_role" class="p-2 border border-border rounded-lg text-sm outline-none focus:ring-2 focus:ring-sn-green">
            <option value="all">All Roles</option>
            <?php foreach ($labels as $r => $l): ?>
            <option value="<?= $r ?>" <?= $filterRole === $r ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_access" class="p-2 border border-border rounded-lg text-sm outline-none focus:ring-2 focus:ring-sn-green">
            <option value="all"      <?= $filterAccess === 'all'      ? 'selected' : '' ?>>All Access</option>
            <option value="active"   <?= $filterAccess === 'active'   ? 'selected' : '' ?>>Active Only</option>
            <option value="disabled" <?= $filterAccess === 'disabled' ? 'selected' : '' ?>>Disabled Only</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <span class="text-sm text-muted-foreground ml-auto"><?= count($filtered) ?> users</span>
    </form>

    <?php if ($activeTab === 'access'): ?>
    <!-- Account Access Table -->
    <div class="card overflow-hidden p-0">
        <div class="p-4 border-b border-border bg-muted/10 flex items-center justify-between">
            <span class="text-sm font-bold">Account Access Control</span>
            <span class="text-xs text-muted-foreground">Toggle to grant or remove login access</span>
        </div>
        <table class="w-full text-left">
            <thead>
                <tr class="bg-muted/20 border-b border-border text-[10px] font-bold uppercase text-muted-foreground tracking-wide">
                    <th class="p-4">User</th>
                    <th class="p-4">Role</th>
                    <th class="p-4 text-center">Status</th>
                    <th class="p-4 text-center">Toggle Access</th>
                    <th class="p-4">Change Role</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <?php if (empty($filtered)): ?>
                <tr><td colspan="5" class="p-8 text-center text-muted-foreground">No users found.</td></tr>
                <?php else: foreach ($filtered as $u):
                    $uRole     = $u['role'] ?? 'user';
                    $uLevel    = $levels[$uRole] ?? 1;
                    $isMe      = ($u['uid'] ?? $u['id']) === (getCurrentUser()['uid'] ?? '');
                    $canEdit   = !$isMe && $myLevel > $uLevel;
                    $isDisabled= $u['disabled'] ?? false;
                    $uid       = $u['uid'] ?? $u['id'];
                ?>
                <tr class="<?= $isDisabled ? 'bg-red-50/30' : 'hover:bg-muted/5' ?> transition-colors">
                    <td class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold bg-orange-100 text-orange-700">
                                <?= strtoupper(substr($u['name'] ?? $u['email'] ?? '?', 0, 1)) ?>
                            </div>
                            <div>
                                <div class="font-semibold text-sm <?= $isDisabled ? 'line-through text-muted-foreground' : '' ?>">
                                    <?= htmlspecialchars($u['name'] ?? '—') ?>
                                </div>
                                <div class="text-xs text-muted-foreground"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                                <?php if ($isMe): ?><div class="text-[10px] text-sn-green font-bold">You</div><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="p-4">
                        <span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-orange-100 text-orange-700">
                            <?= htmlspecialchars($labels[$uRole] ?? $uRole) ?>
                        </span>
                    </td>
                    <td class="p-4 text-center">
                        <?php if ($isDisabled): ?>
                        <span class="inline-flex items-center gap-1 text-red-600 text-xs font-bold bg-red-50 px-2 py-1 rounded-full border border-red-200">
                            <i data-lucide="shield-off" class="w-3 h-3"></i> Disabled
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-green-700 text-xs font-bold bg-green-50 px-2 py-1 rounded-full border border-green-200">
                            <i data-lucide="shield-check" class="w-3 h-3"></i> Active
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-center">
                        <?php if ($isMe): ?>
                        <span class="text-xs text-muted-foreground italic">Your account</span>
                        <?php elseif (!$canEdit): ?>
                        <span class="text-xs text-muted-foreground italic">No permission</span>
                        <?php else: ?>
                        <form method="POST" action="<?= BASE_URL ?>/?page=access_control" class="inline">
                            <input type="hidden" name="target_uid"    value="<?= htmlspecialchars($uid) ?>">
                            <input type="hidden" name="access_action" value="<?= $isDisabled ? 'grant' : 'remove' ?>">
                            <button type="submit"
                                class="<?= $isDisabled ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' ?> text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5 mx-auto">
                                <i data-lucide="<?= $isDisabled ? 'unlock' : 'lock' ?>" class="w-3.5 h-3.5"></i>
                                <?= $isDisabled ? 'Grant Access' : 'Remove Access' ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <?php if ($canEdit): ?>
                        <form method="POST" action="<?= BASE_URL ?>/?page=access_control" class="flex items-center gap-2">
                            <input type="hidden" name="target_uid"    value="<?= htmlspecialchars($uid) ?>">
                            <input type="hidden" name="access_action" value="role">
                            <select name="new_role" class="p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green">
                                <?php foreach (assignableRolesPHP() as $r => $l): ?>
                                <option value="<?= $r ?>" <?= $uRole === $r ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary text-xs px-2 py-1">Save</button>
                        </form>
                        <?php else: ?>
                        <span class="text-xs text-muted-foreground italic">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <!-- Feature Access -->
    <?php
    $modules = [
        ['key'=>'tickets',             'label'=>'Tickets / Incidents',  'icon'=>'ticket',         'group'=>'Service Desk'],
        ['key'=>'conversations',       'label'=>'Conversations',         'icon'=>'message-square', 'group'=>'Service Desk'],
        ['key'=>'catalog',             'label'=>'Service Catalog',       'icon'=>'shopping-cart',  'group'=>'Service Desk'],
        ['key'=>'kb',                  'label'=>'Knowledge Base',        'icon'=>'book-open',      'group'=>'Service Desk'],
        ['key'=>'approvals',           'label'=>'My Approvals',          'icon'=>'check-square',   'group'=>'Service Desk'],
        ['key'=>'history',             'label'=>'System Activity',       'icon'=>'history',        'group'=>'Service Desk'],
        ['key'=>'timesheet',           'label'=>'Timesheet',             'icon'=>'clock',          'group'=>'Timesheet'],
        ['key'=>'timesheet_reports',   'label'=>'Timesheet Reports',     'icon'=>'bar-chart-2',    'group'=>'Timesheet'],
        ['key'=>'timesheet_approvals', 'label'=>'Timesheet Approvals',   'icon'=>'clipboard-list', 'group'=>'Timesheet'],
        ['key'=>'problem',             'label'=>'Problem Management',    'icon'=>'alert-octagon',  'group'=>'ITSM'],
        ['key'=>'change',              'label'=>'Change Management',     'icon'=>'git-pull-request','group'=>'ITSM'],
        ['key'=>'cmdb',                'label'=>'CMDB Assets',           'icon'=>'database',       'group'=>'ITSM'],
        ['key'=>'reports',             'label'=>'Reports & Analytics',   'icon'=>'bar-chart-3',    'group'=>'Reports'],
        ['key'=>'sla',                 'label'=>'SLA Policies',          'icon'=>'shield',         'group'=>'Admin'],
        ['key'=>'users',               'label'=>'User Management',       'icon'=>'users',          'group'=>'Admin'],
        ['key'=>'settings',            'label'=>'System Settings',       'icon'=>'settings',       'group'=>'Admin'],
        ['key'=>'access_control',      'label'=>'Access Control',        'icon'=>'key-round',      'group'=>'Admin'],
        ['key'=>'approved_tickets',    'label'=>'Approved Tickets',      'icon'=>'check-circle-2', 'group'=>'Admin'],
    ];
    $groups = array_unique(array_column($modules, 'group'));
    ?>
    <div class="space-y-4">
        <div class="bg-blue-50 border border-blue-200 text-blue-700 p-3 rounded-lg text-sm flex items-start gap-2">
            <i data-lucide="key-round" class="w-4 h-4 mt-0.5 shrink-0"></i>
            <span>Click a user to expand and toggle individual feature access. <strong>Green = allowed, Gray = restricted.</strong></span>
        </div>
        <?php foreach ($filtered as $u):
            $uid       = $u['uid'] ?? $u['id'];
            $uRole     = $u['role'] ?? 'user';
            $uLevel    = $levels[$uRole] ?? 1;
            $isMe      = $uid === (getCurrentUser()['uid'] ?? '');
            $canEdit   = !$isMe && $myLevel > $uLevel;
            $restricted= $u['restrictedModules'] ?? [];
            $isDisabled= $u['disabled'] ?? false;
            $expandKey = 'expand_' . $uid;
            $isExpanded= isset($_GET[$expandKey]);
        ?>
        <div class="card overflow-hidden p-0">
            <a href="?page=access_control&tab=modules&<?= $expandKey ?>=1&search=<?= urlencode($search) ?>&filter_role=<?= urlencode($filterRole) ?>"
               class="flex items-center gap-4 p-4 hover:bg-muted/5 transition-colors">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold bg-orange-100 text-orange-700 flex-shrink-0">
                    <?= strtoupper(substr($u['name'] ?? '?', 0, 1)) ?>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-sm <?= $isDisabled ? 'line-through text-muted-foreground' : '' ?>">
                            <?= htmlspecialchars($u['name'] ?? '—') ?>
                        </span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-orange-100 text-orange-700">
                            <?= htmlspecialchars($labels[$uRole] ?? $uRole) ?>
                        </span>
                        <?php if ($isDisabled): ?>
                        <span class="text-[10px] font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full border border-red-200">Account Disabled</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-muted-foreground mt-0.5"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                </div>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <span><?= count($restricted) > 0 ? count($restricted) . ' restricted' : 'Full access' ?></span>
                    <i data-lucide="<?= $isExpanded ? 'chevron-up' : 'chevron-right' ?>" class="w-4 h-4"></i>
                </div>
            </a>

            <?php if ($isExpanded): ?>
            <div class="border-t border-border p-4 bg-muted/5">
                <?php if (!$canEdit): ?>
                <p class="text-sm text-muted-foreground italic text-center py-4">No permission to modify this user's feature access.</p>
                <?php else: ?>
                <?php foreach ($groups as $group):
                    $groupModules = array_filter($modules, fn($m) => $m['group'] === $group);
                ?>
                <div class="mb-4">
                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider mb-2 flex items-center gap-2">
                        <div class="h-px flex-grow bg-border"></div><?= $group ?><div class="h-px flex-grow bg-border"></div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                        <?php foreach ($groupModules as $mod):
                            $isAllowed = !in_array($mod['key'], $restricted);
                        ?>
                        <form method="POST" action="<?= BASE_URL ?>/?page=access_control&tab=modules&<?= $expandKey ?>=1">
                            <input type="hidden" name="target_uid"    value="<?= htmlspecialchars($uid) ?>">
                            <input type="hidden" name="access_action" value="module_toggle">
                            <input type="hidden" name="module_key"    value="<?= $mod['key'] ?>">
                            <input type="hidden" name="module_allow"  value="<?= $isAllowed ? '0' : '1' ?>">
                            <button type="submit"
                                class="w-full flex items-center justify-between p-3 rounded-lg border transition-colors <?= $isAllowed ? 'bg-green-50 border-green-200 hover:bg-green-100' : 'bg-gray-50 border-gray-200 hover:bg-gray-100' ?>">
                                <div class="flex items-center gap-2 min-w-0">
                                    <i data-lucide="<?= $mod['icon'] ?>" class="w-4 h-4 flex-shrink-0 <?= $isAllowed ? 'text-green-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-xs font-medium truncate <?= !$isAllowed ? 'text-muted-foreground' : '' ?>"><?= $mod['label'] ?></span>
                                </div>
                                <div class="<?= $isAllowed ? 'bg-sn-green' : 'bg-gray-300' ?> w-9 h-5 rounded-full relative flex-shrink-0 ml-2">
                                    <div class="absolute top-0.5 <?= $isAllowed ? 'right-0.5' : 'left-0.5' ?> w-4 h-4 bg-white rounded-full shadow transition-all"></div>
                                </div>
                            </button>
                        </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="flex justify-end gap-2 pt-2 border-t border-border">
                    <form method="POST" action="<?= BASE_URL ?>/?page=access_control&tab=modules&<?= $expandKey ?>=1" class="inline">
                        <input type="hidden" name="target_uid"    value="<?= htmlspecialchars($uid) ?>">
                        <input type="hidden" name="access_action" value="restrict_all">
                        <button type="submit" class="px-3 py-1.5 text-xs font-bold border border-red-200 text-red-600 rounded-lg hover:bg-red-50">Restrict All</button>
                    </form>
                    <form method="POST" action="<?= BASE_URL ?>/?page=access_control&tab=modules&<?= $expandKey ?>=1" class="inline">
                        <input type="hidden" name="target_uid"    value="<?= htmlspecialchars($uid) ?>">
                        <input type="hidden" name="access_action" value="allow_all">
                        <button type="submit" class="px-3 py-1.5 text-xs font-bold border border-green-200 text-green-700 rounded-lg hover:bg-green-50">Allow All</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add Login Modal -->
<div id="addLoginModal" class="modal-backdrop hidden">
    <div class="modal" style="max-width:480px;">
        <div class="p-5 border-b border-border flex items-center justify-between bg-sn-dark text-white">
            <div class="flex items-center gap-3">
                <i data-lucide="user-plus" class="w-5 h-5 text-sn-green"></i>
                <div><div class="font-bold">Add New Login</div><div class="text-xs text-white/60">Create a new user account</div></div>
            </div>
            <button onclick="document.getElementById('addLoginModal').classList.add('hidden')" class="p-1 hover:bg-white/10 rounded">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/?page=access_control" class="p-6 space-y-4">
            <input type="hidden" name="target_uid"    value="new">
            <input type="hidden" name="access_action" value="create_user">
            <div>
                <label class="text-xs font-bold text-muted-foreground uppercase tracking-wider block mb-1.5">Full Name *</label>
                <input type="text" name="new_name" required placeholder="John Doe"
                    class="w-full p-3 border border-border rounded-lg text-sm focus:ring-2 focus:ring-sn-green outline-none">
            </div>
            <div>
                <label class="text-xs font-bold text-muted-foreground uppercase tracking-wider block mb-1.5">Email Address *</label>
                <input type="email" name="new_email" required placeholder="name@company.com"
                    class="w-full p-3 border border-border rounded-lg text-sm focus:ring-2 focus:ring-sn-green outline-none">
            </div>
            <div>
                <label class="text-xs font-bold text-muted-foreground uppercase tracking-wider block mb-1.5">Password *</label>
                <input type="password" name="new_password" required placeholder="Min. 6 characters" minlength="6"
                    class="w-full p-3 border border-border rounded-lg text-sm focus:ring-2 focus:ring-sn-green outline-none">
            </div>
            <div>
                <label class="text-xs font-bold text-muted-foreground uppercase tracking-wider block mb-1.5">Role *</label>
                <select name="new_role" class="w-full p-3 border border-border rounded-lg text-sm focus:ring-2 focus:ring-sn-green outline-none">
                    <?php foreach (assignableRolesPHP() as $r => $l): ?>
                    <option value="<?= $r ?>"><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-muted-foreground mt-1">You can only assign roles below your level</p>
            </div>
            <div class="flex justify-end gap-3 pt-2 border-t border-border">
                <button type="button" onclick="document.getElementById('addLoginModal').classList.add('hidden')"
                    class="px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-muted transition-colors">Cancel</button>
                <button type="submit" class="flex items-center gap-2 px-5 py-2 bg-sn-green text-sn-dark rounded-lg text-sm font-bold hover:opacity-90">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> Create Login
                </button>
            </div>
        </form>
    </div>
</div>

<script>
lucide.createIcons();
document.getElementById('addLoginModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>
