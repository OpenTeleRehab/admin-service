<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\TranslationResource;

class TranslationHelper
{
    /**
     * @param integer $languageId
    *
    * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    */
    public static function getTranslations($languageId = '', $platform = Translation::ADMIN_PORTAL)
    {
        if (!$languageId && Auth::user()) {
            $languageId = Auth::user()->language_id;
        }

        if ($languageId) {
            $translations = Translation::select('key', DB::raw('IFNULL(localizations.value, translations.value) as value'))
                ->leftJoin('localizations', function ($join) use ($languageId) {
                    $join->on('localizations.translation_id', '=', 'translations.id');
                    $join->where('localizations.language_id', '=', $languageId);
                })
                ->where('platform', $platform)
                ->get();
        } else {
            $translations = Translation::where('platform', $platform)->get();
        }

        $data = [];
        if (!empty($translations)) {
            $translationData = TranslationResource::collection($translations);
            foreach ($translationData as $translation) {
                $data[$translation['key']] = $translation['value'];
            }
        }

      return $data;
    }
}
