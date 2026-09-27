<?php

namespace App\Domains\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'qr_token' => ['required', 'uuid'],
        ];
    }
}
