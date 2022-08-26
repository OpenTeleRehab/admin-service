<?php

namespace App\Imports;

use App\Models\InternationalClassificationDisease;
use App\Models\Language;
use Illuminate\Support\Facades\App;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Row;

class ImportDisease implements OnEachRow, WithHeadingRow, WithEvents
{
    /**
     * @param \Maatwebsite\Excel\Row $row
     *
     * @return void
     */
    public function onRow(Row $row)
    {
        if (!App::isLocale('en')) {
            App::setLocale('en');
        }
        if (isset($row['name'])) {
            $row = $row->toArray();
            $existedDisease = InternationalClassificationDisease::where('name->en', '=', $row['name'])->count();
            $data = [
                'name' => $row['name'],
            ];
            if (!$existedDisease) {
                $disease = InternationalClassificationDisease::where('name', $row['name'])->updateOrCreate([], $data);
                $languages = Language::where('code', '<>', 'en')->get();
                foreach ($languages as $language) {
                    if (isset($row[$language['code']])) {
                        $disease->setTranslation('name', $language['code'], $row[$language['code']]);
                    }
                }
                $disease->save();
            }
        }
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $this->sheetName = $event->getSheet()->getTitle();
            }
        ];
    }
}
