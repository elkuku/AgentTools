<?php

declare(strict_types=1);

namespace App\Notifier;

use App\Entity\AgentStat;
use App\Entity\User;
use App\Repository\AgentStatRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Posts the site's activity to Slack and Telegram (ChatFeed::Activity): new users, stats uploads
 * and CSV imports. The messages are sent after the response (kernel.terminate), so a chat never
 * slows a request down; a failure is only logged as a warning, below the level ChatLogHandler posts.
 */
final class ChatActivity implements ResetInterface
{
    /** @var list<ChatText> */
    private array $messages = [];

    public function __construct(
        private readonly ChatSender $chatSender,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AgentStatRepository $agentStatRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function userRegistered(User $user): void
    {
        $this->messages[] = new ChatText('👋 New user ')->link($user->getIdentifier(), $this->userUrl($user));
    }

    /**
     * Stats uploaded through the API (the app); imports are reported with statsImported().
     */
    public function statsUploaded(AgentStat $agentStat): void
    {
        $previous = $this->agentStatRepository->findPrevious($agentStat);

        $this->messages[] = $this->agent(new ChatText('📊 '), $agentStat->owner)->text(\sprintf(
            ' uploaded stats: %sAP %s%s',
            null === $agentStat->level ? '' : 'level '.$agentStat->level.', ',
            \number_format($agentStat->ap),
            $previous instanceof AgentStat ? \sprintf(' (+%s)', \number_format($agentStat->ap - $previous->ap)) : ' (first upload)',
        ));
    }

    public function statsImported(User $owner, int $imported): void
    {
        if ($imported < 1) {
            return;
        }

        $this->messages[] = $this->agent(new ChatText('📥 '), $owner)
            ->text(\sprintf(' imported %d stats %s', $imported, 1 === $imported ? 'upload' : 'uploads'));
    }

    #[AsEventListener(KernelEvents::TERMINATE)]
    #[AsEventListener(ConsoleEvents::TERMINATE)]
    public function send(): void
    {
        $messages = $this->messages;
        $this->messages = [];

        foreach ($messages as $message) {
            foreach ($this->chatSender->send(ChatFeed::Activity, $message) as $transport => $exception) {
                $this->logger->warning('{transport}: {message} was not sent: {error}', ['transport' => $transport, 'message' => $message->plain(), 'error' => $exception->getMessage()]);
            }
        }
    }

    public function reset(): void
    {
        $this->messages = [];
    }

    /** The agent name, linked to the admins' page of the user. */
    private function agent(ChatText $text, User $user): ChatText
    {
        return $text->link($user->getAgentName() ?? $user->getIdentifier(), $this->userUrl($user));
    }

    private function userUrl(User $user): string
    {
        return $this->urlGenerator->generate('app_user_show', ['id' => $user->id], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
