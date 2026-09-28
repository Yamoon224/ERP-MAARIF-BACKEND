<?php

namespace App\Domains\Students\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // sql : export d'un systeme existant (voir StudentImportService, qui ne fait que le lire — jamais l'executer).
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls,sql', 'max:5120'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }
}
