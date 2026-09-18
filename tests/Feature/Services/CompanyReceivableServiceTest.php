<?php

namespace Tests\Feature\Services;

use App\Models\CapitalAccount;
use App\Models\Company;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CompanyReceivableService;
use App\Services\LedgerVerificationService;
use Tests\ServiceTestCase;

class CompanyReceivableServiceTest extends ServiceTestCase
{
    private CompanyReceivableService $service;
    private Company $company;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParameters();
        $this->service = new CompanyReceivableService();
        $this->company = Company::factory()->create();

        // Capital respaldado por un aporte del mismo monto, para que la
        // verificación contable arranque cuadrada.
        FundAccount::query()->delete();
        FundAccount::create(['balance' => 0]);
        CapitalAccount::query()->delete();
        CapitalAccount::create(['balance' => 500000]);

        FundMember::factory()->create([
            'type'         => 'capital',
            'active'       => true,
            'contribution' => 500000,
        ]);

        $this->operator = User::factory()->create();
        $this->operator->assignRole($this->createRole('operator'));
    }

    private function record(float $amount = 19765.59): Transaction
    {
        return $this->service->record([
            'company_id'    => $this->company->id,
            'amount'        => $amount,
            'registered_by' => $this->operator->id,
            'notes'         => 'Sobre-desembolso de prueba',
        ]);
    }

    private function ledgerFails(): array
    {
        return (new LedgerVerificationService())->runChecks();
    }

    // ── record ─────────────────────────────────────────────────────────────

    public function test_record_crea_el_saldo_confirmado(): void
    {
        $tx = $this->record();

        $this->assertSame('company_receivable', $tx->type);
        $this->assertSame('confirmed', $tx->status);
        $this->assertEquals(19765.59, (float) $tx->amount);
        $this->assertSame($this->company->id, $tx->company_id);
        $this->assertNotNull($tx->confirmed_at);
        $this->assertEquals(19765.59, $this->service->balanceFor($this->company->id));
    }

    public function test_record_no_mueve_los_ledgers(): void
    {
        $capitalAntes = (float) CapitalAccount::instance()->balance;
        $fondoAntes   = (float) FundAccount::instance()->balance;

        $this->record();

        $this->assertEquals($capitalAntes, (float) CapitalAccount::instance()->balance);
        $this->assertEquals($fondoAntes, (float) FundAccount::instance()->balance);
        $this->assertSame([], $this->ledgerFails());
    }

    public function test_record_rechaza_monto_no_positivo(): void
    {
        $this->expectExceptionMessage('debe ser mayor que cero');

        $this->service->record([
            'company_id'    => $this->company->id,
            'amount'        => 0,
            'registered_by' => $this->operator->id,
        ]);
    }

    public function test_record_exige_compania(): void
    {
        $this->expectExceptionMessage('debe tener una compañía asociada');

        $this->service->record([
            'amount'        => 1000,
            'registered_by' => $this->operator->id,
        ]);
    }

    // ── repay ──────────────────────────────────────────────────────────────

    public function test_repay_acredita_capital_y_baja_el_saldo(): void
    {
        $this->record();
        $capitalAntes = (float) CapitalAccount::instance()->balance;

        $tx = $this->service->repay([
            'company_id'    => $this->company->id,
            'amount'        => 10000,
            'registered_by' => $this->operator->id,
        ]);

        $this->assertSame('company_repayment', $tx->type);
        $this->assertSame('confirmed', $tx->status);
        $this->assertEquals(9765.59, $this->service->balanceFor($this->company->id));
        $this->assertEquals($capitalAntes + 10000, (float) CapitalAccount::instance()->balance);
    }

    public function test_repay_mantiene_la_invariante_contable(): void
    {
        $this->record();

        $this->service->repay([
            'company_id'    => $this->company->id,
            'amount'        => 10000,
            'registered_by' => $this->operator->id,
        ]);

        $this->assertSame([], $this->ledgerFails());
    }

    public function test_saldar_por_completo_deja_el_saldo_en_cero(): void
    {
        $this->record();

        $this->service->repay([
            'company_id'    => $this->company->id,
            'amount'        => 19765.59,
            'registered_by' => $this->operator->id,
        ]);

        $this->assertEquals(0.0, $this->service->balanceFor($this->company->id));
        $this->assertSame([], $this->ledgerFails());
    }

    public function test_repay_rechaza_devolver_mas_del_saldo(): void
    {
        $this->record(5000);

        $this->expectExceptionMessage('supera el saldo pendiente');

        $this->service->repay([
            'company_id'    => $this->company->id,
            'amount'        => 5000.01,
            'registered_by' => $this->operator->id,
        ]);
    }

    public function test_repay_rechaza_sin_saldo_previo(): void
    {
        $this->expectExceptionMessage('supera el saldo pendiente');

        $this->service->repay([
            'company_id'    => $this->company->id,
            'amount'        => 100,
            'registered_by' => $this->operator->id,
        ]);
    }

    public function test_repay_rechaza_monto_no_positivo(): void
    {
        $this->record();

        $this->expectExceptionMessage('debe ser mayor que cero');

        $this->service->repay([
            'company_id'    => $this->company->id,
            'amount'        => -1,
            'registered_by' => $this->operator->id,
        ]);
    }

    // ── consultas ──────────────────────────────────────────────────────────

    public function test_balance_es_por_compania(): void
    {
        $otra = Company::factory()->create();

        $this->record();
        $this->service->record([
            'company_id'    => $otra->id,
            'amount'        => 3000,
            'registered_by' => $this->operator->id,
        ]);

        $this->assertEquals(19765.59, $this->service->balanceFor($this->company->id));
        $this->assertEquals(3000.0, $this->service->balanceFor($otra->id));
        $this->assertEquals(22765.59, $this->service->totalOutstanding());
    }

    public function test_outstanding_by_company_omite_las_saldadas(): void
    {
        $this->record(5000);
        $this->service->repay([
            'company_id'    => $this->company->id,
            'amount'        => 5000,
            'registered_by' => $this->operator->id,
        ]);

        $this->assertCount(0, $this->service->outstandingByCompany());
    }

    public function test_statement_lleva_saldo_corrido(): void
    {
        $this->record(5000);
        $this->service->repay([
            'company_id'    => $this->company->id,
            'amount'        => 2000,
            'registered_by' => $this->operator->id,
        ]);

        $movimientos = $this->service->statementFor($this->company->id);

        $this->assertCount(2, $movimientos);
        $this->assertEquals(5000.0, $movimientos[0]['signed']);
        $this->assertEquals(5000.0, $movimientos[0]['balance']);
        $this->assertEquals(-2000.0, $movimientos[1]['signed']);
        $this->assertEquals(3000.0, $movimientos[1]['balance']);
    }

    public function test_movimientos_pendientes_no_cuentan_en_el_saldo(): void
    {
        $this->record();

        Transaction::create([
            'type'             => 'company_receivable',
            'status'           => 'pending',
            'amount'           => 50000,
            'company_id'       => $this->company->id,
            'transaction_date' => now(),
            'registered_by'    => $this->operator->id,
        ]);

        $this->assertEquals(19765.59, $this->service->balanceFor($this->company->id));
    }
}
