<?php

namespace App\Domains\Admissions\Http\Requests;

use App\Domains\Admissions\Enums\AdmissionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeAdmissionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_map(fn (AdmissionStatus $status) => $status->value, AdmissionStatus::decidable()))],
            // Un refus doit etre motive : c'est ce que le tuteur demandera.
            'note' => [Rule::requiredIf(fn () => $this->input('status') === AdmissionStatus::Rejected->value), 'nullable', 'string', 'max:2000'],
        ];
    }

    public function status(): AdmissionStatus
    {
        return AdmissionStatus::from($this->validated('status'));
    }
}
