<?php

namespace App\Http\Requests;

use App\Enums\FeatureTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoryPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-plans') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tier' => ['required', Rule::enum(FeatureTier::class)],
            'included_credits' => ['required', 'integer', 'min:0', 'max:1000000'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'currency' => ['required', 'string', 'in:USD'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'feature_list' => ['nullable', 'array', 'max:30'],
            'feature_list.*' => ['required', 'string', 'max:200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $features = $this->input('feature_list');

        if (is_string($features)) {
            $features = collect(preg_split('/\r\n|\r|\n/', $features) ?: [])
                ->map(fn ($line) => trim((string) $line))
                ->filter()
                ->values()
                ->all();
        }

        $this->merge([
            'currency' => strtoupper((string) $this->input('currency', 'USD')),
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOL),
            'is_featured' => filter_var($this->input('is_featured', false), FILTER_VALIDATE_BOOL),
            'feature_list' => is_array($features) ? $features : [],
        ]);
    }
}
