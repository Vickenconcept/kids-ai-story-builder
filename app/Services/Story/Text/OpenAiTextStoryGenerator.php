<?php

namespace App\Services\Story\Text;

use App\Contracts\Story\TextStoryGenerator;
use App\Data\Story\GeneratedStoryOutline;
use App\Data\Story\GeneratedStoryPageContent;
use App\Data\Story\StoryTextInput;
use JsonException;
use OpenAI\Client;

class OpenAiTextStoryGenerator implements TextStoryGenerator
{
    public function __construct(
        private readonly Client $client,
    ) {}

    public function generate(StoryTextInput $input): GeneratedStoryOutline
    {
        $quizHint = $input->includeQuiz
            ? 'Include 1–3 short quiz questions per page as quiz_questions array with question, choices (optional), answer.'
            : 'Set quiz_questions to null for every page.';

        $prompt = <<<TXT
Write a children's story as JSON only (no markdown). Schema:
{
  "character_visual_bible": "2–5 short sentences the illustrator must follow on EVERY page",
  "pages": [
    {
      "page_number": 1,
      "text": "story text for this page",
      "quiz_questions": null or array of { "question": "", "choices": ["",""], "answer": "" }
    }
  ]
}

Rules:
- Exactly {$input->pageCount} pages, page_number 1..{$input->pageCount}.
- Title context: "{$input->title}". Topic: "{$input->topic}".
- Lesson type: {$input->lessonType}. Age group: {$input->ageGroup}.
- Illustration style hint for consistency: {$input->illustrationStyle}.
- Age-appropriate, kind tone; no scary or adult content.
- {$quizHint}
- character_visual_bible is REQUIRED as a single JSON string (not an array). It locks recurring characters for art: name each main character, their SPECIES (fox, rabbit, human child, robot, etc.), size, fur/skin/feathers, clothing or accessories, and art-friendly proportions. If the hero is an animal, state clearly they are that animal on every page—not a human with the animal's name. If the cast is human, say so. Do not introduce a new species later unless the story explicitly transforms them; page text should match this bible.
TXT;

        $response = $this->client->chat()->create([
            'model' => config('story.models.text'),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => 'You write safe, engaging children\'s fiction. Output valid JSON only.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $content = $response->choices[0]->message->content ?? '';
        if ($content === '') {
            throw new JsonException('Empty model response for story JSON.');
        }

        try {
            /** @var array{pages?: array<int, array<string, mixed>>, character_visual_bible?: string} $data */
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JsonException('Story JSON parse failed: '.$e->getMessage(), 0, $e);
        }

        $bible = $this->normalizeCharacterVisualBible($data['character_visual_bible'] ?? null);
        if ($bible === '') {
            $bible = $this->fallbackCharacterVisualBible($input);
        }

        $rawPages = $data['pages'] ?? [];
        $pages = [];
        foreach ($rawPages as $row) {
            $num = (int) ($row['page_number'] ?? 0);
            $text = (string) ($row['text'] ?? '');
            $quiz = $row['quiz_questions'] ?? null;
            if ($num < 1 || $text === '') {
                continue;
            }
            $pages[] = new GeneratedStoryPageContent(
                pageNumber: $num,
                text: $text,
                quizQuestions: is_array($quiz) ? $quiz : null,
            );
        }

        usort($pages, fn (GeneratedStoryPageContent $a, GeneratedStoryPageContent $b): int => $a->pageNumber <=> $b->pageNumber);

        if (count($pages) !== $input->pageCount) {
            throw new JsonException('Story JSON did not return the expected page count.');
        }

        return new GeneratedStoryOutline($pages, $bible);
    }

    private function fallbackCharacterVisualBible(StoryTextInput $input): string
    {
        return 'Illustration consistency: match the title "'.$input->title.'" and topic "'.$input->topic.'". '
            .'If the story names or centers an animal, show that species clearly on every page—not a human child with the same name. '
            .'Keep the same main character proportions, colors, and outfit (if any) across all pages.';
    }

    /**
     * Models sometimes return character_visual_bible as a string or as a list of strings; normalize safely.
     */
    private function normalizeCharacterVisualBible(mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }

        if (is_string($raw)) {
            return trim($raw);
        }

        if (is_array($raw)) {
            $parts = [];
            foreach ($raw as $item) {
                if (is_string($item)) {
                    $t = trim($item);
                    if ($t !== '') {
                        $parts[] = $t;
                    }
                } elseif (is_scalar($item)) {
                    $t = trim((string) $item);
                    if ($t !== '') {
                        $parts[] = $t;
                    }
                }
            }

            return trim(implode(' ', $parts));
        }

        return '';
    }
}
