<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Ingress all-time stats pasted as copied from the game.
 */
final class AgentStatImportType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('stats', TextareaType::class, [
            'label' => 'Ingress stats',
            'help' => 'In Ingress: Agent profile → All time → copy the stats, then paste them here.',
            'constraints' => [new NotBlank()],
            // Keep the tabs: the stats are tab-separated
            'trim' => false,
            'attr' => ['rows' => 6, 'class' => 'font-monospace'],
        ]);
    }
}
