<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoryProjectPresentationRequest extends FormRequest
{
    /** @var list<string> */
    public const COVER_FRAME_IDS = [
        'classic-leather',
        'minimal-gilt',
        'modern-bevel',
        'ornate-baroque',
        'deckle-paper',
        'none',
    ];

    public function authorize(): bool
    {
        $story = $this->route('story');

        return $story && $this->user()?->can('update', $story);
    }

    public function rules(): array
    {
        $kinds = ['solid', 'gradient', 'image', 'gif', 'ai_image'];

        return [
            'sharing_enabled' => ['sometimes', 'boolean'],
            'flip_gameplay_enabled' => ['sometimes', 'boolean'],
            'cover_front' => ['sometimes', 'nullable', 'array'],
            'cover_front.kind' => ['sometimes', Rule::in($kinds)],
            'cover_front.color' => ['sometimes', 'nullable', 'string', 'max:32'],
            'cover_front.angle' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:360'],
            'cover_front.from' => ['sometimes', 'nullable', 'string', 'max:32'],
            'cover_front.to' => ['sometimes', 'nullable', 'string', 'max:32'],
            'cover_front.path' => ['sometimes', 'nullable', 'string', 'max:512'],
            'cover_front.prompt' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cover_front.frame' => ['sometimes', 'nullable', 'string', Rule::in(self::COVER_FRAME_IDS)],
            'cover_back' => ['sometimes', 'nullable', 'array'],
            'cover_back.kind' => ['sometimes', Rule::in($kinds)],
            'cover_back.color' => ['sometimes', 'nullable', 'string', 'max:32'],
            'cover_back.angle' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:360'],
            'cover_back.from' => ['sometimes', 'nullable', 'string', 'max:32'],
            'cover_back.to' => ['sometimes', 'nullable', 'string', 'max:32'],
            'cover_back.path' => ['sometimes', 'nullable', 'string', 'max:512'],
            'cover_back.prompt' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cover_back.frame' => ['sometimes', 'nullable', 'string', Rule::in(self::COVER_FRAME_IDS)],
            'flip_settings' => ['sometimes', 'nullable', 'array'],
            'flip_settings.audioOnFlip' => ['sometimes', 'boolean'],
            'flip_settings.spreadAudio' => ['sometimes', Rule::in(['first', 'sequence'])],
            'flip_settings.autoAdvance' => ['sometimes', Rule::in(['off', 'timer', 'afterAudio'])],
            'flip_settings.timerDelaySec' => ['sometimes', 'integer', 'min:2', 'max:30'],
            'flip_settings.flipDuration' => ['sometimes', 'integer', 'min:250', 'max:1500'],
            'flip_settings.display' => ['sometimes', Rule::in(['single', 'double'])],
            'flip_settings.gradients' => ['sometimes', 'boolean'],
            'flip_settings.acceleration' => ['sometimes', 'boolean'],
            'flip_settings.elevation' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'flip_settings.bookZoomPercent' => ['sometimes', 'integer', 'min:70', 'max:130'],
        ];
    }
}
