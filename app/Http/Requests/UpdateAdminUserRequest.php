<?php

namespace App\Http\Requests;

use App\Enums\FeatureTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'story_credits' => ['required', 'integer', 'min:0'],
            'feature_tier' => ['required', Rule::enum(FeatureTier::class)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
