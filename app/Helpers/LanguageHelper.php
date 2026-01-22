<?php

namespace App\Helpers;

use App\Models\Language;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LanguageHelper
{
    /**
     * Validate if the authenticated user is allowed to access a specific language.
     *
     * Super admins are allowed to access any language.
     * Translators can only access languages assigned to them.
     *
     * @param int $langId The ID of the language to validate.
     * @return bool Returns true if the user is allowed to access the language, false otherwise.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the language ID does not exist.
     */
    public static function validateAssignedLanguage($langId): bool
    {
        $user = Auth::user();

        if ($user->type !== User::GROUP_TRANSLATOR) {
            return true;
        }

        $language = Language::findOrFail($langId);

        $isAllowed = $user->translatorLanguages()
            ->where('code', $language->code)
            ->exists();

        if (!$isAllowed) {
            throw new HttpException(422, 'restricted.languages');
        }

        return true;
    }

    public static function validateAssignedLanguageCode($langCode): bool
    {
        $user = Auth::user();

        if ($user->type === User::ADMIN_GROUP_SUPER_ADMIN) {
            return true;
        }

        $isAllowed = $user->translatorLanguages()
            ->where('code', $langCode)
            ->exists();

        if (!$isAllowed) {
            throw new HttpException(422, 'restricted.languages');
        }

        return true;
    }
}
