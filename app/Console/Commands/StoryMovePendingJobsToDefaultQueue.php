<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StoryMovePendingJobsToDefaultQueue extends Command
{
    protected $signature = 'story:queues-to-default';

    protected $description = 'Move pending jobs from story-* queues onto the default queue (after enabling unified default routing)';

    public function handle(): int
    {
        $from = ['story-text', 'story-image', 'story-audio', 'story-video'];

        $count = DB::table('jobs')->whereIn('queue', $from)->update(['queue' => 'default']);

        $this->info("Updated {$count} row(s) in jobs.queue → default.");

        return self::SUCCESS;
    }
}
