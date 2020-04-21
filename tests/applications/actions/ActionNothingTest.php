<?php
namespace tests;

use deflou\components\applications\activities\actions\ActionNothing;
use deflou\components\applications\activities\Activity;
use deflou\components\applications\anchors\Anchor;
use deflou\components\applications\Application;
use deflou\components\applications\ApplicationRepository;
use deflou\components\triggers\Trigger;
use deflou\interfaces\applications\IApplicationRepository;
use Dotenv\Dotenv;
use extas\components\SystemContainer;
use extas\interfaces\repositories\IRepository;
use PHPUnit\Framework\TestCase;

/**
 * Class ActionNothingTest
 *
 * @package tests
 * @author jeyroik@gmail.com
 */
class ActionNothingTest extends TestCase
{
    protected ?IRepository $appRepo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $env = Dotenv::create(getcwd() . '/tests/');
        $env->load();

        $this->appRepo = new ApplicationRepository();

        SystemContainer::addItem(
            IApplicationRepository::class,
            ApplicationRepository::class
        );
    }

    public function tearDown(): void
    {
        $this->appRepo->delete([Application::FIELD__SAMPLE_NAME => 'test_app']);
    }

    public function testTriggering()
    {
        $action = new ActionNothing();
        $this->appRepo->create(new Application([
            Application::FIELD__NAME => 'test',
            Application::FIELD__SAMPLE_NAME => 'test_app'
        ]));

        $response = $action(
            new Activity([
                Activity::FIELD__NAME => 'test_action',
                Activity::FIELD__SAMPLE_NAME => 'test_action',
                Activity::FIELD__APPLICATION_NAME => 'test',
                Activity::FIELD__TYPE => Activity::TYPE__ACTION
            ]),
            new Activity([
                Activity::FIELD__NAME => 'test_event',
                Activity::FIELD__SAMPLE_NAME => 'test_event',
                Activity::FIELD__APPLICATION_NAME => 'test',
                Activity::FIELD__TYPE => Activity::TYPE__EVENT
            ]),
            new Trigger([
                Trigger::FIELD__NAME => 'test'
            ]),
            new Anchor([
                Anchor::FIELD__PLAYER_NAME => 'test'
            ])
        );
        $this->assertEquals('Nothing is done', $response->getResponseBody());
        $this->assertTrue($response->isSuccess());
    }
}
