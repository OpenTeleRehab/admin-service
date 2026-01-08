<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use App\Models\Category;
use Illuminate\Console\Command;
use App\Helpers\GlobalDataSyncHelper;
use App\Models\EducationMaterial;
use App\Models\Questionnaire;
use Illuminate\Support\Facades\DB;

class SyncCategoryData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-category-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync categories data from global to other organization';

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

        $this->info('Starting category sync...');

        // Fetch categories from global
        $globalCategories = GlobalDataSyncHelper::fetchData('get-categories');
        if (!$globalCategories) {
            $this->error('Failed to fetch categories from global.');
            return;
        }

        // Collect all global category IDs for deletion check
        $globalCategoryIds = collect($globalCategories)->pluck('id')->toArray();

        // Bulk fetch all exercises, education materials, questionnaires once
        $allExercises = Exercise::whereNotNull('exercise_id')->where('global', true)
            ->get(['id', 'exercise_id'])
            ->keyBy('exercise_id'); // key = global id for fast lookup

        $allEduMaterials = EducationMaterial::whereNotNull('education_material_id')->where('global', true)
            ->get(['id', 'education_material_id'])
            ->keyBy('education_material_id');

        $allQuestionnaires = Questionnaire::whereNotNull('questionnaire_id')->where('global', true)
            ->get(['id', 'questionnaire_id'])
            ->keyBy('questionnaire_id');

        foreach ($globalCategories as $globalCategory) {

            // Upsert category
            DB::table('categories')->updateOrInsert(
                ['global_category_id' => $globalCategory->id],
                [
                    'title' => json_encode($globalCategory->title),
                    'type' => $globalCategory->type,
                    'auto_translated' => json_encode($globalCategory->auto_translated)
                ]
            );
            $parentId = Category::where('global_category_id', $globalCategory->parent_id)->first()?->id ?? null;
            $category = Category::where('global_category_id', $globalCategory->id)->first();
            $category->parent_id = $parentId;
            $category->save();
            // Sync exercises gategories
            $exerciseIds = collect($globalCategory->exercises ?? [])
                ->map(fn($exercise) => $allExercises[$exercise->id]->id ?? null)
                ->filter()
                ->toArray();
            $category->exercises()->sync($exerciseIds);

            // Sync education materials categories
            $eduMaterialIds = collect($globalCategory->educationMaterials ?? [])
                ->map(fn($material) => $allEduMaterials[$material->id]->id ?? null)
                ->filter()
                ->toArray();
            $category->educationMaterials()->sync($eduMaterialIds);

            // Sync questionnaires categories
            $questionnaireIds = collect($globalCategory->questionnaires ?? [])
                ->map(fn($questionnaire) => $allQuestionnaires[$questionnaire->id]->id ?? null)
                ->filter()
                ->toArray();
            $category->questionnaires()->sync($questionnaireIds);
        }

        // Delete categories that no longer exist in the global categories
        Category::whereNotNull('global_category_id')
            ->whereNotIn('global_category_id', $globalCategoryIds)
            ->delete();

        $this->info('Category sync completed successfully!');
}
}
