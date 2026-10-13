<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\CuentasPorCobrarPage;
use App\Filament\Pages\SaldosCompaniasPage;
use App\Filament\Resources\TransactionResource\Pages\CreateTransaction;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TransactionResource\Pages\ViewTransaction;
use App\Filament\Widgets\CompanyCreditMovementsWidget;
use App\Filament\Widgets\CuentasPorCobrarStatsWidget;
use App\Filament\Widgets\FinancingPipelineWidget;
use App\Filament\Widgets\PendingTransactionsWidget;
use App\Models\CapitalAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyCredit;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CompanyReceivableService;
use App\Services\LedgerVerificationService;
use App\Services\TransactionService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\ServiceTestCase;

/**
 * Las pantallas de operaciones tal como las ve un operador: formulario de
 * transacción, cobros pendientes, cuentas por cobrar y saldos de compañías.
 * Están llenas de closures que solo fallan al renderizar.
 */
class OperationsScreensTest extends ServiceTestCase
{
    private Company $company;
    private Client $client;
    private User $operator;
    private User $companyUser;
    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParameters();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->company = Company::factory()->create(['name' => 'Compañía de Prueba SRL']);
        $this->client  = Client::factory()->for($this->company)->create();

        FundAccount::query()->delete();
        FundAccount::create(['balance' => 0]);
        CapitalAccount::query()->delete();
        CapitalAccount::create(['balance' => 500000]);

        FundMember::factory()->create([
            'type'         => 'capital',
            'active'       => true,
            'contribution' => 500000,
        ]);

