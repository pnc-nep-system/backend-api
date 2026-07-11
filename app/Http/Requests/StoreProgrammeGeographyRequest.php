<?php

namespace App\Http\Requests;

use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProgrammeGeographyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provinces' => ['present', 'array'],
            'provinces.*.province_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('provinces', 'id'),
            ],
            'provinces.*.district_ids' => ['sometimes', 'array'],
            'provinces.*.district_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('districts', 'id'),
            ],

            'other_countries' => ['present', 'array'],
            'other_countries.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'provinces.*.province_id.exists' => 'The selected province is invalid.',
            'provinces.*.district_ids.*.exists' => 'One or more selected districts are invalid.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('provinces', []) as $index => $provinceData) {
                $provinceId = $provinceData['province_id'] ?? null;
                $districtIds = $provinceData['district_ids'] ?? [];

                if (empty($districtIds) || ! $provinceId) {
                    continue;
                }

                $validCount = District::where('province_id', $provinceId)
                    ->whereIn('id', $districtIds)
                    ->count();

                if ($validCount !== count(array_unique($districtIds))) {
                    $validator->errors()->add(
                        "provinces.{$index}.district_ids",
                        'One or more selected districts do not belong to the selected province.'
                    );
                }
            }
        });
    }
}
