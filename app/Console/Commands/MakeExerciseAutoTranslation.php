<?php

namespace App\Console\Commands;

use App\Events\ApplyExerciseAutoTranslationEvent;
use App\Models\Exercise;
use Illuminate\Console\Command;

class MakeExerciseAutoTranslation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:make-exercise-auto-translator {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make exercise auto translator by id';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $exercise = Exercise::find($this->argument('id'));

        if ($exercise) {
            // Add automatic translation for Exercise.
            event(new ApplyExerciseAutoTranslationEvent($exercise));

            $this->info('This exercise applied auto translate successfully.');

            return true;
        }

        $this->error('The exercise you are looking for does not exists.');

        return false;
    }
}
