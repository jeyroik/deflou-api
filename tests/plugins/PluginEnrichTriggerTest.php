<?php
namespace tests\plugins;

use deflou\components\applications\activities\Activity;
use deflou\components\plugins\triggers\PluginTriggerEnrich;
use deflou\components\triggers\Trigger;
use extas\components\conditions\ConditionRepository;
use extas\components\conditions\TSnuffConditions;
use extas\components\http\TSnuffHttp;
use extas\components\parsers\Parser;
use extas\components\parsers\ParserSimpleReplace;
use extas\components\repositories\TSnuffRepositoryDynamic;
use extas\components\THasMagicClass;
use extas\interfaces\samples\parameters\ISampleParameter;
use PHPUnit\Framework\TestCase;

/**
 * Class PluginEnrichTriggerTest
 *
 * @package tests\plugins
 * @author jeyroik@gmail.com
 */
class PluginEnrichTriggerTest extends TestCase
{
    use TSnuffRepositoryDynamic;
    use THasMagicClass;
    use TSnuffHttp;
    use TSnuffConditions;

    protected function setUp(): void
    {
        parent::setUp();
        $env = \Dotenv\Dotenv::create(getcwd() . '/tests/');
        $env->load();

        $this->createSnuffDynamicRepositories([
            ['parserRepository', 'name', Parser::class]
        ]);

        $this->registerSnuffRepos([
            'conditionRepository' => ConditionRepository::class
        ]);
    }

    public function tearDown(): void
    {
        $this->deleteSnuffDynamicRepositories();
    }

    public function testEnrich()
    {
        $trigger = new Trigger([
            Trigger::FIELD__NAME => 'test_trigger',
            Trigger::FIELD__ACTION_PARAMETERS => [
                'test_event' => [
                    ISampleParameter::FIELD__NAME => 'test_event',
                    ISampleParameter::FIELD__VALUE => '@event.test'
                ],
                'test_trigger' => [
                    ISampleParameter::FIELD__NAME => 'test_trigger',
                    ISampleParameter::FIELD__VALUE => '@trigger.name'
                ]
            ]
        ]);
        $event = new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__PARAMETERS => [
                'test' => [
                    ISampleParameter::FIELD__NAME => 'test',
                    ISampleParameter::FIELD__VALUE => 'is ok'
                ]
            ]
        ]);

        $this->createSnuffCondition('not_empty');
        $this->getMagicClass('parserRepository')->create(new Parser([
            Parser::FIELD__NAME => 'replace_with_event_parameters',
            Parser::FIELD__CLASS => ParserSimpleReplace::class,
            Parser::FIELD__VALUE => '',
            Parser::FIELD__CONDITION => '!@',
            Parser::FIELD__PARAMETERS => [
                ParserSimpleReplace::FIELD__PARAM_NAME => [
                    ISampleParameter::FIELD__NAME => ParserSimpleReplace::FIELD__PARAM_NAME,
                    ISampleParameter::FIELD__VALUE => 'event'
                ]
            ]
        ]));
        $this->getMagicClass('parserRepository')->create(new Parser([
            Parser::FIELD__NAME => 'replace_with_trigger_parameters',
            Parser::FIELD__CLASS => ParserSimpleReplace::class,
            Parser::FIELD__VALUE => '',
            Parser::FIELD__CONDITION => '!@',
            Parser::FIELD__PARAMETERS => [
                ParserSimpleReplace::FIELD__PARAM_NAME => [
                    ISampleParameter::FIELD__NAME => ParserSimpleReplace::FIELD__PARAM_NAME,
                    ISampleParameter::FIELD__VALUE => 'trigger'
                ]
            ]
        ]));

        $plugin = new PluginTriggerEnrich([
            PluginTriggerEnrich::FIELD__ACTIVITY => $event,
            PluginTriggerEnrich::FIELD__PSR_REQUEST => $this->getPsrRequest('.applicable.trigger'),
            PluginTriggerEnrich::FIELD__PSR_RESPONSE => $this->getPsrResponse()
        ]);
        $plugin($trigger);

        $testEvent = $trigger->getActionParameter('test_event');
        $this->assertEquals('is ok', $testEvent->getValue());

        $testTrigger = $trigger->getActionParameter('test_trigger');
        $this->assertEquals('test_trigger', $testTrigger->getValue());
    }
}
