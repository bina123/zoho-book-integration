<?php

namespace App\Http\Requests;

use App\Rules\MonthRule;
use Illuminate\Foundation\Http\FormRequest;

final class TransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'string', 'max:50'],
            'month' => ['required', new MonthRule],
        ];
    }

    public function accountId(): string
    {
        return (string) $this->validated('account_id');
    }

    public function month(): string
    {
        return (string) $this->validated('month');
    }
}