        $role = $this->createRole('operator');
        foreach (['view_any_transaction', 'view_transaction', 'create_transaction'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]));
        }

        $this->operator = User::factory()->create();
        $this->operator->assignRole($role);

        $this->companyUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->companyUser->assignRole($this->createRole('company_user'));

        $this->service = new TransactionService();

        $this->actingAs($this->operator);
    }

    private function solicitedFinancing(float $amount = 100000.00): Financing
    {
        return Financing::factory()->withAmount($amount)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->operator->id,
        ]);
    }

    private function disbursedFinancing(float $amount = 100000.00): Financing
    {
        $financing = $this->solicitedFinancing($amount);

        $this->service->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-' . $financing->id,
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);

        return $financing->fresh();
    }

    private function pendingCollection(Financing $financing, float $amount, string $method = 'check'): Transaction
    {
        $this->actingAs($this->companyUser);

        $transaction = $this->service->create([
            'type'               => 'collection',
            'payment_method'     => $method,
            'company_id'         => $this->company->id,
            'amount'             => $amount,
            'bank'               => 'BanReservas',
            'transaction_number' => 'REF-' . $financing->id,
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->companyUser->id,
        ], [$financing->id]);

        $this->actingAs($this->operator);

        return $transaction;
    }

    /**
     * Deja a la compañía con un cheque confirmado en mano por el monto dado.
     */
    private function checkInHand(float $amount): void
    {
        $financing = $this->disbursedFinancing($amount);

        $this->service->create([
            'type'               => 'collection',
            'payment_method'     => 'check',
            'bank'               => 'BHD',
            'transaction_number' => 'CHK-' . $financing->id,
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);
    }

    // ── Formulario de transacción ──────────────────────────────────────────

    public function test_el_desembolso_cubierto_con_cheques_no_pide_datos_bancarios(): void
    {
        $this->checkInHand(200000.00);
        $nuevo = $this->solicitedFinancing(100000.00);
        $gastosAntes = Transaction::where('type', 'expense')->count();

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'type'          => 'disbursement',
                'company_id'    => $this->company->id,
                'financing_ids' => [$nuevo->id],
            ])
            ->assertFormFieldIsVisible('bank')
            ->fillForm(['credit_applied' => '95,000.00'])
            ->assertFormFieldIsHidden('bank')
            ->assertFormFieldIsHidden('transaction_number')
            ->assertSee('No sale dinero del banco')
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo->refresh();
        $this->assertSame('disbursed', $nuevo->status);
        $this->assertEquals(105000.00, (float) CompanyCredit::sum('amount'));
        // Sin transferencia no hay impuesto bancario.
        $this->assertSame($gastosAntes, Transaction::where('type', 'expense')->count());
        $this->assertEmpty((new LedgerVerificationService())->runChecks());
    }

    public function test_usar_todo_lo_posible_rellena_el_efectivo_aplicable(): void
    {
        $this->checkInHand(50000.00);
        $nuevo = $this->solicitedFinancing(100000.00);

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'type'          => 'disbursement',
                'company_id'    => $this->company->id,
                'financing_ids' => [$nuevo->id],
            ])
            // Los dos montos calculados se muestran como campos de solo lectura.
            ->assertSeeHtml('value="50,000.00"')
            ->assertSeeHtml('value="95,000.00"')
            ->callFormComponentAction('credit_applied', 'useAllCredit')
            ->assertFormSet(['credit_applied' => '50,000.00'])
            ->assertSeeHtml('value="45,000.00"')
            ->assertSee('le transfiere RD$ 45,000.00');
    }

    /**
     * El impuesto se escribe siempre con dos decimales: 0.20, nunca 0.2.
     */
    public function test_el_impuesto_se_muestra_con_dos_decimales(): void
    {
        \App\Models\Parameter::where('key', 'tax_pct')->update(['value' => 0.2]);
        $nuevo = $this->solicitedFinancing(100000.00);

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'type'          => 'disbursement',
                'company_id'    => $this->company->id,
                'financing_ids' => [$nuevo->id],
            ])
            ->assertSee('(0.20%)')
            ->assertDontSee('(0.2%)')
            ->fillForm(['bank' => 'BanReservas', 'transaction_number' => 'TXN-TAX'])
            ->call('create')
            ->assertHasNoFormErrors();

        $impuesto = Transaction::where('type', 'expense')->latest('id')->first();

        $this->assertEquals(190.00, (float) $impuesto->amount);
        $this->assertStringContainsString('Automático (0.20%)', $impuesto->notes);

        Livewire::actingAs($this->superAdmin())
            ->test(\App\Filament\Pages\ParametrosPage::class)
            ->assertFormSet(['tax_pct' => '0.20']);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole($this->createRole('super_admin'));

        return $admin;
    }

    public function test_el_resumen_explica_un_cobro_en_cheque(): void
    {
        $financing = $this->disbursedFinancing(100000.00);

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'type'           => 'collection',
                'company_id'     => $this->company->id,
                'financing_ids'  => [$financing->id],
                'payment_method' => 'check',
            ])
            ->assertSee('el dinero queda en manos de la compañía')
            ->assertSee('Los financiamientos quedan saldados.');
    }

    public function test_la_liquidacion_llega_rellenada_desde_saldos_de_companias(): void
    {
        $this->checkInHand(100000.00);

        Livewire::withQueryParams(['type' => 'settlement', 'company_id' => $this->company->id])
            ->test(CreateTransaction::class)
            ->assertFormSet(['type' => 'settlement', 'amount' => '100,000.00'])
            ->assertFormFieldIsHidden('financing_ids')
            ->assertSee('Sus cheques por liquidar bajan de RD$ 100,000.00 a RD$ 0.00.');
    }

    // ── Cobros pendientes ──────────────────────────────────────────────────

    public function test_corregir_desde_el_listado_cambia_monto_y_metodo(): void
    {
        $financing  = $this->disbursedFinancing(100000.00);
        $collection = $this->pendingCollection($financing, 100000.00, 'check');

        Livewire::test(ListTransactions::class)
            ->assertTableActionVisible('confirm', $collection)
            ->assertTableActionVisible('correct', $collection)
            ->assertTableActionVisible('reject', $collection)
            ->callTableAction('correct', $collection, data: [
                'amount'             => '80,000.00',
                'payment_method'     => 'transfer',
                'bank'               => 'BanReservas',
                'transaction_number' => 'REF-' . $financing->id,
                'reason'             => 'Fue transferencia y por menos.',
            ])
            ->assertHasNoTableActionErrors();

        $collection->refresh();
        $this->assertSame('pending', $collection->status);
        $this->assertEquals(80000.00, (float) $collection->amount);
        $this->assertSame('transfer', $collection->payment_method);
    }

    public function test_rechazar_desde_la_vista_del_cobro(): void
    {
        $financing  = $this->disbursedFinancing(100000.00);
        $collection = $this->pendingCollection($financing, 100000.00);

        Livewire::test(ViewTransaction::class, ['record' => $collection->getRouteKey()])
            ->assertActionVisible('confirm')
            ->callAction('reject', data: ['reason' => 'El cheque rebotó.'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('confirm');

        $this->assertSame('rejected', $collection->fresh()->status);
        $this->assertSame('disbursed', $financing->fresh()->status);
    }

    public function test_las_acciones_no_aparecen_en_una_transaccion_confirmada(): void
    {
        $financing    = $this->disbursedFinancing(100000.00);
        $disbursement = $financing->transactions()->where('type', 'disbursement')->first();

        Livewire::test(ListTransactions::class)
            ->assertTableActionHidden('confirm', $disbursement)
            ->assertTableActionHidden('correct', $disbursement)
            ->assertTableActionHidden('reject', $disbursement);
    }

    public function test_el_widget_de_pendientes_ofrece_las_tres_acciones(): void
    {
        $financing  = $this->disbursedFinancing(100000.00);
        $collection = $this->pendingCollection($financing, 100000.00);

        Livewire::test(PendingTransactionsWidget::class)
            ->assertCanSeeTableRecords([$collection])
            ->assertTableActionVisible('confirm', $collection)
            ->assertTableActionVisible('correct', $collection)
            ->assertTableActionVisible('reject', $collection);
    }

    public function test_el_company_user_no_ve_las_acciones_de_gestion(): void
    {
        $financing  = $this->disbursedFinancing(100000.00);
        $collection = $this->pendingCollection($financing, 100000.00);

        $this->actingAs($this->companyUser);

        Livewire::test(PendingTransactionsWidget::class)
            ->assertCanSeeTableRecords([$collection])
            ->assertTableActionHidden('confirm', $collection)
            ->assertTableActionHidden('correct', $collection)
            ->assertTableActionHidden('reject', $collection);
    }

    // ── Cuentas por cobrar ─────────────────────────────────────────────────

    public function test_un_financiamiento_con_pago_por_confirmar_sigue_en_cuentas_por_cobrar(): void
    {
        $financing = $this->disbursedFinancing(100000.00);
        $this->pendingCollection($financing, 100000.00);

        $this->assertSame('pending_payment', $financing->fresh()->status);

        Livewire::test(CuentasPorCobrarPage::class)
            ->assertCanSeeTableRecords([$financing])
            ->assertSee('Pago por confirmar');

        Livewire::test(CuentasPorCobrarStatsWidget::class)
            ->assertSee('RD$ 100,000.00')
            ->assertSee('1 con pago por confirmar');

        Livewire::test(FinancingPipelineWidget::class)
            ->assertSee('RD$ 95,000.00 en calle');
    }

    /**
     * Lo que se muestra por cobrar es lo que el deudor debe (monto menos cobrado),
     * no lo desembolsado, que omite la comisión.
     */
    public function test_el_total_por_cobrar_es_lo_que_debe_el_deudor(): void
    {
        $financing = $this->disbursedFinancing(100000.00);

        $this->service->create([
            'type'               => 'collection',
            'amount'             => 30000.00,
            'bank'               => 'BHD',
            'transaction_number' => 'ABONO-1',
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);

        Livewire::test(CuentasPorCobrarStatsWidget::class)
            ->assertSee('RD$ 70,000.00');
    }

    // ── Saldos de compañías ────────────────────────────────────────────────

    public function test_saldos_es_una_fila_por_compania_con_cada_deuda_en_su_columna(): void
    {
        $this->checkInHand(100000.00);
        (new CompanyReceivableService())->record(['company_id' => $this->company->id, 'amount' => 5000.00]);

        Livewire::test(SaldosCompaniasPage::class)
            ->assertSee('Compañía de Prueba SRL')
            ->assertSee('RD$ 100,000.00')
            ->assertSee('RD$ 5,000.00')
            ->assertSee('RD$ 105,000.00')
            ->assertSee('Cheques: registrar liquidación')
            ->assertSee('Cheques: descontar de un desembolso')
            ->assertSee('Sobre-desembolso: registrar devolución')
            ->assertSee('De todas las compañías.');
    }

    /**
     * Con varias compañías la página crece en filas, no en bloques, y cada una
     * solo ofrece las acciones de las deudas que tiene.
     */
    public function test_saldos_con_varias_companias_muestra_totales_y_filtra_el_historial(): void
    {
        $this->checkInHand(100000.00);

        $otra = Company::factory()->create(['name' => 'Otra Compañía SRL']);
        (new CompanyReceivableService())->record(['company_id' => $otra->id, 'amount' => 7000.00]);

        $page = Livewire::test(SaldosCompaniasPage::class)
            ->assertSee('Compañía de Prueba SRL')
            ->assertSee('Otra Compañía SRL')
            ->assertSee('Total · 2 compañías')
            ->assertSee('RD$ 107,000.00')
            ->assertSet('tab', 'checks');

        // Pulsar el sobre-desembolso de la otra compañía: cambia de pestaña y acota.
        $page->call('verMovimientos', $otra->id, 'overdisbursed')
            ->assertSet('companyId', $otra->id)
            ->assertSet('tab', 'overdisbursed')
            ->assertSee('Solo Otra Compañía SRL.')
            ->assertSee('Ver todas las compañías')
            ->assertCountTableRecords(1);

        // La primera compañía no tiene sobre-desembolsos: su historial queda vacío.
        $page->call('verMovimientos', $this->company->id, 'overdisbursed')
            ->assertCountTableRecords(0);

        $page->call('verMovimientos', null)
            ->assertSet('companyId', null)
            ->assertSee('De todas las compañías.')
            ->assertCountTableRecords(1);
    }

    public function test_el_historial_de_cheques_se_acota_a_la_compania(): void
    {
        $this->checkInHand(100000.00);
        $otra = Company::factory()->create();

        Livewire::test(CompanyCreditMovementsWidget::class)
            ->assertSee('Cheque recibido por la compañía')
            ->assertCountTableRecords(1);

        Livewire::test(CompanyCreditMovementsWidget::class, ['companyId' => $otra->id])
            ->assertCountTableRecords(0);
    }

    /**
     * La devolución se lanza desde la fila de la compañía: llega con ella elegida
     * y su saldo como monto.
     */
    public function test_registrar_devolucion_llega_rellenada_y_salda_el_sobre_desembolso(): void
    {
        (new CompanyReceivableService())->record(['company_id' => $this->company->id, 'amount' => 5000.00]);

        Livewire::test(SaldosCompaniasPage::class)
            ->mountAction('registrarDevolucion', ['company' => $this->company->id])
            ->assertActionDataSet([
                'company_id' => $this->company->id,
                'amount'     => 5000.00,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertSee('Ninguna compañía le debe dinero al fondo.');

        $this->assertEquals(0.0, (new CompanyReceivableService())->balanceFor($this->company->id));
    }

    public function test_saldos_vacio_renderiza_sin_acciones(): void
    {
        Livewire::test(SaldosCompaniasPage::class)
            ->assertSee('Ninguna compañía le debe dinero al fondo.')
            ->assertDontSee('Saldar')
            ->assertDontSee('Cheques: registrar liquidación');
    }
}
