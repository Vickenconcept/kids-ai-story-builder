<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoryPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page && $this->user()?->can('update', $page);
    }

    public function rules(): array
    {
        return [
            'text_content' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'quiz_questions' => ['sometimes', 'nullable', 'array', 'max:20'],
            'quiz_questions.*.question' => ['required_with:quiz_questions', 'string', 'max:2000'],
            'quiz_questions.*.choices' => ['nullable', 'array', 'max:12'],
            'quiz_questions.*.choices.*' => ['string', 'max:500'],
            'quiz_questions.*.answer' => ['required_with:quiz_questions', 'string', 'max:500'],
        ];
    }
}
