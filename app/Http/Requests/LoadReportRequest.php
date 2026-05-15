<?php

namespace App\Http\Requests;

use App\Rules\MonthRule;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

final class LoadReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'month_a' => $this->query('month_a', Carbon::now()->format('Y-m')),
            'month_b' => $this->query('month_b', Carbon::now()->subMonth()->format('Y-m')),
        ]);
    }

    public function rules(): array
    {
        return [
            'month_a' => ['required', new MonthRule],
            'month_b' => ['required', new MonthRule],
        ];
    }

    public function monthA(): string
    {
        return (string) $this->validated('month_a');
    }

    public function monthB(): string
    {
        return (string) $this->validated('month_b');
    }
}
