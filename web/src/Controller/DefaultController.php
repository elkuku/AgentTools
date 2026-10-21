<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'app_default', methods: ['GET'])]
class DefaultController extends BaseController
{
    public function __invoke(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ): Response {
        return $this->render('default/index.html.twig', [
            'controller_name' => 'DefaultController',
            'php_version' => PHP_VERSION,
            'symfony_version' => Kernel::VERSION,
            'project_dir' => $projectDir,
        ]);
    }
}
