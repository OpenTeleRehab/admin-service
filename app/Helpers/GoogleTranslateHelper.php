<?php

namespace App\Helpers;

use Google\Cloud\Translate\V2\TranslateClient;
use Exception;

class GoogleTranslateHelper
{
    /**
     * @var \Google\Cloud\Translate\V2\TranslateClient
     */
    private $translate;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->checkForInvalidConfiguration();

        $this->translate = new TranslateClient([
            'key' => env('GOOGLE_TRANSLATE_API_KEY'),
        ]);
    }

    /**
     * @param string $input
     * @param string $to
     *
     * @return mixed
     */
    public function translate($input, $to)
    {
        $response = $this->translate->translate($input, ['target' => $to]);
        return $response['text'];
    }

    /**
     * @return array
     */
    public function supportedLanguages()
    {
        return $this->translate->languages();
    }

    /**
     * @return void
     * @throws \Exception
     *
     */
    private function checkForInvalidConfiguration()
    {
        if (empty(env('GOOGLE_TRANSLATE_API_KEY'))) {
            throw new Exception('Google Api Key is required.');
        }
    }
}
