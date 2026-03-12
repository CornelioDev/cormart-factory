<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
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

        // ── 1. COBRADOS (feb 14 – mar 8) ────────────────────────────────────
        // Cormart: Innovatech + Caribbean cobrados juntos el Mar 1
        $f1 = $this->financing($cormart, $innovatech, 80000,  '2026-02-14', 15, 'collected', '2026-02-15', '2026-03-01', $cormart_user->id);
        $f2 = $this->financing($cormart, $caribbean,  200000, '2026-02-15', 15, 'collected', '2026-02-16', '2026-03-01', $cormart_user->id);

        // Cormart: Grupo Rodriguez cobrado Mar 5 (cobro individual)
        $f3 = $this->financing($cormart, $rodriguez,  120000, '2026-02-20', 15, 'collected', '2026-02-21', '2026-03-05', $operator->id);

        // Ysetech: DataCenter + TechPro desembolsados juntos, cobrados juntos Mar 8
        $f4 = $this->financing($ysetech, $datacenter, 150000, '2026-02-17', 15, 'collected', '2026-02-18', '2026-03-08', $ysetech_user->id);
        $f5 = $this->financing($ysetech, $techpro,    95000,  '2026-02-20', 15, 'collected', '2026-02-21', '2026-03-08', $ysetech_user->id);

        // TX desembolsos cobrados
        $this->transaction('disbursement', $cormart, [$f1, $f2], 'BanReservas',   'DIS-C-0001', '2026-02-16', 'confirmed', $admin);
        $this->transaction('collection',   $cormart, [$f1, $f2], 'BHD',            'COB-C-0001', '2026-03-01', 'confirmed', $admin);

        $this->transaction('disbursement', $cormart, [$f3],      'Banco Popular',  'DIS-C-0002', '2026-02-21', 'confirmed', $admin);
        $this->transaction('collection',   $cormart, [$f3],      'BanReservas',    'COB-C-0002', '2026-03-05', 'confirmed', $admin);

        $this->transaction('disbursement', $ysetech, [$f4, $f5], 'BHD',            'DIS-Y-0001', '2026-02-18', 'confirmed', $admin);
        $this->transaction('collection',   $ysetech, [$f4, $f5], 'BHD',            'COB-Y-0001', '2026-03-08', 'confirmed', $admin);

        // ── 2. DESEMBOLSADOS (en calle, vencen después del 11/Mar) ──────────
        $f6 = $this->financing($cormart, $rodriguez,  90000, '2026-03-01', 30, 'disbursed', '2026-03-03', null, $cormart_user->id);
        $f7 = $this->financing($ysetech, $sistemas,   60000, '2026-03-03', 30, 'disbursed', '2026-03-05', null, $ysetech_user->id);
        $f8 = $this->financing($cormart, $innovatech, 55000, '2026-03-06', 30, 'disbursed', '2026-03-07', null, $operator->id);
        $f9 = $this->financing($ysetech, $datacenter, 75000, '2026-03-07', 30, 'disbursed', '2026-03-08', null, $ysetech_user->id);

        $this->transaction('disbursement', $cormart, [$f6], 'BanReservas',  'DIS-C-0003', '2026-03-03', 'confirmed', $admin);
        $this->transaction('disbursement', $ysetech, [$f7], 'Banco Popular', 'DIS-Y-0002', '2026-03-05', 'confirmed', $admin);
        $this->transaction('disbursement', $cormart, [$f8], 'BHD',           'DIS-C-0004', '2026-03-07', 'confirmed', $admin);
        $this->transaction('disbursement', $ysetech, [$f9], 'BanReservas',  'DIS-Y-0003', '2026-03-08', 'confirmed', $admin);

        // Cobro pendiente registrado por Ysetech user (sin confirmar)
        $this->transaction('collection', $ysetech, [$f9], 'BHD', 'COB-Y-PEND-001', '2026-03-11', 'pending', null, $ysetech_user);

        // ── 3. SOLICITADOS (esperando desembolso) ────────────────────────────
        $this->financing($cormart, $caribbean,  45000,  '2026-03-08', 30, 'solicited', null, null, $cormart_user->id);
        $this->financing($ysetech, $techpro,    110000, '2026-03-09', 30, 'solicited', null, null, $ysetech_user->id);
        $this->financing($cormart, $innovatech, 70000,  '2026-03-10', 30, 'solicited', null, null, $cormart_user->id);
        $this->financing($ysetech, $sistemas,   85000,  '2026-03-11', 30, 'solicited', null, null, $operator->id);
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
}
