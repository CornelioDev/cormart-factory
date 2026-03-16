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
        return [
            'total_financings'      => $client->financings()->count(),
            'total_amount'          => $client->financings()->sum('amount'),
            'total_commissions'     => $client->financings()->sum('commission'),
            'collected'             => $client->financings()->where('status', 'collected')->count(),
            'partially_collected'   => $client->financings()->where('status', 'partially_collected')->count(),
            'disbursed'             => $client->financings()->where('status', 'disbursed')->count(),
            'solicited'             => $client->financings()->where('status', 'solicited')->count(),
        ];
    }
}
