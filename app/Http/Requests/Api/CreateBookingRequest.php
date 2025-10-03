<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
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
            'product_variant_id' => 'required|string',
            'slot_start_iso' => 'required|date|after:now',
            'seats' => 'integer|min:1|max:10',
            'customer' => 'required|array',
            'customer.name' => 'required|string|max:255',
            'customer.phone' => 'required|string|max:20',
            'customer.email' => 'required|email|max:255',
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
            'product_variant_id.required' => 'Product variant ID is required.',
            'slot_start_iso.required' => 'Slot start time is required.',
            'slot_start_iso.after' => 'Slot start time must be in the future.',
            'seats.required' => 'Number of seats is required.',
            'seats.min' => 'At least 1 seat is required.',
            'seats.max' => 'Maximum 10 seats allowed.',
            'customer.required' => 'Customer information is required.',
            'customer.name.required' => 'Customer name is required.',
            'customer.phone.required' => 'Customer phone is required.',
            'customer.email.required' => 'Customer email is required.',
            'customer.email.email' => 'Customer email must be a valid email address.',
        ];
    }
}
