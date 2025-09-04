<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Models\GlobalTreatmentPlan;

class TreatmentPlanHelper
{
    public static function determineStatus(string $startDateStr, string $endDateStr): ?string
    {
        $today = Carbon::today();
        $format = config('settings.date_format');

        $startDate = Carbon::createFromFormat($format, $startDateStr);
        $endDate = Carbon::createFromFormat($format, $endDateStr);

        if ($startDate->lte($today) && $endDate->gte($today)) {
            return GlobalTreatmentPlan::ONGOING_TREATMENT_PLAN;
        } elseif ($startDate->gt($today) && $endDate->gt($today)) {
            return GlobalTreatmentPlan::PLANNED_TREATMENT_PLAN;
        } elseif ($startDate->lt($today) && $endDate->lt($today)) {
            return GlobalTreatmentPlan::FINISHED_TREATMENT_PLAN;
        }

        return null;
    }
}
