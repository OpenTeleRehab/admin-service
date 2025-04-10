<?php

namespace App\Exports;

use App\Exports\Templates\AnswerSurveyTemplate;
use App\Models\Survey;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Helpers\TranslationHelper;

class SurveyExport
{
    /**
     * @var string $exportDirectoryName
     */
    protected static $exportDirectoryName = 'exports/';

    /**
     * Exports data to an XLSX file.
     *
     * @param \Illuminate\Http\Request $request.
     * @return string The path to the exported file.
     */
    public static function export(Request $request)
    {
        $translations = TranslationHelper::getTranslations($request->get('lang'));
        $basePath = 'app/' . self::$exportDirectoryName;
        $absolutePath = storage_path($basePath);

        if (!file_exists($absolutePath)) {
            mkdir($absolutePath, 0777, true);
        }

        $survey = Survey::find($request->integer('id'));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $template = new AnswerSurveyTemplate();
        $template->template($survey, $sheet, $translations);

        // Set the first sheet as the active sheet.
        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);
        $fileName = 'Survey_Result_' . date('Y-m-d_His') . '.xlsx';
        $filePath = $absolutePath . '/' . $fileName;

        $writer->save($filePath);
        return $basePath . '/' . $fileName;
    }
}
