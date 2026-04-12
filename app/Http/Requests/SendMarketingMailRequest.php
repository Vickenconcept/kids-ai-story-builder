<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendMarketingMailRequest extends FormRequest
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
        return [
            'subject' => ['required', 'string', 'max:200'],
            'body_html' => ['required', 'string', 'max:500000'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'extra_emails' => ['nullable', 'string', 'max:50000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $userIds = $this->input('user_ids', []);
            $extra = trim((string) $this->input('extra_emails', ''));

            if (! is_array($userIds)) {
                $userIds = [];
            }

            $parsed = [];
            if ($extra !== '') {
                foreach (explode(',', $extra) as $part) {
                    $e = strtolower(trim($part));
                    if ($e !== '') {
                        $parsed[] = $e;
                    }
                }
            }

            if (count($userIds) === 0 && $parsed === []) {
                $validator->errors()->add('recipients', 'Select at least one user or enter at least one email address.');
            }

            foreach ($parsed as $e) {
                if (! filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add('extra_emails', 'Invalid email in list: '.$e);
                }
            }
        });
    }
}
