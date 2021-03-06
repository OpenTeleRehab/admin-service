<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Support\Facades\Auth;

/**
 * @group ExerciseTest
 *
 * @return void
 */
class ExerciseTest extends DuskTestCase
{

    /**
     * @group CreateExerciseTest
     *
     * @return void
     */
    public function testCreateExercise()
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsGlobal($browser)
                ->visit('/service-setup')
                ->waitForText('Services Setup')
                ->clickLink('New Content')
                ->type('title', 'Jogging')
                ->check('get_pain_level')
                ->pause(10000)
                ->press('Add more field')
                ->type('field', 'Instruction')
                ->type('value', 'This is the instruction')
                ->attach('file', 'storage/app/test/exercise.jpeg')
                ->press('Save')
                ->waitForText('Exercise created successfully');
            $this->logout($browser);
        });
    }

    /**
     * @group EditExerciseTest
     *
     * @return void
     */
    public function testEditExercise()
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsGlobal($browser)
                ->visit('/service-setup')
                ->waitForText('Services Setup')
                ->clickLink('New Content')
                ->type('title', 'Jogging')
                ->check('get_pain_level')
                ->pause(10000)
                ->press('Add more field')
                ->type('field', 'Instruction')
                ->type('value', 'This is the instruction')
                ->attach('file', 'storage/app/test/exercise.jpeg')
                ->press('Save')
                ->waitForText('Exercise created successfully')
                ->waitForText('Jogging')
                ->press('svg[viewBox="0 0 24 24"]')
                ->pause(10000)
                ->type('title', 'Jogging testing')
                ->press('Save')
                ->waitForText('Exercise updated successfully')
                ->waitForText('Jogging testing');
            $this->logout($browser);
        });
    }

    /**
     * @group DeleteExerciseTest
     *
     * @return void
     */
    public function testDeleteExercise()
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsGlobal($browser)
                ->visit('/service-setup')
                ->waitForText('Services Setup')
                ->clickLink('New Content')
                ->type('title', 'Jogging')
                ->check('get_pain_level')
                ->pause(10000)
                ->press('Add more field')
                ->type('field', 'Instruction')
                ->type('value', 'This is the instruction')
                ->attach('file', 'storage/app/test/exercise.jpeg')
                ->press('Save')
                ->waitForText('Exercise created successfully')
                ->waitForText('Jogging')
                ->pause(10000)
                ->press('svg[viewBox="0 0 448 512"]')
                ->press('Yes')
                ->waitForText('Exercise deleted successfully')
                ->assertDontSee('Jogging');
            $this->logout($browser);
        });
    }

    /**
     * @group CreateExerciseWithAttachVideoTest
     *
     * @return void
     */
    public function testCreateExerciseWithAttachVideo()
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsGlobal($browser)
                ->pause(10000)
                ->visit('/service-setup')
                ->waitForText('Services Setup')
                ->clickLink('New Content')
                ->type('title', 'Jogging')
                ->check('get_pain_level')
                ->pause(10000)
                ->press('Add more field')
                ->type('field', 'Instruction')
                ->type('value', 'This is the instruction')
                ->attach('file', 'storage/app/test/video.mp4')
                ->press('Save')
                ->pause(10000)
                ->waitForText('Jogging');
            $this->logout($browser);
        });
    }
}
