<?php

namespace App\Http\Requests;

use App\Rules\MonthRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', new MonthRule],
            'sales_budget' => ['required', 'numeric', 'min:0'],
            'cogs_budget' => ['required', 'numeric', 'min:0'],
        ];
    }
}
