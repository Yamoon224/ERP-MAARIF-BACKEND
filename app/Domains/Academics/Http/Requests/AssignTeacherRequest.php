<?php

namespace App\Domains\Academics\Http\Requests;

use App\Domains\Academics\Rules\IsTeacher;
use Illuminate\Foundation\Http\FormRequest;

class AssignTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'uuid', new IsTeacher],
        ];
    }
}
