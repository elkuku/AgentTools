<?php

namespace App\Controller\System;

use App\Controller\BaseController;
use App\Enum\UserRole;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/system/logview', name: 'app_system_logview', methods: ['GET'])]
#[IsGranted(UserRole::ADMIN->value)]
class LogviewController extends BaseController
{
    public function __invoke(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ): Response {
        $entries = [];

        try {
            $entries = $this->parseLogEntries($projectDir.'/var/log/deploy.log');
        } catch (IOException $ioException) {
            $this->addFlash('danger', $ioException->getMessage());
        }

        return $this->render('system/logview.html.twig', [
            'project_dir' => $projectDir,
            'logEntries' => \array_reverse($entries),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function parseLogEntries(string $filename): array
    {
        $entries = [];
        $entry = null;
        $dateTime = null;

        foreach ($this->readLines($filename) as $line) {
            if (\str_starts_with($line, '>>>==============')) {
                $entry = $this->openEntry($entry);
                continue;
            }

            if (\str_starts_with($line, '<<<===========')) {
                [$entries, $entry] = $this->closeEntry($entries, $entry, $dateTime);
                continue;
            }

            if ('' === $entry) {
                // The first line contains the dateTime string
                $dateTime = $line;
                $entry = $line."\n";
                continue;
            }

            $entry .= $line."\n";
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    private function readLines(string $filename): array
    {
        $filesystem = new Filesystem();

        if (false === $filesystem->exists($filename)) {
            throw new IOException('Log file not found!');
        }

        return \array_filter(
            \array_map(trim(...), \explode("\n", $filesystem->readFile($filename))),
            static fn (string $l): bool => '' !== $l && '0' !== $l,
        );
    }

    private function openEntry(?string $current): string
    {
        if (null !== $current) {
            throw new \LogicException('Entry finished string not found');
        }

        return '';
    }

    /**
     * @param array<string, string> $entries
     *
     * @return array{array<string, string>, null}
     */
    private function closeEntry(array $entries, ?string $entry, ?string $dateTime): array
    {
        if (null === $entry) {
            throw new \LogicException('Entry not started.');
        }

        if (null === $dateTime) {
            throw new \LogicException('Entry has no dateTime.');
        }

        $entries[$dateTime] = $entry;

        return [$entries, null];
    }
}
