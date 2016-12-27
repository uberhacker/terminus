<?php

namespace Pantheon\Terminus\UnitTests\Commands\Workflow;

use Pantheon\Terminus\Collections\Workflows;
use Pantheon\Terminus\Commands\Workflow\WatchCommand;
use Pantheon\Terminus\Config\TerminusConfig;
use Pantheon\Terminus\Models\Workflow;
use Pantheon\Terminus\Models\WorkflowOperation;

/**
 * Class WatchCommandTest
 * Testing class for Pantheon\Terminus\Commands\Workflow\WatchCommand
 * @package Pantheon\Terminus\UnitTests\Commands\Workflow
 */
class WatchCommandTest extends WorkflowCommandTest
{
    /**
     * @var TerminusConfig
     */
    protected $config;
    /**
     * @var WorkflowOperation
     */
    protected $operation;

    const LAST_CREATED_AT = 0;
    const LAST_FINISHED_AT = 1000;

    /**
     * Setup the test fixture.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->config = $this->getMockBuilder(TerminusConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->workflow = $this->getMockBuilder(Workflow::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->operation = $this->getMockBuilder(WorkflowOperation::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->config->expects($this->at(0))
            ->method('get')
            ->with($this->equalTo('date_format'))
            ->willReturn('Y-m-d H:i:s');
        $this->logger->expects($this->at(0))
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->equalTo('Watching workflows...')
            );
        $this->site->expects($this->any())
            ->method('getWorkflows')
            ->with()
            ->willReturn($this->workflows);
        $this->workflows->expects($this->any())
            ->method('fetchWithOperations')
            ->with()
            ->willReturn($this->workflows);
        $this->workflows->expects($this->any())
            ->method('lastCreatedAt')
            ->with()
            ->willReturn(self::LAST_CREATED_AT);
        $this->workflows->expects($this->any())
            ->method('lastFinishedAt')
            ->with()
            ->willReturn(self::LAST_FINISHED_AT);
        $this->workflows->expects($this->any())
            ->method('all')
            ->with()
            ->willReturn([$this->workflow,]);

        $this->command = new WatchCommand($this->getConfig());
        $this->command->setLogger($this->logger);
        $this->command->setSites($this->sites);
        $this->command->setConfig($this->config);
    }

    /**
     * Tests the workflow:list command
     */
    public function testWatch()
    {
        $site_name = 'site name';
        $description = 'description';
        $this->environment->id = 'dev';
        $started_at = '-14160840';
        $this->workflow->id = 'workflow id';
        $log_output = 'log output';

        $this->workflow->expects($this->at(0))
            ->method('get')
            ->with($this->equalTo('created_at'))
            ->willReturn(self::LAST_CREATED_AT + 1);
        $this->workflow->expects($this->at(1))
            ->method('get')
            ->with($this->equalTo('description'))
            ->willReturn($description);
        $this->workflow->expects($this->at(2))
            ->method('get')
            ->with($this->equalTo('environment'))
            ->willReturn($this->environment->id);
        $this->workflow->expects($this->at(3))
            ->method('get')
            ->with($this->equalTo('started_at'))
            ->willReturn($started_at);
        $this->logger->expects($this->at(1))
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->equalTo('Started {id} {description} ({env}) at {time}'),
                $this->equalTo([
                    'id' => $this->workflow->id,
                    'description' => $description,
                    'env' => $this->environment->id,
                    'time' => '1969-07-21 02:26:00',
                ])
            );

        $this->workflow->expects($this->at(4))
            ->method('get')
            ->with($this->equalTo('finished_at'))
            ->willReturn($started_at);
        /**
        $this->workflow->expects($this->at(5))
            ->method('get')
            ->with($this->equalTo('description'))
            ->willReturn($description);
        $this->workflow->expects($this->at(6))
            ->method('get')
            ->with($this->equalTo('environment'))
            ->willReturn($this->environment->id);
        $this->workflow->expects($this->at(7))
            ->method('get')
            ->with($this->equalTo('finished_at'))
            ->willReturn(self::LAST_FINISHED_AT);
        $this->logger->expects($this->at(2))
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->equalTo('Finished workflow {id} {description} ({env}) at {time}'),
                $this->equalTo([
                    'id' => $this->workflow->id,
                    'description' => $description,
                    'env' => $this->environment->id,
                    'time' => '1969-07-21 02:26:00',
                ])
            );

        $this->workflow->expects($this->at(8))
            ->method('get')
            ->with($this->equalTo('has_operation_log_output'))
            ->willReturn(true);
        $this->workflow->expects($this->once())
            ->method('fetchWithLogs')
            ->with()
            ->willReturn($this->workflows);
        $this->workflow->expects($this->once())
            ->method('operations')
            ->with()
            ->willReturn([$this->operation,]);
        $this->operation->expects($this->once())
            ->method('has')
            ->with($this->equalTo('log_output'))
            ->willReturn(true);
        $this->operation->expects($this->once())
            ->method('description')
            ->with()
            ->willReturn($description);
        $this->operation->expects($this->at(0))
            ->method('get')
            ->with($this->equalTo('environment'))
            ->willReturn($this->environment->id);
        $this->operation->expects($this->at(1))
            ->method('get')
            ->with($this->equalTo('log_output'))
            ->willReturn($log_output);
        $this->logger->expects($this->at(3))
            ->method('log')
            ->with(
                $this->equalTo('notice'),
                $this->equalTo("\n------ $description ({$this->environment->id}) ------\n$log_output")
            );
*/
        $out = $this->command->watch($site_name, ['checks' => 1,]);
        $this->assertNull($out);
    }
}
