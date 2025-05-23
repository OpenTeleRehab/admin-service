<?php

namespace App\Exports\Templates;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PatientTreatmentTemplate
{
    /**
     * Renders data to the given sheet.
     *
     * @param array $data.
     * @param array $therapists.
     * @return void
     */
    public function template($data, $sheet, $translations)
    {
        $columns = [
            $translations['report.patient_raw_data.clinic'],
            $translations['report.patient_raw_data.patient_id'],
            $translations['common.country'],
            $translations['gender'],
            $translations['common.date_of_birth'],
            $translations['common.age'], 
            $translations['common.status'],
            $translations['report.patient_raw_data.location'],
            $translations['report.patient_raw_data.lead_therapist'],
            $translations['report.patient_raw_data.supplementary_therapist'],
            $translations['report.patient_raw_data.number_of_online_encounter'],
            $translations['report.patient_raw_data.treatment_diagnostic'],
            $translations['report.patient_raw_data.treatment_icd_classification'],
            $translations['report.patient_raw_data.treatment_status'],
            $translations['report.patient_raw_data.treatment_start_date'],
            $translations['report.patient_raw_data.treatment_end_date'],
            $translations['report.patient_raw_data.treatment_initial_adherence'],
            $translations['report.patient_raw_data.treatment_final_adherence'],
            $translations['report.patient_raw_data.treatment_initial_pain'],
            $translations['report.patient_raw_data.treatment_final_pain'],
            $translations['report.patient_raw_data.treatment_daily_goal.initial_achievement_satisfaction'],
            $translations['report.patient_raw_data.treatment_daily_goal.final_achievement_satisfaction'],
            $translations['report.patient_raw_data.treatment_average_daily_goal.achievement_satisfaction'],
            $translations['report.patient_raw_data.treatment_weekly_goal.initial_achievement_satisfaction'],
            $translations['report.patient_raw_data.treatment_weekly_goal.final_achievement_satisfaction'],
            $translations['report.patient_raw_data.treatment_average_weekly_goal.achievement_satisfaction']
        ];

        $headerColIndex = 1;
        foreach ($columns as $column) {
            $endColIndex = $headerColIndex;
            // Convert numeric column index to Excel column letters
            $startCol = Coordinate::stringFromColumnIndex($headerColIndex);
            $endCol = Coordinate::stringFromColumnIndex($endColIndex);

            $sheet->setCellValue($startCol . '1', $column);
            $sheet->getColumnDimension($startCol)->setWidth(20);
            $headerColIndex += 1;
        }

        $sheet->getStyle('A1:' . $endCol . '1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A1:' . $endCol . '1')->getFont()->setBold(true);
        $sheet->getRowDimension('1')->setRowHeight(25);
        $sheet->getStyle('A1:' . $endCol . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:' . $endCol . '1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $row = 2;
        foreach ($data as $dataRow) {
            $colIndex = 1;
            foreach ($dataRow as $dataCol) {
                $startCol = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($startCol . $row,  $dataCol);
                $colIndex++;
            }
            $sheet->getRowDimension($row)->setRowHeight(20);
            // Move to the next row for new patient data
            $row++;
        }

        // Apply Borders and aling center to All Data Rows
        $sheet->getStyle('A2:' . $endCol . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A2:' . $endCol . ($row - 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    }
}
