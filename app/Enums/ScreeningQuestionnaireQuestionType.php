<?php

namespace App\Enums;

enum ScreeningQuestionnaireQuestionType: string
{
    case CHECKBOX = 'checkbox';

    case RADIO = 'radio';

    case OPEN_TEXT = 'open-text';

    case OPEN_NUMBER = 'open-number';

    case RATING = 'rating';

    case NOTE = 'note';
}
