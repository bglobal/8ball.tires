<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location_id' => 'required|integer|exists:locations,id',
            'service_id' => 'required|integer|exists:services,id',
            'date' => 'nullable|date|after_or_equal:today',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'location_id.required' => 'Location ID is required.',
            'location_id.exists' => 'The selected location does not exist.',
            'service_id.required' => 'Service ID is required.',
            'service_id.exists' => 'The selected service does not exist.',
            'date.date' => 'Date must be a valid date.',
            'date.after_or_equal' => 'Date must be today or in the future.',
        ];
    }
}
