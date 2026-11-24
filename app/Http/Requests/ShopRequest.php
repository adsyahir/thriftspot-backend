<?php

namespace App\Http\Requests;

use App\Enums\MalaysianState;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShopRequest extends FormRequest
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function authorize(): bool
    {
        $shop = $this->route('shop');

        return $shop === null || (bool) $this->user('api')?->can('update', $shop);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'state' => ['required', Rule::enum(MalaysianState::class)],
            'location_type' => ['required', Rule::in(['google_maps', 'coordinates'])],
            'place_id' => ['nullable', 'string', 'max:255'],
            'address' => ['required_if:location_type,google_maps', 'nullable', 'string', 'max:255'],
            'latitude' => ['required_if:location_type,coordinates', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:location_type,coordinates', 'nullable', 'numeric', 'between:-180,180'],
            'phone_number' => ['required', 'regex:/^\+?[0-9 \-]{8,20}$/'],
            'email' => ['required', 'email', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'social_links.instagram' => ['nullable', 'url:http,https', 'max:255'],
            'social_links.facebook' => ['nullable', 'url:http,https', 'max:255'],
            'social_links.twitter' => ['nullable', 'url:http,https', 'max:255'],
            'social_links.tiktok' => ['nullable', 'url:http,https', 'max:255'],
            'social_links.whatsapp' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/'],
            'operating_hours' => ['required', 'array'],
        ];

        foreach (self::DAYS as $day) {
            $rules["operating_hours.{$day}"] = ['present', $this->dayRule()];
        }

        return $rules;
    }

    /**
     * A day is `false` (closed) or exactly two different H:i strings; overnight spans are allowed.
     */
    private function dayRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === false) {
                return;
            }

            $time = '/^([01]\d|2[0-3]):[0-5]\d$/';

            $valid = is_array($value)
                && count($value) === 2
                && array_is_list($value)
                && is_string($value[0]) && preg_match($time, $value[0]) === 1
                && is_string($value[1]) && preg_match($time, $value[1]) === 1
                && $value[0] !== $value[1];

            if (! $valid) {
                $fail('The :attribute must be closed or have two different times (HH:MM).');
            }
        };
    }
}
