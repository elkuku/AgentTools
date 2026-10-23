<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\Entity\User;
use App\State\AgentNameProcessor;
use App\State\AgentNameProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * GET / PUT /api/me/agent-name: the signed-in agent's Ingress agent name.
 * Agent stats are only accepted for this agent.
 */
#[ApiResource(
    description: 'Your Ingress agent name. Agent stats are only accepted for this agent (ignoring case); no other user may have it.',
    operations: [
        new Get(
            uriTemplate: '/me/agent-name',
            security: "is_granted('ROLE_AGENT')",
            securityMessage: self::AGENT_ROLE_NEEDED,
            provider: AgentNameProvider::class,
        ),
        new Put(
            uriTemplate: '/me/agent-name',
            collectDenormalizationErrors: true,
            security: "is_granted('ROLE_AGENT')",
            securityMessage: self::AGENT_ROLE_NEEDED,
            read: false,
            processor: AgentNameProcessor::class,
        ),
    ],
)]
final class AgentName
{
    public const string AGENT_ROLE_NEEDED = 'An agent name needs the Agent role. Ask an administrator.';

    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Regex(pattern: User::AGENT_NAME_PATTERN, message: User::AGENT_NAME_INVALID, normalizer: 'trim')]
        public ?string $agentName = null,
    ) {
    }
}
