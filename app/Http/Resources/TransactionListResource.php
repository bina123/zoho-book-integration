<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the full payload returned by TransactionsAssembler::assemble().
 */
final class TransactionListResource extends JsonResource
{
    /**
     * Disable Laravel's default `data` wrapper — the JS client and existing
     * contract expect the payload at the top level.
     */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'meta' => $this->resource['meta'],
            'transactions' => TransactionResource::collection($this->resource['transactions']),
            'opening_balance' => $this->resource['opening_balance']
                ? new AccountBalanceResource($this->resource['opening_balance'])
                : null,
            'closing_balance' => $this->resource['closing_balance']
                ? new AccountBalanceResource($this->resource['closing_balance'])
                : null,
            'totals' => $this->resource['totals'],
        ];
    }
}
