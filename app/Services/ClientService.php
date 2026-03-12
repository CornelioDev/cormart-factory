<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Collection;

class ClientService
{
    public function search(string $query, ?int $companyId = null): Collection
    {
        $q = Client::where('active', true)
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name');

        if ($companyId) {
            $q->where('company_id', $companyId);
        }

        return $q->get();
    }

    public function createQuick(string $name, int $companyId): Client
    {
        return Client::create([
            'company_id' => $companyId,
            'name'       => $name,
            'active'     => true,
        ]);
    }

    public function getStats(Client $client): array
    {
        $financings = $client->financings();

        return [
            'total_financings'  => $financings->count(),
            'total_amount'      => $financings->sum('amount'),
            'total_commissions' => $financings->sum('commission'),
            'collected'         => $financings->where('status', 'collected')->count(),
            'disbursed'         => $financings->where('status', 'disbursed')->count(),
            'solicited'         => $financings->where('status', 'solicited')->count(),
        ];
    }
}
