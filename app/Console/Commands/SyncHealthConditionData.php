<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\GlobalDataSyncHelper;
use App\Models\HealthCondition;
use App\Models\HealthConditionGroup;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncHealthConditionData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-health-condition-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync health condition data from global to other organization';

    /**
     * @return void
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        if (env('APP_NAME') === 'hi') {
            $this->info('Skipping sync on global instance.');
            return;
        }

        $this->alert('Starting health conditions sync...');

        // Fetch health condition groups from global
        $globalHealthConditionGroups = GlobalDataSyncHelper::fetchData('get-health-condition-groups');
        $globalHealthConditionGroupIds = collect($globalHealthConditionGroups)->pluck('id')->toArray();
        $globalHealthConditionIds = [];
        if (!$globalHealthConditionGroups) {
            $this->error('Failed to fetch health condition groups from global.');
            return;
        }

        $this->output->progressStart(count($globalHealthConditionGroups));
        foreach ($globalHealthConditionGroups as $globalHealthConditionGroup) {
            // Upsert health condition group
            DB::table('health_condition_groups')->updateOrInsert(
                ['id' => $globalHealthConditionGroup->id],
                [
                    'title' => json_encode($globalHealthConditionGroup->title),
                    'created_at' => $globalHealthConditionGroup->created_at ? Carbon::parse($globalHealthConditionGroup->created_at) : $globalHealthConditionGroup->created_at,
                    'updated_at' => $globalHealthConditionGroup->updated_at ? Carbon::parse($globalHealthConditionGroup->updated_at) : $globalHealthConditionGroup->updated_at,
                ]
            );

            // Upsert health conditions
            if (isset($globalHealthConditionGroup->health_conditions)) {
                $globalHealthConditions = $globalHealthConditionGroup->health_conditions;
                $globalHealthConditionIds = array_merge($globalHealthConditionIds, collect($globalHealthConditions)->pluck('id')->toArray());
                foreach ($globalHealthConditions as $globalHealthCondition) {
                    DB::table('health_conditions')->updateOrInsert(
                        ['id' => $globalHealthCondition->id],
                        [
                            'title' => json_encode($globalHealthCondition->title),
                            'parent_id' => $globalHealthCondition->parent_id,
                            'created_at' => $globalHealthCondition->created_at ? Carbon::parse($globalHealthCondition->created_at) : $globalHealthCondition->created_at,
                            'updated_at' => $globalHealthCondition->updated_at ? Carbon::parse($globalHealthCondition->updated_at) : $globalHealthCondition->updated_at,
                        ]
                    );
                }
            }
            $this->output->progressAdvance();
        }
        // Delete health condition groups and health conditions that no longer exist in the global data
        HealthConditionGroup::whereNotIn('id', $globalHealthConditionGroupIds)
            ->delete();
        HealthCondition::whereNotIn('id', $globalHealthConditionIds)
            ->delete();
        $this->output->progressFinish();

        $this->info('Health conditions sync completed successfully!');
}
}
