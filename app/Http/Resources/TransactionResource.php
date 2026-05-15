<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{
 *     date: ?string,
 *     account: ?string,
 *     details: string,
 *     transaction_type: ?string,
 *     transaction_number: string,
 *     reference_number: string,
 *     debit: float,
 *     credit: float,
 *     amount: float,
 *     amount_label: string,
 *     entity_id: ?string,
 *     attachment_type: ?string
 * } $resource
 */
final class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->resource['date'],
            'account' => $this->resource['account'],
            'details' => $this->resource['details'],
            'transaction_type' => $this->resource['transaction_type'],
            'transaction_number' => $this->resource['transaction_number'],
            'reference_number' => $this->resource['reference_number'],
            'debit' => $this->resource['debit'],
            'credit' => $this->resource['credit'],
            'amount' => $this->resource['amount'],
            'amount_label' => $this->resource['amount_label'],
            'entity_id' => $this->resource['entity_id'],
            'attachment_type' => $this->resource['attachment_type'],
        ];
    }
}
