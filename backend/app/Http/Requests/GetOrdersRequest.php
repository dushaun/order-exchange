<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'symbol' => 'required|string|in:BTC,ETH',
        ];
    }

    public function messages(): array
    {
        return [
            'symbol.required' => 'The symbol parameter is required.',
            'symbol.in' => 'The symbol must be either BTC or ETH.',
        ];
    }
}
