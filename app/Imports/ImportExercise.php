<?php

namespace App\Imports;

use App\Helpers\FileHelper;
use App\Models\AdditionalField;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Row;

class ImportExercise implements OnEachRow, WithHeadingRow, WithEvents, WithValidation
{
    const DEFAULT_LANGUAGE = 'en';
    const YES = 'yes';

    /**
     * @var string
     */
    private $sheetName = '';

    /**
     * @var array
     */
    private $exerciseIds = [];

    /**
     * @var integer
     */
    private $newRecords = 0;

    /**
     * @var integer
     */
    private $updatedRecords = 0;

    /**
     * @param \Maatwebsite\Excel\Row $row
     *
     * @return void
     */
    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $row = $row->toArray();
        $locale = trim($this->sheetName);
        App::setLocale($locale);

        // Insert or update exercise.
        if ($this->sheetName === self::DEFAULT_LANGUAGE) {
            $data = [
                'title' => isset($row['title']) ? $row['title'] : '',
                'include_feedback' => isset($row['collect_sets_and_reps']) ? ($row['collect_sets_and_reps'] === self::YES) : false,
                'get_pain_level' => isset($row['collect_pain_level']) ? ($row['collect_pain_level'] === self::YES) : false,
                'reps' => isset($row['reps']) ? intval($row['reps']) : 0,
                'sets' => isset($row['sets']) ? intval($row['sets']) : 0,
            ];

            $exercise = Exercise::where('id', $row['id'])->updateOrCreate([], $data);
            $this->exerciseIds[$rowIndex] = $exercise->id;

            if ($exercise->wasRecentlyCreated) {
                $this->newRecords++;
                $exercise->save();
            } else {
                $this->updatedRecords++;
            }

            // Attach categories to exercise.
            ExerciseCategory::where('exercise_id', $exercise->id)->delete();
            $categories = [];

            if (isset($row['categories'])) {
                foreach (explode(',', $row['categories']) as $categoryTree) {
                    $categoriesInTree = explode('->', $categoryTree);
                    $category = trim(end($categoriesInTree));

                    if ($category) {
                        $categories[] = $category;
                    }
                }

                if (count($categories)) {
                    $placeholder = implode(', ', array_fill(0, count($categories), '?'));
                    $categoryIds = Category::whereRaw("JSON_EXTRACT(title, \"$." . self::DEFAULT_LANGUAGE . "\") IN ($placeholder)", $categories)
                        ->pluck('id');

                    $exercise->categories()->attach($categoryIds);
                }
            }

            if (isset($row['dynamic_fields'])) {
                // Insert Additional fields.
                $exercise->additionalFields()->delete();
                AdditionalField::where('exercise_id', $exercise->id)->delete();
                foreach (array_filter(str_getcsv($row['dynamic_fields'], ',')) as $additionalField) {
                    $additionalFieldInfo = explode(':', $additionalField);
                    AdditionalField::create([
                        'field' => trim($additionalFieldInfo[0]),
                        'value' => trim($additionalFieldInfo[1]),
                        'exercise_id' => $exercise->id
                    ]);
                }
            }

            if (isset($row['files'])) {
                // Upload files and attach to exercise.
                $exercise->files()->delete();
                foreach (explode(',', $row['files']) as $index => $fileUrl) {
                    if ($fileUrl) {
                        $info = pathinfo(trim($fileUrl));
                        $contents = @file_get_contents(trim($fileUrl));
                        if ($contents) {
                            $filePath = '/tmp/' . $info['basename'];
                            file_put_contents($filePath, $contents);
                            $uploadedFile = new UploadedFile($filePath, $info['basename']);
                            $file = FileHelper::createFile($uploadedFile, File::EXERCISE_PATH, File::EXERCISE_THUMBNAIL_PATH);
                            if ($file) {
                                $exercise->files()->attach($file->id, ['order' => (int)$index]);
                            }
                        }
                    }
                }
            }
        } else {
            if (isset($this->exerciseIds[$rowIndex])) {
                $data = [
                    'title' => $row['title'],
                ];
                Exercise::find($this->exerciseIds[$rowIndex])->update($data);
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

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'title' => 'required',
        ];
    }

    /**
     * @return array
     */
    public function customValidationMessages()
    {
        return [
            'title.required' => 'error_message.exercise_bulk_upload_title_required',
        ];
    }

    /**
     * @return array
     */
    public function getImportInfo()
    {
        return [
            'new_records' => $this->newRecords,
            'updated_records' => $this->updatedRecords,
        ];
    }

    /**
     * @return string
     */
    public function getCurrentSheetName()
    {
        return $this->sheetName;
    }
}
