<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TimelineEvent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A custom event on the timeline: on /agent-stats (new) and its edit page.
 */
class TimelineEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'label' => 'timeline.event.date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'timeline.event.end_date',
                'help' => 'timeline.event.end_date_help',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('text', TextareaType::class, [
                'label' => 'timeline.event.text',
                // Blank is null otherwise, the entity's text is a string
                'empty_data' => '',
                'help' => 'timeline.event.text_help',
                'attr' => ['maxlength' => 500, 'rows' => 2],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimelineEvent::class,
        ]);
    }
}
