<?php

function adminTableState(array $rows, array $searchFields, string $key, int $perPage = 10): array
{
    $search = trim((string) ($_GET[$key . '_search'] ?? ''));
    $page = max(1, (int) ($_GET[$key . '_page'] ?? 1));

    if ($search !== '') {
        $rows = array_values(array_filter($rows, static function (array $row) use ($searchFields, $search): bool {
            foreach ($searchFields as $field) {
                $value = $row;
                foreach (explode('.', $field) as $part) {
                    if (!is_array($value) || !array_key_exists($part, $value)) {
                        $value = '';
                        break;
                    }
                    $value = $value[$part];
                }

                if (mb_stripos((string) $value, $search, 0, 'UTF-8') !== false) {
                    return true;
                }
            }
            return false;
        }));
    }

    $total = count($rows);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);

    return [
        'rows' => array_slice($rows, ($page - 1) * $perPage, $perPage),
        'search' => $search,
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'from' => $total ? (($page - 1) * $perPage) + 1 : 0,
        'to' => min($page * $perPage, $total),
    ];
}

function adminTableToolbar(string $key, array $state, string $placeholder = 'Search...'): void
{
    ?>
    <div class="px-6 py-4 border-b border-slate-100 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <label class="relative block w-full sm:max-w-sm">
            <span class="sr-only"><?= htmlspecialchars($placeholder) ?></span>
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
            <input type="search"
                   value="<?= htmlspecialchars($state['search']) ?>"
                   placeholder="<?= htmlspecialchars($placeholder) ?>"
                   data-admin-table-search="<?= htmlspecialchars($key) ?>"
                   class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-4 text-sm text-[#13324a] outline-none transition focus:border-[#1d5f8c] focus:bg-white focus:ring-2 focus:ring-[#1d5f8c]/10">
        </label>
        <p class="text-xs font-semibold text-slate-500" data-admin-table-info="<?= htmlspecialchars($key) ?>">
            Showing <?= (int) $state['from'] ?>–<?= (int) $state['to'] ?> of <?= (int) $state['total'] ?>
        </p>
    </div>
    <?php
}

function adminTablePagination(string $key, array $state): void
{
    ?>
    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between gap-3" data-admin-table-pagination="<?= htmlspecialchars($key) ?>">
        <button type="button" data-admin-table-page="<?= max(1, (int) $state['page'] - 1) ?>" <?= $state['page'] <= 1 ? 'disabled' : '' ?> class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-[#1d5f8c] hover:text-[#1d5f8c] disabled:cursor-not-allowed disabled:opacity-40">Previous</button>
        <span class="text-xs font-bold text-slate-500">Page <?= (int) $state['page'] ?> of <?= (int) $state['pages'] ?></span>
        <button type="button" data-admin-table-page="<?= min((int) $state['pages'], (int) $state['page'] + 1) ?>" <?= $state['page'] >= $state['pages'] ? 'disabled' : '' ?> class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-[#1d5f8c] hover:text-[#1d5f8c] disabled:cursor-not-allowed disabled:opacity-40">Next</button>
    </div>
    <?php
}

