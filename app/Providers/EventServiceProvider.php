<?php

namespace App\Providers;

use App\Events\ApplyCategoryAutoTranslationEvent;
use App\Events\ApplyExerciseAutoTranslationEvent;
use App\Events\ApplyGuidanceAutoTranslationEvent;
use App\Events\ApplyMaterialAutoTranslationEvent;
use App\Events\ApplyNewLanguageTranslationEvent;
use App\Events\ApplyPrivacyPolicyAutoTranslationEvent;
use App\Events\ApplyQuestionnaireAutoTranslationEvent;
use App\Events\ApplyStaticPageAutoTranslationEvent;
use App\Events\ApplyTermAndConditionAutoTranslationEvent;
use App\Events\ApplyTranslationAutoTranslationEvent;
use App\Listeners\ApplyCategoryAutoTranslationListener;
use App\Listeners\ApplyExerciseAutoTranslationListener;
use App\Listeners\ApplyGuidanceAutoTranslationListener;
use App\Listeners\ApplyMaterialAutoTranslationListener;
use App\Listeners\ApplyNewLanguageTranslationListener;
use App\Listeners\ApplyPrivacyPolicyAutoTranslationListener;
use App\Listeners\ApplyQuestionnaireAutoTranslationListener;
use App\Listeners\ApplyStaticPageAutoTranslationListener;
use App\Listeners\ApplyTermAndConditionAutoTranslationListener;
use App\Listeners\ApplyTranslationAutoTranslationListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        ApplyExerciseAutoTranslationEvent::class => [
            ApplyExerciseAutoTranslationListener::class,
        ],
        ApplyMaterialAutoTranslationEvent::class => [
            ApplyMaterialAutoTranslationListener::class,
        ],
        ApplyQuestionnaireAutoTranslationEvent::class => [
            ApplyQuestionnaireAutoTranslationListener::class,
        ],
        ApplyNewLanguageTranslationEvent::class => [
            ApplyNewLanguageTranslationListener::class,
        ],
        ApplyCategoryAutoTranslationEvent::class => [
            ApplyCategoryAutoTranslationListener::class,
        ],
        ApplyGuidanceAutoTranslationEvent::class => [
            ApplyGuidanceAutoTranslationListener::class,
        ],
        ApplyPrivacyPolicyAutoTranslationEvent::class => [
            ApplyPrivacyPolicyAutoTranslationListener::class,
        ],
        ApplyStaticPageAutoTranslationEvent::class => [
            ApplyStaticPageAutoTranslationListener::class,
        ],
        ApplyTermAndConditionAutoTranslationEvent::class => [
            ApplyTermAndConditionAutoTranslationListener::class,
        ],
        ApplyTranslationAutoTranslationEvent::class => [
            ApplyTranslationAutoTranslationListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
    }
}
