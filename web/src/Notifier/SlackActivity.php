<?php

declare(strict_types=1);

namespace App\Notifier;

use App\Entity\AgentStat;
use App\Entity\User;
use App\Repository\AgentStatRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerException;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Posts the site's activity to Slack (SLACK_CHANNEL): new users, stats uploads and CSV
 * imports. The messages are sent after the response (kernel.terminate), so Slack never slows a
 * request down; a failure is only logged as a warning, below the level SlackLogHandler posts.
 */
final class SlackActivity implements ResetInterface
{
    /** @var list<ChatMessage> */
    private array $messages = [];

    public function __construct(
        private readonly ChatterInterface $chatter,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AgentStatRepository $agentStatRepository,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'SLACK_CHANNEL')]
        private readonly string $channel,
    ) {
    }

    public function userRegistered(User $user): void
    {
        $this->add(\sprintf(
            ':wave: New user <%s|%s>',
            $this->urlGenerator->generate('app_user_show', ['id' => $user->id], UrlGeneratorInterface::ABSOLUTE_URL),
            self::escape($user->getIdentifier()),
        ));
    }

    /**
     * Stats uploaded through the API (the app); imports are reported with statsImported().
     */
    public function statsUploaded(AgentStat $agentStat): void
    {
        $previous = $this->agentStatRepository->findPrevious($agentStat);

        $this->add(\sprintf(
            ':bar_chart: %s uploaded stats: %sAP %s%s',
            $this->agent($agentStat->owner),
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

        $this->add(\sprintf(':inbox_tray: %s imported %d stats %s', $this->agent($owner), $imported, 1 === $imported ? 'upload' : 'uploads'));
    }

    /**
     * Sends a message right away, to SLACK_CHANNEL or [$channel] (app:slack:test).
     *
     * @throws TransportExceptionInterface|MessengerException
     */
    public function sendNow(string $text, ?string $channel = null): void
    {
        $this->chatter->send($this->message($text, $channel ?? $this->channel));
    }

    #[AsEventListener(KernelEvents::TERMINATE)]
    #[AsEventListener(ConsoleEvents::TERMINATE)]
    public function send(): void
    {
        $messages = $this->messages;
        $this->messages = [];

        foreach ($messages as $message) {
            try {
                $this->chatter->send($message);
            } catch (TransportExceptionInterface|MessengerException $exception) {
                $this->logger->warning('Slack: {message} was not sent: {error}', ['message' => $message->getSubject(), 'error' => $exception->getMessage()]);
            }
        }
    }

    public function reset(): void
    {
        $this->messages = [];
    }

    private function add(string $text): void
    {
        $this->messages[] = $this->message($text, $this->channel);
    }

    private function message(string $text, string $channel): ChatMessage
    {
        return new ChatMessage($text, new SlackOptions()->recipient($channel));
    }

    /** The agent name, linked to the admins' page of the user. */
    private function agent(User $user): string
    {
        return \sprintf(
            '<%s|%s>',
            $this->urlGenerator->generate('app_user_show', ['id' => $user->id], UrlGeneratorInterface::ABSOLUTE_URL),
            self::escape($user->getAgentName() ?? $user->getIdentifier()),
        );
    }

    /** Slack's control characters in a message's text. */
    public static function escape(string $text): string
    {
        return \strtr($text, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }
}
