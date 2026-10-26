<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\ProfilePart;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * The profile parts other users see, on /profile. Data: ['visibleParts' => list<ProfilePart>].
 */
class ProfileVisibilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('visibleParts', EnumType::class, [
            'class' => ProfilePart::class,
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'label' => 'Visible to other users',
            'choice_label' => static fn (ProfilePart $part): string => $part->label(),
            'help' => 'Unchecked parts stay private. Only signed-in users see your agent page.',
        ]);
    }
}
