<?php

namespace App\Domains\Results\Http\Resources;

use App\Models\GradeScaleBand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GradeScaleBand */
class GradeScaleBandResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'min_average' => $this->min_average,
            'max_average' => $this->max_average,
            'label' => $this->label,
            'decision' => $this->decision === null ? null : ['value' => $this->decision->value, 'label' => $this->decision->label()],
        ];
    }
}
