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
        ];

    public function testAllRoutesAreProtected(): void
    {
        $this->runTests(static::createClient());
    }
}
