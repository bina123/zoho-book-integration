<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AccountBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->resource['label'],
            'name' => $this->resource['name'],
            'debit' => $this->resource['debit'],
            'credit' => $this->resource['credit'],
        ];
    }
}
