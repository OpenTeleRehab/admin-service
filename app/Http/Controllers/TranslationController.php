<?php

namespace App\Http\Controllers;

use App\Http\Resources\TranslationResource;
use App\Models\Language;
use App\Models\Localization;
use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TranslationController extends Controller
{
    private const DEFAULT_LANG_CODE = 'en';
    private const TRANSLATION_KEY = 'key';

    /**
     * @OA\Get(
     *     path="/api/translation",
     *     tags={"Translation"},
     *     summary="Lists translations",
     *     operationId="translationList",
     *     @OA\Parameter(
     *         name="filter_platform",
     *         in="query",
     *         description="Filter platform",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="search_value",
     *         in="query",
     *         description=" Search value",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page_size",
     *         in="query",
     *         description="Limit",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $filterPlatform = $request->get('filter_platform', Translation::ADMIN_PORTAL);
        $searchValue = $request->get('search_value');
        $filterValues = $request->get('filters');

        $languages = Language::where('code', '!=', self::DEFAULT_LANG_CODE)->get()->toArray();

        $filterIds = [];
        // TODO filter should apply only for visible columns (frontend grid).
        if ($searchValue && $filterValues) {
            $filterSearchIds = $this->getIdsFromSearchValues($searchValue, $languages, $filterPlatform);
            $filterValueIds = $this->getIdsFromFilterValues($filterValues, $languages, $filterPlatform);
            $filterIds = array_intersect($filterSearchIds, $filterValueIds) ?: [0];
        } elseif ($searchValue && !$filterValues) {
            $filterIds = $this->getIdsFromSearchValues($searchValue, $languages, $filterPlatform) ?: [0];
        } elseif (!$searchValue && $filterValues) {
            $filterIds = $this->getIdsFromFilterValues($filterValues, $languages, $filterPlatform) ?: [0];
        }

        if ($languages) {
            $localizationValues = [];
            foreach ($languages as $key => $language) {
                $localizationValues[$key] = '(SELECT value FROM localizations WHERE language_id = ' . $language['id'] . ' AND translation_id = translations.id) AS `' . $language['code'] . '`';
            }
            $query = Translation::select('id', 'key', 'platform', 'value AS en', DB::raw(implode(',', $localizationValues)));
        } else {
            $query = Translation::select('id', 'key', 'platform', 'value AS en');
        }

        if ($filterIds) {
            $query->whereIn('id', $filterIds);
        } else {
            $query->where('platform', '=', $filterPlatform);
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator $translations */
        $translations = $query->paginate((int) $request->get('page_size'));

        $info = [
            'current_page' => $translations->currentPage(),
            'total_count' => $translations->total(),
            'ids' => $filterIds
        ];

        return [
            'success' => true,
            'data' => $translations->getCollection()->toArray(),
            'info' => $info,
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/translation/i18n/{platform}",
     *     tags={"Translation"},
     *     summary="Get i18n translations",
     *     operationId="getTranslationByPlateform",
     *     @OA\Parameter(
     *         name="platform",
     *         in="path",
     *         description="Platform",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description=" Language id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @param string $platform
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getI18n(Request $request, $platform)
    {
        $languageId = $request->get('lang');
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

        return TranslationResource::collection($translations);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return array
     */
    public function update(Request $request, $id)
    {
        try {
            $data = $request->all();

            // Update default language.
            if (array_key_exists(self::DEFAULT_LANG_CODE, $data)) {
                $translation = Translation::findOrFail($id);
                $translation->fill([
                    'value' => $data[self::DEFAULT_LANG_CODE] ?: ''
                ])->save();
            }

            // Update other language(s).
            $languages = Language::where('code', '!=', self::DEFAULT_LANG_CODE)->get()->toArray();
            foreach ($languages as $language) {
                if (array_key_exists($language['code'], $data)) {
                    $translationValue = $data[$language['code']] ?: '';
                    $localization = Localization::where('translation_id', $id)->where('language_id', $language['id'])->first();
                    if ($localization) {
                        $localization->fill([
                            'value' => $translationValue,
                            'auto_translated' => false,
                        ])->save();
                    } else {
                        Localization::create([
                            'translation_id' => $id,
                            'language_id' => $language['id'],
                            'value' => $translationValue,
                            'auto_translated' => false,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'error_message.localization_update', 'error_message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.localization_update'];
    }

    /**
     * @param string $searchValue
     * @param array $languages
     * @param string $filterPlatform
     *
     * @return array
     */
    private function getIdsFromSearchValues(string $searchValue, array $languages, string $filterPlatform)
    {
        if ($languages) {
            $sql = "
                SELECT id FROM translations WHERE (value LIKE '%{$searchValue}%' OR `key` LIKE '%{$searchValue}%') AND platform = '{$filterPlatform}'
                UNION DISTINCT
                SELECT L.translation_id AS id FROM localizations L LEFT JOIN translations T ON L.translation_id = T.id WHERE L.value LIKE '%{$searchValue}%' AND T.platform = '{$filterPlatform}'
            ";
        } else {
            $sql = "SELECT id FROM translations WHERE (value LIKE '%{$searchValue}%' OR `key` LIKE '%{$searchValue}%') AND platform = '{$filterPlatform}'";
        }

        $filterIds = DB::select(DB::raw($sql));

        return $this->convertFilterIdsToSingleArray($filterIds);
    }

    /**
     * @param array $filterValues
     * @param array $languages
     * @param string $filterPlatform
     *
     * @return array
     */
    private function getIdsFromFilterValues(array $filterValues, array $languages, string $filterPlatform)
    {
        $filterValueIds = [];
        foreach ($filterValues as $filterValue) {
            $filter = json_decode($filterValue, true);
            if ($filter['columnName'] === self::TRANSLATION_KEY) {
                $sql = "SELECT id FROM translations WHERE `key` LIKE '%{$filter['value']}%' AND platform = '{$filterPlatform}'";
            } elseif ($filter['columnName'] === self::DEFAULT_LANG_CODE) {
                $sql = "SELECT id FROM translations WHERE value LIKE '%{$filter['value']}%' AND platform = '{$filterPlatform}'";
            } else {
                $index = array_search($filter['columnName'], array_column($languages, 'code'));
                $language = $languages[$index];
                $sql = "SELECT L.translation_id AS id FROM localizations L INNER JOIN translations T ON L.translation_id = T.id WHERE L.value LIKE '%{$filter['value']}%' AND L.language_id = {$language['id']} AND T.platform = '{$filterPlatform}'";
            }
            $filterIds = DB::select(DB::raw($sql));

            $filterIdsArr = $this->convertFilterIdsToSingleArray($filterIds);
            if ($filterValueIds) {
                $filterValueIds = array_intersect($filterValueIds, $filterIdsArr);
            } else {
                $filterValueIds = $filterIdsArr;
            }
        }

        return $filterValueIds;
    }

    /**
     * @param array $result
     *
     * @return array
     */
    private function convertFilterIdsToSingleArray(array $result)
    {
        $resultArr = [];
        foreach ($result as $row) {
            $resultArr[] = $row->id;
        }

        return $resultArr;
    }
}
