<?php

namespace App\Services\Story\Text;

use App\Contracts\Story\TextStoryGenerator;
use App\Data\Story\GeneratedStoryOutline;
use App\Data\Story\GeneratedStoryPageContent;
use App\Data\Story\StoryTextInput;

class FakeTextStoryGenerator implements TextStoryGenerator
{
    public function generate(StoryTextInput $input): GeneratedStoryOutline
    {
        $pages = [];
        for ($i = 1; $i <= $input->pageCount; $i++) {
            $quiz = $input->includeQuiz ? [
                [
                    'question' => "What happened on page {$i}?",
                    'choices' => ['A happy moment', 'A sad ending', 'A surprise'],
                    'answer' => 'A happy moment',
                ],
            ] : null;

            $pages[] = new GeneratedStoryPageContent(
                pageNumber: $i,
                text: "Page {$i}: A gentle tale about {$input->topic} — written for ages {$input->age_group}, with a {$input->lessonType} lesson. (Fake AI mode)",
                quizQuestions: $quiz,
            );
        }

        $bible = 'Consistent cast for every page: a friendly animal protagonist that fits the topic "'
            .$input->topic.'". Clearly the same species and proportions on each page—not a human child '
            .'unless the topic is explicitly about people. Match tone to ages '.$input->ageGroup.'.';

        return new GeneratedStoryOutline($pages, $bible);
    }
}
