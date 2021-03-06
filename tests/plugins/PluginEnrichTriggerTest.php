<?php
namespace tests\plugins;

use deflou\components\applications\activities\Activity;
use deflou\components\plugins\triggers\PluginEnrichTrigger;
use deflou\components\triggers\Trigger;
use extas\components\conditions\Condition;
use extas\components\conditions\ConditionNotEmpty;
use extas\components\conditions\ConditionRepository;
use extas\components\parsers\Parser;
use extas\components\parsers\ParserRepository;
use extas\components\parsers\ParserSimpleReplace;
use extas\components\SystemContainer;
use extas\interfaces\conditions\IConditionRepository;
use extas\interfaces\parsers\IParserRepository;
use extas\interfaces\repositories\IRepository;
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
    protected ?IRepository $condRepo = null;
    protected ?IRepository $parserRepo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $env = \Dotenv\Dotenv::create(getcwd() . '/tests/');
        $env->load();

        $this->condRepo = new ConditionRepository();
        $this->parserRepo = new ParserRepository();

        SystemContainer::addItem(
            IConditionRepository::class,
            ConditionRepository::class
        );

        SystemContainer::addItem(
            IParserRepository::class,
            ParserRepository::class
        );
    }

    public function tearDown(): void
    {
        $this->condRepo->delete([Condition::FIELD__NAME => 'not_empty']);
        $this->parserRepo->delete([Parser::FIELD__CLASS => ParserSimpleReplace::class]);
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
        $action = new Activity([]);
        $event = new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__PARAMETERS => [
                'test' => [
                    ISampleParameter::FIELD__NAME => 'test',
                    ISampleParameter::FIELD__VALUE => 'is ok'
                ]
            ]
        ]);

        $this->condRepo->create(new Condition([
            Condition::FIELD__NAME => 'not_empty',
            Condition::FIELD__CLASS => ConditionNotEmpty::class,
            Condition::FIELD__ALIASES => ['not_empty', '!@', '!null', '!empty']
        ]));

        $this->parserRepo->create(new Parser([
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
        $this->parserRepo->create(new Parser([
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

        $plugin = new PluginEnrichTrigger();
        $plugin($action, $event, $trigger);

        $testEvent = $trigger->getActionParameter('test_event');
        $this->assertEquals('is ok', $testEvent->getValue());

        $testTrigger = $trigger->getActionParameter('test_trigger');
        $this->assertEquals('test_trigger', $testTrigger->getValue());
    }
}
