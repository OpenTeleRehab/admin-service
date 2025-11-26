<?php

namespace App\Console\Commands;

use App\Models\Profession;
use Illuminate\Console\Command;

class AssignExistingProfessionForTherapist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:assign-existing-profession-for-therapist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign existing profession for therapist';

    /**
     * The console command example helper.
     *
     * @var string
     */
    protected $help = 'php artisan hi:assign-existing-profession-for-therapist';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->alert('Start assigning existing profession for therapist:');
        Profession::where('type', '')
            ->update(['type' => Profession::TYPE_THERAPIST]);

        $this->info('All professions updated successfully.');

        return Command::SUCCESS;
    }
}
