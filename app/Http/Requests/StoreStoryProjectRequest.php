<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStoryProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'topic' => ['required', 'string', 'max:500'],
            'lesson_type' => ['required', 'string', 'max:80'],
            'age_group' => ['required', 'string', 'max:32'],
            'page_count' => ['required', 'integer', 'min:3', 'max:15'],
            'illustration_style' => ['required', 'string', 'max:80'],
            'include_quiz' => ['sometimes', 'boolean'],
            'include_narration' => ['sometimes', 'boolean'],
            'include_video' => ['sometimes', 'boolean'],
        ];
    }
}
