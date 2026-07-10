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
        return true; // authorization handled in controller via canManage()
    }

    public function rules(): array
    {
        return [
            'provinces' => ['sometimes', 'array'],
            'provinces.*.province_id' => [
                'required_with:provinces',
                'integer',
                'distinct',
                Rule::exists('provinces', 'id'),
            ],
            'provinces.*.district_ids' => ['sometimes', 'array'],
            'provinces.*.district_ids.*' => [
                'integer',
                Rule::exists('districts', 'id'),
            ],

            'countries' => ['sometimes', 'array'],
            'countries.*' => ['string', 'max:255'],
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
            $provinces = $this->input('provinces', []);
            $countries = $this->input('countries', []);

            if (empty($provinces) && empty($countries)) {
                $validator->errors()->add(
                    'provinces',
                    'Select at least one province or enter a country.'
                );
                return;
            }

            foreach ($provinces as $index => $entry) {
                $provinceId = $entry['province_id'] ?? null;
                $districtIds = $entry['district_ids'] ?? [];

                if (empty($districtIds) || !$provinceId) {
                    continue;
                }

                $validCount = District::where('province_id', $provinceId)
                    ->whereIn('id', $districtIds)
                    ->count();

                if ($validCount !== count(array_unique($districtIds))) {
                    $validator->errors()->add(
                        "provinces.$index.district_ids",
                        'One or more selected districts do not belong to the selected province.'
                    );
                }
            }
        });
    }
}