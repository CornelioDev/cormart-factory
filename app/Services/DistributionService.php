<?php

namespace App\Services;

use App\Models\ClosingDistribution;
use App\Models\ClosingParametersSnapshot;
use App\Models\FundMember;
use App\Models\Financing;
use App\Models\MonthlyClosing;
use App\Models\Parameter;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DistributionService
{
    public function calculate(string $period): array
    {
        $params = Parameter::all()->pluck('value', 'key');

        $commissionPct   = (float) $params['commission_pct'];
        $fixedReturnPct  = (float) $params['fixed_return_pct'];
        $reservePct      = (float) $params['reserve_pct'];
        $inKindPct       = (float) $params['in_kind_pct'];

        [$year, $month] = explode('-', $period);

        $capitalMembers = FundMember::where('type', 'capital')
            ->where('active', true)
            ->get();

        $inKindMember = FundMember::where('type', 'in_kind')
            ->where('active', true)
            ->first();

        $totalCommissions = Financing::where('status', 'collected')
            ->where('collection_period', $period)
            ->sum('commission');

        $totalExpenses = Transaction::where('type', 'expense')
            ->where('status', 'confirmed')
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->sum('amount');

        $totalExpenses = round((float) $totalExpenses, 2);
        $baseEarnings  = round((float) $totalCommissions - $totalExpenses, 2);

        $totalFixed = $capitalMembers->sum(function ($member) use ($fixedReturnPct) {
            return round($member->contribution * ($fixedReturnPct / 100), 2);
        });

        $netProfit        = round($baseEarnings - $totalFixed, 2);
        $reserve          = round($netProfit * ($reservePct / 100), 2);
        $postReserve      = round($netProfit - $reserve, 2);
        $inKindPayment    = round($postReserve * ($inKindPct / 100), 2);
        $availableCapital = round($postReserve - $inKindPayment, 2);

        $verificationDiff = round(
            (float) $totalCommissions - ($totalExpenses + $totalFixed + $reserve + $inKindPayment + $availableCapital),
            2
        );

        $memberDistributions = $capitalMembers->map(function ($member) use ($fixedReturnPct, $availableCapital) {
            $fixed        = round($member->contribution * ($fixedReturnPct / 100), 2);
            $proportional = round($availableCapital * ($member->fund_percentage / 100), 2);
            return [
                'fund_member_id'      => $member->id,
                'name'                => $member->name,
                'type'                => $member->type,
                'fixed_amount'        => $fixed,
                'proportional_amount' => $proportional,
                'total_amount'        => round($fixed + $proportional, 2),
            ];
        })->toArray();

        if ($inKindMember) {
            $memberDistributions[] = [
                'fund_member_id'      => $inKindMember->id,
                'name'                => $inKindMember->name,
                'type'                => $inKindMember->type,
                'fixed_amount'        => 0,
                'proportional_amount' => $inKindPayment,
                'total_amount'        => $inKindPayment,
            ];
        }

        return [
            'period'                => $period,
            'total_commissions'     => $totalCommissions,
            'total_expenses'        => $totalExpenses,
            'base_earnings'         => $baseEarnings,
            'total_fixed'           => $totalFixed,
            'net_profit'            => $netProfit,
            'reserve'               => $reserve,
            'post_reserve'          => $postReserve,
            'in_kind_payment'       => $inKindPayment,
            'available_for_capital' => $availableCapital,
            'verification_diff'     => $verificationDiff,
            'distributions'         => $memberDistributions,
            'parameters'            => $params->toArray(),
            'financings_count'      => Financing::where('status', 'collected')
                                        ->where('collection_period', $period)
                                        ->count(),
        ];
    }

    public function executeClosing(string $period, int $executedBy): MonthlyClosing
    {
        if (MonthlyClosing::where('period', $period)->exists()) {
            throw new \Exception("El periodo {$period} ya tiene un cierre ejecutado.");
        }

        $data = $this->calculate($period);

        return DB::transaction(function () use ($data, $period, $executedBy) {
            $closing = MonthlyClosing::create([
                'period'                => $period,
                'total_commissions'     => $data['total_commissions'],
                'total_expenses'        => $data['total_expenses'],
                'total_fixed'           => $data['total_fixed'],
                'net_profit'            => $data['net_profit'],
                'reserve'               => $data['reserve'],
                'post_reserve'          => $data['post_reserve'],
                'in_kind_payment'       => $data['in_kind_payment'],
                'available_for_capital' => $data['available_for_capital'],
                'verification_diff'     => $data['verification_diff'],
                'executed_by'           => $executedBy,
                'closed_at'             => now(),
            ]);

            foreach ($data['distributions'] as $dist) {
                ClosingDistribution::create([
                    'monthly_closing_id'  => $closing->id,
                    'fund_member_id'      => $dist['fund_member_id'],
                    'fixed_amount'        => $dist['fixed_amount'],
                    'proportional_amount' => $dist['proportional_amount'],
                    'total_amount'        => $dist['total_amount'],
                ]);
            }

            foreach ($data['parameters'] as $key => $value) {
                ClosingParametersSnapshot::create([
                    'monthly_closing_id' => $closing->id,
                    'key'                => $key,
                    'value'              => $value,
                ]);
            }

            // Crear transacciones de distribución por miembro y debitar del fondo
            $transactionService = new TransactionService();
            $fundAccountService = new FundAccountService();
            $totalDistributed = 0;

            foreach ($data['distributions'] as $dist) {
                if ($dist['total_amount'] > 0) {
                    $transactionService->createEarningDistribution(
                        $dist['fund_member_id'],
                        $dist['total_amount'],
                        $period,
                    );
                    $totalDistributed += $dist['total_amount'];
                }
            }

            if ($totalDistributed > 0) {
                $fundAccountService->debit($totalDistributed);
            }

            return $closing;
        });
    }
}
