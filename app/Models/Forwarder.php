<?php

namespace App\Models;

use App\Helpers\KeycloakHelper;
use Illuminate\Database\Eloquent\Model;

class Forwarder extends Model
{
    const GADMIN_SERVICE = 'global_admin';
    const THERAPIST_SERVICE = 'therapist';
    const PATIENT_SERVICE = 'patient';

    /**
     * @param string $service_name
     * @param string|null $host
     *
     * @return mixed
     */
    public static function getAccessToken($service_name, $host = null)
    {
        if ($service_name === self::GADMIN_SERVICE) {
            return KeycloakHelper::getGAdminKeycloakAccessToken();
        }

        if ($service_name === self::THERAPIST_SERVICE) {
            return KeycloakHelper::getTherapistKeycloakAccessToken();
        }

        if ($service_name === self::PATIENT_SERVICE) {
            return KeycloakHelper::getPatientKeycloakAccessToken($host);
        }
    }
}
