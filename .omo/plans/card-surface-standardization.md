# Filament Admin Card Surface Standardization

## Context

**Request:** Standardize the light/dark card foundation across Filament admin statistics, chart sections, and finance tables without changing data, authorization, pagination, or Chart.js configuration.

**Audited files:**

- `resources/views/filament/components/ui-polish.blade.php`
- `resources/views/filament/pages/neraca.blade.php`
- `resources/views/filament/pages/piutang.blade.php`
- `resources/views/filament/pages/hutang.blade.php`

**Decision:** Replace the near-black stats surface (`rgb(9, 9, 11)`) and slate chart gradients with one neutral, tokenized card surface. Preserve semantic danger, success, info, warning, and primary colors for content-level emphasis.

**Out of scope:** Customer portal, public invoices, sidebar/modal/profile treatment, PHP/data-query changes, Chart.js datasets and axes, authorization, pagination, and dependencies.

## Surface Contract

Define CSS variables in `ui-polish.blade.php`:

| Token | Light | Dark |
|---|---|---|
| `--admin-surface-card` | `#ffffff` | `#18181b` |
| `--admin-surface-card-muted` | `#f8fafc` | `#27272a` |
| `--admin-border-subtle` | `rgba(148, 163, 184, 0.22)` | `rgba(255, 255, 255, 0.10)` |
| `--admin-border-strong` | `rgba(148, 163, 184, 0.32)` | `rgba(255, 255, 255, 0.16)` |
| `--admin-text-muted` | `rgb(100, 116, 139)` | `rgb(148, 163, 184)` |
| `--admin-table-hover` | `rgb(248, 250, 252)` | `rgba(255, 255, 255, 0.05)` |
| `--admin-shadow-card` | restrained slate shadow | `0 12px 30px rgba(0, 0, 0, 0.24)` |

Use solid surfaces for standardized card containers. Do not change semantic color utilities or content hierarchy.

## Execution Order

### 1. Establish shared tokens

**File:** `resources/views/filament/components/ui-polish.blade.php`

- Add the light and dark CSS custom properties once.
- Remove the near-black stats background from the standardized card path.
- Keep existing typography, icon, semantic status, sizing, and responsive rules intact.

**Acceptance:** Every standardized dark card resolves through the shared surface token; `rgb(9, 9, 11)` is gone.

### 2. Apply the shared card system globally

**File:** `resources/views/filament/components/ui-polish.blade.php`

- Make stats cards, dashboard chart sections, and explicitly hooked finance sections consume the same card background, border, radius, and shadow.
- Preserve chart sizing and hover lift, but remove its independent gradient surface.
- Add scoped table styling for headers, dividers, hover rows, empty state, and pagination divider.
- Avoid broad global styling that affects sidebar, modal, or profile-specific visual treatment.

**Acceptance:** Dashboard stats, charts, and finance sections all use the same foundational surface in each theme.

### 3. Align Neraca

**File:** `resources/views/filament/pages/neraca.blade.php`

- Add a semantic class to the filter/period bar so it consumes the shared surface contract.
- Keep `fi-wi-stats-overview-stat` cards and all existing values, conditions, icon colors, quantity badges, and breakdown logic unchanged.

**Acceptance:** The filter bar and six summary cards belong to the same card family without altering financial output.

### 4. Align Piutang and Hutang

**Files:**

- `resources/views/filament/pages/piutang.blade.php`
- `resources/views/filament/pages/hutang.blade.php`

- Add semantic classes to filter bars, summary sections, chart sections, table-owning sections, and plain tables.
- Centralize card/table appearance in `ui-polish.blade.php`; do not add page-specific color systems.
- Keep table alignment, status badges, danger/success emphasis, role guard, pagination, and all Chart.js code exactly as-is.

**Acceptance:** Piutang and Hutang use equivalent card/table treatment in light and dark themes without behavioral changes.

### 5. Verify

Run:

```powershell
php artisan test
php artisan view:cache
php artisan optimize:clear
```

Then inspect the changed Blade/CSS diff to confirm:

- no `$data`, query, conditional, number formatting, authorization, pagination, or Chart.js edits;
- no standardized dark card selector keeps `rgb(9, 9, 11)`;
- stats, chart, finance sections, and finance tables consume the token contract.

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Filament DOM selector changes | Restrict selectors to audited existing classes and semantic hooks added in local views. |
| Over-broad section styling | Target dashboard and `.he-finance-*` hooks only. |
| Loss of semantic hierarchy | Keep all danger/success/info/warning utilities untouched. |
| Contrast regressions | Use shared muted-text, divider, and hover tokens for dark surfaces. |
| Chart regressions | Treat all Chart.js configuration and CDN sources as read-only. |

## Completion Criteria

- A single tokenized solid-surface foundation controls standardized Filament admin cards.
- Light/dark stats, chart sections, finance sections, and finance tables are visually coherent.
- The existing data and interaction behavior of Neraca, Piutang, and Hutang is unchanged.
- Laravel tests and compiled view cache pass.
