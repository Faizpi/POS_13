<?php

declare(strict_types=1);

namespace App\Filament\Pages\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\AccountCreationOptions;
use App\Accounting\MappingKey;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Services\Accounting\AccountCodeGenerator;
use App\Services\Accounting\AccountingAuthorization;
use App\Services\Accounting\AccountMappingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class PemetaanAkunPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|UnitEnum|null $navigationGroup = 'Akuntansi';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Pemetaan Akun';

    protected static ?string $title = 'Pemetaan Akun';

    protected static ?string $slug = 'pemetaan-akun';

    protected string $view = 'filament.pages.accounting.pemetaan-akun';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && app(AccountingAuthorization::class)->canViewConfig($user);
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->fill(['mappings' => $this->mappingState()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->mappingSections())
            ->statePath('data');
    }

    public function saveMappings(): void
    {
        $this->assertCanManage();
        $data = $this->form->getState();
        $service = app(AccountMappingService::class);

        foreach ($data['mappings'] ?? [] as $stateKey => $mappingData) {
            $accountId = $mappingData['account_id'] ?? null;

            if ($accountId === null) {
                continue;
            }

            $key = $this->keyFromStateKey($stateKey);
            $service->replaceForEffectiveFrom(
                actor: auth()->user(),
                key: $key,
                account: Account::query()->findOrFail($accountId),
                effectiveFrom: $mappingData['effective_from'],
                effectiveTo: $mappingData['effective_to'] ?? null,
                isProtected: (bool) ($mappingData['is_protected'] ?? false),
                changeReason: self::nullableString($mappingData['change_reason'] ?? null),
                isActive: (bool) ($mappingData['is_active'] ?? false),
            );
        }

        Notification::make()->title('Pemetaan akun berhasil disimpan.')->success()->send();
        $this->form->fill(['mappings' => $this->mappingState()]);
    }

    /** @param array<string, mixed> $data */
    public function createAccountForMapping(string $stateKey, array $data): void
    {
        $this->assertCanManage();
        $key = $this->keyFromStateKey($stateKey);
        $category = AccountCategory::from($data['category']);

        if (in_array($category, $key->compatibleCategories(), true) === false) {
            throw ValidationException::withMessages(['category' => 'Kategori akun tidak kompatibel dengan pemetaan ini.']);
        }

        $parent = Account::query()->findOrFail($data['parent_id']);
        $generator = app(AccountCodeGenerator::class);
        $account = $generator->create(
            auth()->user(),
            $category,
            $parent,
            $data['name'],
            null,
            new AccountCreationOptions(
                subcategory: self::nullableString($data['subcategory'] ?? null),
                normalBalance: $category->normalBalance(),
                statementClassification: $category->statementClassification(),
                cashFlowCategory: null,
                cashFlowLine: null,
                isPostable: (bool) ($data['is_postable'] ?? true),
                isControlAccount: (bool) ($data['is_control_account'] ?? false),
                isActive: (bool) ($data['is_active'] ?? true),
                displayOrder: 0,
            ),
        );

        $state = $this->form->getState();
        $state['mappings'][$stateKey]['account_id'] = $account->id;
        $this->form->fill($state);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveMappings')
                ->label('Simpan Pemetaan')
                ->icon('heroicon-o-check')
                ->visible(fn (): bool => $this->canManage())
                ->action('saveMappings'),
            Action::make('createAccount')
                ->label('Buat Akun')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->canManage())
                ->form([
                    Select::make('mapping_state_key')
                        ->label('Pemetaan tujuan')
                        ->options($this->mappingLabels())
                        ->required(),
                    Select::make('category')
                        ->label('Kategori')
                        ->options($this->categoryOptions())
                        ->required(),
                    TextInput::make('subcategory')->label('Subkategori')->maxLength(100),
                    Select::make('parent_id')
                        ->label('Akun Induk')
                        ->options(fn (): array => Account::query()->where('is_postable', false)->orderBy('code')->get()
                            ->mapWithKeys(fn (Account $account): array => [$account->id => "{$account->code} — {$account->name}"])->all())
                        ->required()
                        ->searchable(),
                    TextInput::make('name')->label('Nama Akun')->required()->maxLength(255),
                    Toggle::make('is_postable')->label('Dapat Diposting')->default(true)->required(),
                    Toggle::make('is_control_account')->label('Akun Kontrol')->default(false)->required(),
                    Toggle::make('is_active')->label('Aktif')->default(true)->required(),
                ])
                ->action(function (array $data): void {
                    $stateKey = $data['mapping_state_key'];
                    unset($data['mapping_state_key']);
                    $this->createAccountForMapping($stateKey, $data);
                    Notification::make()->title('Akun baru dipilih pada draft pemetaan.')->success()->send();
                }),
        ];
    }

    /** @return array<int, Section> */
    private function mappingSections(): array
    {
        return collect($this->mappingCatalog())
            ->groupBy('section')
            ->map(function ($items, string $section): Section {
                $runtimeCount = $items->filter(fn (array $item): bool => $item['key']->isRuntimeRequired())->count();

                return Section::make($section)
                    ->description("{$items->count()} pemetaan · {$runtimeCount} diperlukan untuk runtime")
                    ->icon('heroicon-o-folder-open')
                    ->collapsible()
                    ->schema($items->map(fn (array $item) => $this->mappingFields($item))->all())
                    ->columns(1);
            })
            ->values()
            ->all();
    }

    /** @param array{key: MappingKey, label: string, section: string} $item */
    private function mappingFields(array $item): Section
    {
        $stateKey = $item['key']->formStateKey();

        return Section::make($item['label'])
            ->description(fn (): string => $this->isProtected($item['key']) ? 'Terkunci' : ($item['key']->isRuntimeRequired() ? 'Wajib · digunakan saat posting' : 'Opsional · belum diaktifkan'))
            ->icon(fn (): string => $this->isProtected($item['key']) ? 'heroicon-o-lock-closed' : ($item['key']->isRuntimeRequired() ? 'heroicon-o-bolt' : 'heroicon-o-link'))
            ->schema([
                Select::make("mappings.{$stateKey}.account_id")
                    ->label('Akun COA')
                    ->options(fn (): array => Account::query()
                        ->whereIn('category', array_map(fn (AccountCategory $category): string => $category->value, $item['key']->compatibleCategories()))
                        ->where('is_active', true)
                        ->where('is_postable', true)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Account $account): array => [$account->id => "{$account->code} — {$account->name}"])
                        ->all())
                    ->searchable()
                    ->disabled(fn (): bool => $this->isProtected($item['key'])),
                DatePicker::make("mappings.{$stateKey}.effective_from")
                    ->label('Berlaku Mulai')
                    ->required()
                    ->disabled(fn (): bool => $this->isProtected($item['key'])),
                DatePicker::make("mappings.{$stateKey}.effective_to")
                    ->label('Berlaku Sampai')
                    ->afterOrEqual("mappings.{$stateKey}.effective_from")
                    ->disabled(fn (): bool => $this->isProtected($item['key'])),
                Toggle::make("mappings.{$stateKey}.is_active")
                    ->label('Aktif')
                    ->disabled(fn (): bool => $item['key']->isRuntimeRequired() === false || $this->isProtected($item['key']))
                    ->dehydrated(),
            ]);
    }

    /** @return array<string, array{account_id: ?int, effective_from: string, effective_to: ?string, is_active: bool, is_protected: bool, change_reason: ?string}> */
    private function mappingState(): array
    {
        $current = AccountMapping::query()->orderByDesc('effective_from')->get()->keyBy(fn (AccountMapping $mapping): string => $mapping->mapping_key->value);
        $state = [];

        foreach ($this->mappingCatalog() as $item) {
            $mapping = $current->get($item['key']->value);
            $state[$item['key']->formStateKey()] = [
                'account_id' => $mapping?->account_id,
                'effective_from' => $mapping?->effective_from->toDateString() ?? now()->toDateString(),
                'effective_to' => $mapping?->effective_to?->toDateString(),
                'is_active' => $mapping?->is_active ?? $item['key']->isRuntimeRequired(),
                'is_protected' => $mapping?->is_protected ?? false,
                'change_reason' => $mapping?->change_reason,
            ];
        }

        return $state;
    }

    private function isProtected(MappingKey $key): bool
    {
        return (bool) AccountMapping::query()->where('mapping_key', $key->value)->where('is_protected', true)->exists();
    }

    private function keyFromStateKey(string $stateKey): MappingKey
    {
        foreach (MappingKey::cases() as $key) {
            if ($key->formStateKey() === $stateKey) {
                return $key;
            }
        }

        throw ValidationException::withMessages(['mapping_state_key' => 'Pemetaan akun tidak dikenal.']);
    }

    private function assertCanManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    private function canManage(): bool
    {
        $user = auth()->user();

        return $user !== null && app(AccountingAuthorization::class)->canManageConfig($user);
    }

    /** @return array<string, string> */
    private function categoryOptions(): array
    {
        return collect(AccountCategory::cases())->mapWithKeys(fn (AccountCategory $category): array => [$category->value => $category->value])->all();
    }

    /** @return array<string, string> */
    private function mappingLabels(): array
    {
        return collect($this->mappingCatalog())->mapWithKeys(fn (array $item): array => [$item['key']->formStateKey() => $item['label']])->all();
    }

    /** @return list<array{key: MappingKey, label: string, section: string}> */
    private function mappingCatalog(): array
    {
        return [
            ['key' => MappingKey::SalesRetailRevenue, 'label' => 'Pendapatan Penjualan Retail', 'section' => 'Penjualan'],
            ['key' => MappingKey::SalesWholesaleRevenue, 'label' => 'Pendapatan Penjualan Grosir', 'section' => 'Penjualan'],
            ['key' => MappingKey::SalesDiscount, 'label' => 'Diskon Penjualan', 'section' => 'Penjualan'],
            ['key' => MappingKey::SalesReturn, 'label' => 'Retur Penjualan', 'section' => 'Penjualan'],
            ['key' => MappingKey::SalesOutputTax, 'label' => 'PPN Keluaran', 'section' => 'Penjualan'],
            ['key' => MappingKey::PurchaseInventory, 'label' => 'Pembelian / Persediaan', 'section' => 'Pembelian'],
            ['key' => MappingKey::PurchaseInputTax, 'label' => 'PPN Masukan', 'section' => 'Pembelian'],
            ['key' => MappingKey::ArReceivable, 'label' => 'Piutang Usaha', 'section' => 'AR / AP'],
            ['key' => MappingKey::ApPayable, 'label' => 'Utang Usaha', 'section' => 'AR / AP'],
            ['key' => MappingKey::CashDefault, 'label' => 'Kas Default', 'section' => 'Kas & Bank'],
            ['key' => MappingKey::BankDefault, 'label' => 'Bank Default', 'section' => 'Kas & Bank'],
            ['key' => MappingKey::CashInTransit, 'label' => 'Kas dalam Perjalanan', 'section' => 'Kas & Bank'],
            ['key' => MappingKey::CashRounding, 'label' => 'Selisih Pembulatan', 'section' => 'Kas & Bank'],
            ['key' => MappingKey::InventoryAsset, 'label' => 'Persediaan Barang', 'section' => 'Persediaan'],
            ['key' => MappingKey::InventoryDamageExpense, 'label' => 'Barang Rusak / Kedaluwarsa', 'section' => 'Persediaan'],
            ['key' => MappingKey::ExpenseGeneral, 'label' => 'Biaya Operasional', 'section' => 'Biaya'],
            ['key' => MappingKey::OpeningEquity, 'label' => 'Ekuitas Saldo Awal', 'section' => 'Ekuitas & Lainnya'],
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
