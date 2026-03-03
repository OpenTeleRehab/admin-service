<?php

namespace App\Services;

use App\Models\HealthCondition;
use App\Models\HealthConditionGroup;
use App\Http\Resources\HealthConditionResource;
use App\Http\Resources\HealthConditionGroupResource;

class HealthConditionService
{
    /**
     * @param string|null $ids Comma-separated IDs
     * @param string|null $title Title to search
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findHealthConditions($ids = null, $title = null, $languageCode = null)
    {
        $languageCode ??= config('app.fallback_locale');
        $query = HealthCondition::query();

        // If IDs are provided
        if ($ids) {
            $idsArray = explode(',', $ids);
            $query->whereIn('id', $idsArray);
        }

        // If title is provided
        if ($title) {
            $query->whereRaw(
                "LOWER(json_unquote(json_extract(title, '$.\"$languageCode\"'))) LIKE ?",
                ['%' . strtolower($title) . '%']
            );;
        }

        $healthConditions = $query->get();

        return HealthConditionResource::collection($healthConditions);
    }

    /**
     * @param string|null $ids Comma-separated IDs
     * @param string|null $title Title to search
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findHealthConditionGroups($ids = null, $title = null, $languageCode = null)
    {
        $languageCode ??= config('app.fallback_locale');
        $query = HealthConditionGroup::query();

        // If IDs are provided
        if ($ids) {
            $idsArray = explode(',', $ids);
            $query->whereIn('id', $idsArray);
        }

        // If title is provided
        if ($title) {
            $query->whereRaw(
                "LOWER(json_unquote(json_extract(title, '$.\"$languageCode\"'))) LIKE ?",
                ['%' . strtolower($title) . '%']
            );
        }

        $healthConditionGroups = $query->get();

        return HealthConditionGroupResource::collection($healthConditionGroups);
    }

    public function getHealthConditions(array $groupIds = [], array $conditionIds = []): array
    {
        $groups = collect();
        $conditions = collect();

        // Fetch groups from database if IDs are provided
        if (!empty($groupIds)) {
            $groups = $this->findHealthConditionGroups(implode(',', $groupIds))
                        ->keyBy('id');
        }

        // Fetch conditions from database if IDs are provided
        if (!empty($conditionIds)) {
            $conditions = $this->findHealthConditions(implode(',', $conditionIds))
                            ->keyBy('id');
        }

        return [
            'groups' => $groups,
            'conditions' => $conditions,
        ];
    }

}
