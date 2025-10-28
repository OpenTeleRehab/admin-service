<?php

namespace App\Enums;

enum UserType: string
{
    case SUPER_ADMIN = 'super_admin';

    case ORGANIZATION_ADMIN = 'organization_admin';

    case COUNTRY_ADMIN = 'country_admin';

    case CLINIC_ADMIN = 'clinic_admin';

    case TRANSLATOR = 'translator';

    case REGIONAL_ADMIN = 'regional_admin';
}
