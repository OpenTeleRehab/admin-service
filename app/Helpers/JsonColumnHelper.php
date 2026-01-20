<?php

namespace App\Helpers;

class JsonColumnHelper
{
    public static function removeFromJsonColumn(string $modelClass, string $jsonColumn, array $valuesToRemove)
    {
        $records = $modelClass::where(function ($query) use ($jsonColumn, $valuesToRemove) {
            foreach ($valuesToRemove as $value) {
                $query->orWhereJsonContains($jsonColumn, $value);
            }
        })->get();

        foreach ($records as $record) {
            $values = $record->{$jsonColumn};

            $values = array_filter($values, fn($v) => !in_array($v, $valuesToRemove));

            if (empty($values)) {
                $record->delete();
            } else {
                $record->{$jsonColumn} = array_values($values);
                $record->save();
            }
        }
    }
}
