<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinancingAndTransactionSeeder extends Seeder
{
    private float $commissionRate = 0.05;

    public function run(): void
    {
        $admin    = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();
        $operator = User::whereHas('roles', fn ($q) => $q->where('name', 'operator'))->first() ?? $admin;

        $cormart = Company::where('name', 'Cormart Soluciones SRL')->first();
        $ysetech = Company::where('name', 'Ysetech SRL')->first();

        $cormart_user = User::where('email', 'cormart@test.com')->first();
        $ysetech_user = User::where('email', 'ysetech@test.com')->first();

        // Clientes Cormart
        $innovatech = Client::where('name', 'Innovatech Solutions SRL')->first();
        $rodriguez  = Client::where('name', 'Grupo Rodriguez & Asociados SA')->first();
        $caribbean  = Client::where('name', 'Caribbean Trading Co. SRL')->first();

        // Clientes Ysetech
        $datacenter = Client::where('name', 'DataCenter DR SRL')->first();
        $techpro    = Client::where('name', 'TechPro Distribuciones SA')->first();
        $sistemas   = Client::where('name', 'Sistemas Integrados del Caribe')->first();

        $dgii = Supplier::where('name', 'DGII')->first();

        // ── 1. COBRADOS EN ENERO 2026 ──────────────────────────────────────
        $f1 = $this->financing($cormart, $innovatech, 95000,  '2026-01-05', 15, 'collected', '2026-01-06', '2026-01-22', $cormart_user->id);
        $f2 = $this->financing($ysetech, $datacenter, 120000, '2026-01-08', 15, 'collected', '2026-01-09', '2026-01-25', $ysetech_user->id);

        $this->transaction('disbursement', $cormart, [$f1], 'BanReservas',  'DIS-C-0001', '2026-01-06', 'confirmed', $admin);
        $this->transaction('collection',   $cormart, [$f1], 'BHD',          'COB-C-0001', '2026-01-22', 'confirmed', $admin);
        $this->transaction('disbursement', $ysetech, [$f2], 'BHD',          'DIS-Y-0001', '2026-01-09', 'confirmed', $admin);
        $this->transaction('collection',   $ysetech, [$f2], 'BHD',          'COB-Y-0001', '2026-01-25', 'confirmed', $admin);

        // ── 2. COBRADOS EN FEBRERO 2026 ────────────────────────────────────
        $f3 = $this->financing($cormart, $caribbean,  200000, '2026-01-20', 15, 'collected', '2026-01-21', '2026-02-05', $cormart_user->id);
        $f4 = $this->financing($cormart, $rodriguez,  80000,  '2026-01-25', 15, 'collected', '2026-01-26', '2026-02-12', $operator->id);
        $f5 = $this->financing($ysetech, $techpro,    150000, '2026-01-28', 15, 'collected', '2026-01-29', '2026-02-15', $ysetech_user->id);

        $this->transaction('disbursement', $cormart, [$f3],    'BanReservas',  'DIS-C-0002', '2026-01-21', 'confirmed', $admin);
        $this->transaction('collection',   $cormart, [$f3],    'BanReservas',  'COB-C-0002', '2026-02-05', 'confirmed', $admin);
        $this->transaction('disbursement', $cormart, [$f4],    'Banco Popular', 'DIS-C-0003', '2026-01-26', 'confirmed', $admin);
        $this->transaction('collection',   $cormart, [$f4],    'BHD',           'COB-C-0003', '2026-02-12', 'confirmed', $admin);
        $this->transaction('disbursement', $ysetech, [$f5],    'BHD',           'DIS-Y-0002', '2026-01-29', 'confirmed', $admin);
        $this->transaction('collection',   $ysetech, [$f5],    'BHD',           'COB-Y-0002', '2026-02-15', 'confirmed', $admin);

        // ── 3. COBRADOS EN MARZO 2026 ──────────────────────────────────────
        $f6 = $this->financing($cormart, $innovatech, 110000, '2026-02-14', 15, 'collected', '2026-02-15', '2026-03-03', $cormart_user->id);
        $f7 = $this->financing($ysetech, $datacenter, 85000,  '2026-02-18', 15, 'collected', '2026-02-19', '2026-03-08', $ysetech_user->id);

        $this->transaction('disbursement', $cormart, [$f6], 'BanReservas',  'DIS-C-0004', '2026-02-15', 'confirmed', $admin);
        $this->transaction('collection',   $cormart, [$f6], 'BHD',          'COB-C-0004', '2026-03-03', 'confirmed', $admin);
        $this->transaction('disbursement', $ysetech, [$f7], 'BHD',          'DIS-Y-0003', '2026-02-19', 'confirmed', $admin);
        $this->transaction('collection',   $ysetech, [$f7], 'BHD',          'COB-Y-0003', '2026-03-08', 'confirmed', $admin);

        // ── 4. DESEMBOLSADOS (en calle) ────────────────────────────────────
        $f8  = $this->financing($cormart, $rodriguez,  90000, '2026-03-01', 30, 'disbursed', '2026-03-03', null, $cormart_user->id);
        $f9  = $this->financing($ysetech, $sistemas,   60000, '2026-03-03', 30, 'disbursed', '2026-03-05', null, $ysetech_user->id);
        $f10 = $this->financing($cormart, $innovatech, 55000, '2026-03-06', 30, 'disbursed', '2026-03-07', null, $operator->id);
        $f11 = $this->financing($ysetech, $datacenter, 75000, '2026-03-07', 30, 'disbursed', '2026-03-08', null, $ysetech_user->id);

        $this->transaction('disbursement', $cormart, [$f8],  'BanReservas',  'DIS-C-0005', '2026-03-03', 'confirmed', $admin);
        $this->transaction('disbursement', $ysetech, [$f9],  'Banco Popular', 'DIS-Y-0004', '2026-03-05', 'confirmed', $admin);
        $this->transaction('disbursement', $cormart, [$f10], 'BHD',           'DIS-C-0006', '2026-03-07', 'confirmed', $admin);
        $this->transaction('disbursement', $ysetech, [$f11], 'BanReservas',  'DIS-Y-0005', '2026-03-08', 'confirmed', $admin);

        // Cobro pendiente registrado por Ysetech user (sin confirmar)
        $this->transaction('collection', $ysetech, [$f11], 'BHD', 'COB-Y-PEND-001', '2026-03-11', 'pending', null, $ysetech_user);

        // ── 5. SOLICITADOS (esperando desembolso) ──────────────────────────
        $this->financing($cormart, $caribbean,  45000,  '2026-03-08', 30, 'solicited', null, null, $cormart_user->id);
        $this->financing($ysetech, $techpro,    110000, '2026-03-09', 30, 'solicited', null, null, $ysetech_user->id);
        $this->financing($cormart, $innovatech, 70000,  '2026-03-10', 30, 'solicited', null, null, $cormart_user->id);
        $this->financing($ysetech, $sistemas,   85000,  '2026-03-11', 30, 'solicited', null, null, $operator->id);

        // ── 6. GASTOS OPERATIVOS ───────────────────────────────────────────
        $supplierId = $dgii?->id;

        // Enero 2026
        $this->expense(3000.00,  '2026-01-15', 'EXP-001', 'Impuesto desembolso Innovatech',  $supplierId, $admin);
        $this->expense(2500.00,  '2026-01-18', 'EXP-002', 'Impuesto desembolso DataCenter',  $supplierId, $admin);

        // Febrero 2026
        $this->expense(4200.00,  '2026-02-10', 'EXP-003', 'Impuesto desembolsos enero',      $supplierId, $admin);
        $this->expense(1800.00,  '2026-02-20', 'EXP-004', 'Gastos bancarios febrero',        $supplierId, $admin);

        // Marzo 2026
        $this->expense(5100.00,  '2026-03-05', 'EXP-005', 'Impuesto desembolsos febrero',    $supplierId, $admin);
        $this->expense(2300.00,  '2026-03-12', 'EXP-006', 'Gastos operativos marzo',         $supplierId, $admin);
    }

    private function financing(
        Company $company,
        Client $client,
        float $amount,
        string $requestDate,
        int $termDays,
        string $status,
        ?string $disbursedAt,
        ?string $collectedAt,
        int $registeredBy
    ): Financing {
        $commission     = round($amount * $this->commissionRate, 2);
        $transferAmount = $amount - $commission;
        $req            = Carbon::parse($requestDate);

        $data = [
            'company_id'      => $company->id,
            'client_id'       => $client->id,
            'amount'          => $amount,
            'commission'      => $commission,
            'transfer_amount' => $transferAmount,
            'term_days'       => $termDays,
            'request_date'    => $req,
            'due_date'        => $req->copy()->addDays($termDays),
            'status'          => $status,
            'registered_by'   => $registeredBy,
        ];

        if ($status === 'collected') {
            $data['collected_amount'] = $amount;
        }

        if ($disbursedAt) {
            $data['disbursed_at'] = Carbon::parse($disbursedAt);
            $data['issue_period'] = Carbon::parse($disbursedAt)->format('Y-m');
        }

        if ($collectedAt) {
            $data['collected_at']      = Carbon::parse($collectedAt);
            $data['collection_period'] = Carbon::parse($collectedAt)->format('Y-m');
        }

        return Financing::create($data);
    }

    private function transaction(
        string $type,
        Company $company,
        array $financings,
        string $bank,
        string $txNumber,
        string $txDate,
        string $status,
        ?User $confirmedBy,
        ?User $registeredByUser = null
    ): Transaction {
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        $amount = collect($financings)->sum(fn (Financing $f) =>
            $type === 'disbursement' ? (float) $f->transfer_amount : (float) $f->amount
        );

        $tx = Transaction::create([
            'type'               => $type,
            'company_id'         => $company->id,
            'amount'             => $amount,
            'bank'               => $bank,
            'transaction_number' => $txNumber,
            'transaction_date'   => Carbon::parse($txDate),
            'status'             => $status,
            'registered_by'      => ($registeredByUser ?? $admin)->id,
            'confirmed_by'       => $confirmedBy?->id,
            'confirmed_at'       => $confirmedBy ? now() : null,
        ]);

        foreach ($financings as $f) {
            DB::table('transaction_financings')->insert([
                'transaction_id' => $tx->id,
                'financing_id'   => $f->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        return $tx;
    }

    private function expense(
        float $amount,
        string $date,
        string $txNumber,
        string $notes,
        ?int $supplierId,
        User $admin
    ): void {
        Transaction::create([
            'type'               => 'expense',
            'amount'             => $amount,
            'bank'               => 'BanReservas',
            'transaction_number' => $txNumber,
            'transaction_date'   => Carbon::parse($date),
            'status'             => 'confirmed',
            'notes'              => $notes,
            'supplier_id'        => $supplierId,
            'registered_by'      => $admin->id,
            'confirmed_by'       => $admin->id,
            'confirmed_at'       => Carbon::parse($date),
        ]);
    }
}
