<?php

namespace App\Console\Commands;

use App\Models\Parameter;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Console\Command;

class GenerateRetroactiveTaxExpenses extends Command
{
    protected $signature = 'transactions:generate-tax-expenses
                            {--dry-run : Muestra lo que se crearía sin ejecutar}';

    protected $description = 'Genera gastos de impuesto retroactivos para desembolsos confirmados que no tienen impuesto asociado';

    public function handle(): int
    {
        $taxPct = (float) Parameter::where('key', 'tax_pct')->value('value');

        if ($taxPct <= 0) {
            $this->error("tax_pct es {$taxPct}. No se generarán gastos.");
            return self::FAILURE;
        }

        $dgii = Supplier::firstOrCreate(['name' => 'DGII']);

        // Buscar desembolsos confirmados que NO tienen un gasto de impuesto asociado
        $existingTaxNotes = Transaction::where('type', 'expense')
            ->where('notes', 'like', 'Impuesto por transacción — Automático%')
            ->pluck('notes')
            ->map(fn ($note) => preg_match('/desembolso (TX\d+)/', $note, $m) ? $m[1] : null)
            ->filter()
            ->toArray();

        $disbursements = Transaction::where('type', 'disbursement')
            ->where('status', 'confirmed')
            ->get();

        $toCreate = $disbursements->filter(function (Transaction $d) use ($existingTaxNotes) {
            $txCode = 'TX' . str_pad($d->id, 6, '0', STR_PAD_LEFT);
            return ! in_array($txCode, $existingTaxNotes);
        });

        if ($toCreate->isEmpty()) {
            $this->info('No hay desembolsos sin impuesto asociado. Nada que hacer.');
            return self::SUCCESS;
        }

        $this->info("Desembolsos sin impuesto: {$toCreate->count()}");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'TX Code', 'Monto Desembolso', 'Impuesto (' . $taxPct . '%)'],
                $toCreate->map(fn (Transaction $d) => [
                    $d->id,
                    'TX' . str_pad($d->id, 6, '0', STR_PAD_LEFT),
                    number_format((float) $d->amount, 2),
                    number_format(round((float) $d->amount * ($taxPct / 100), 2), 2),
                ])->toArray()
            );
            $this->warn('Modo dry-run. No se creó nada.');
            return self::SUCCESS;
        }

        $created = 0;

        foreach ($toCreate as $disbursement) {
            $taxAmount = round((float) $disbursement->amount * ($taxPct / 100), 2);
            $txCode = 'TX' . str_pad($disbursement->id, 6, '0', STR_PAD_LEFT);

            Transaction::create([
                'type'               => 'expense',
                'status'             => 'confirmed',
                'amount'             => $taxAmount,
                'bank'               => $disbursement->bank,
                'transaction_number' => $txCode,
                'transaction_date'   => $disbursement->transaction_date,
                'supplier_id'        => $dgii->id,
                'notes'              => "Impuesto por transacción — Automático ({$taxPct}%) por desembolso {$txCode}",
                'registered_by'      => $disbursement->registered_by,
                'confirmed_by'       => $disbursement->registered_by,
                'confirmed_at'       => now(),
            ]);

            $created++;
        }

        $this->info("Se crearon {$created} gastos de impuesto retroactivos.");

        return self::SUCCESS;
    }
}
