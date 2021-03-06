<table>
    <thead>
    <tr>
        <th>id</th>
        <th>title</th>
        <th>files</th>
        <th>set_default_sets_and_reps</th>
        <th>sets</th>
        <th>reps</th>
        <th>collect_sets_and_reps</th>
        <th>collect_pain_level</th>
        <th>categories</th>
        <th>dynamic_fields</th>
    </tr>
    </thead>
    <tbody>
    @foreach($exercises as $exercise)
        <tr>
            <td>{{ $exercise->id }}</td>
            <td>{{ $exercise->title }}</td>
            <td>
                @foreach($exercise->files()->get() as $file)
                    {{ env("APP_URL") . '/api/file/' . $file->id }}{{ $loop->remaining ? ',' : '' }}
                @endforeach
            </td>
            <td>{{ $exercise->sets > 0 ? 'yes' : 'no' }}</td>
            <td>{{ $exercise->sets }}</td>
            <td>{{ $exercise->reps }}</td>
            <td>{{ $exercise->include_feedback ? 'yes' : 'no' }}</td>
            <td>{{ $exercise->get_pain_level ? 'yes' : 'no' }}</td>
            <td>
                @foreach($exercise->categories()->get() as $category)
                    @php
                        $treeTitles = [];
                        $treeCategories = \App\Helpers\CategoryHelper::getRootTreeCategories($category);
                        foreach ($treeCategories as $nodeCategory) {
                            $treeTitles[] = $nodeCategory->title;
                        }
                    @endphp
                    {{ implode('->', $treeTitles) }}{{ $loop->remaining ? ',' : '' }}
                @endforeach
            </td>
            <td>
                @php
                    $additionalFields = $exercise->additionalFields()->get() ?? [];
                @endphp

                @foreach($additionalFields as $additionalField)
                    "{{$additionalField->field }}: {{ $additionalField->value }}"{{ $loop->remaining ? ',' : '' }}
                @endforeach
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
