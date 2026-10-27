<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Elkuku\SymfonyUtils\Test\ControllerBaseTest;

class ControllerAccessTest extends ControllerBaseTest
{
    #[\Override]
    protected string $controllerRoot = __DIR__.'/../../src/Controller';

    /**
     * @var array<int, string>
     */
    #[\Override]
    protected array $ignoredFiles
        = [
            '.gitignore',
            'BaseController.php',
        ];

    /**
     * @var array<string, array<string, array<string, int|string>>>
     */
    #[\Override]
    protected array $exceptions
        = [
            'app_default' => [
                'statusCodes' => ['GET' => 200],
            ],
            'app_login' => [
                'statusCodes' => ['GET' => 200],
            ],
            'app_agent_show' => [
                'params' => ['{agentName}' => 'TestAgent'],
            ],
            'app_agent_banners' => [
                'params' => ['{agentName}' => 'TestAgent'],
            ],
            // No event in the test database: the event is looked up before the login
            'app_timeline_event_edit' => [
                'statusCodes' => ['GET' => 404, 'POST' => 404],
            ],
            'app_timeline_event_delete' => [
                'statusCodes' => ['POST' => 404],
            ],
            'app_banners_done_at' => [
                'statusCodes' => ['POST' => 404],
            ],
            'app_locale' => [
                'params' => ['{locale}' => 'de'],
                'statusCodes' => ['GET' => 302],
            ],
        ];

    public function testAllRoutesAreProtected(): void
    {
        $this->runTests(static::createClient());
    }
}
