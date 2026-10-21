<?php

namespace App\Controller\System;

use App\Controller\BaseController;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/system/about', name: 'app_system_about', methods: ['GET'])]
#[IsGranted(UserRole::ADMIN->value)]
class AboutController extends BaseController
{
    public function __invoke(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        KernelInterface $kernel
    ): Response {
        $output = new BufferedOutput();

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->run(new ArrayInput(['command' => 'about']), $output);

        return $this->render('system/about.html.twig', [
            'project_dir' => $projectDir,
            'systemInfo' => $output->fetch(),
        ]);
    }
}
