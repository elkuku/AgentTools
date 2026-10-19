<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\UploadInput;
use App\Form\DataTransformer\JsonToArrayTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('data', TextareaType::class, [
                'label' => 'Data (JSON object)',
                'required' => true,
                'invalid_message' => 'Please enter valid JSON.',
                'attr' => [
                    'rows' => 12,
                    'class' => 'font-monospace',
                    'placeholder' => '{"key": "value"}',
                ],
            ]);

        $builder->get('data')->addModelTransformer(new JsonToArrayTransformer());
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UploadInput::class,
            // UploadInput is read-only, so build it from the submitted values
            'empty_data' => static function (FormInterface $form): UploadInput {
                $data = $form->get('data')->getData();

                return new UploadInput(
                    (string) $form->get('title')->getData(),
                    \is_array($data) ? $data : [],
                );
            },
        ]);
    }
}
