<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * A CSV file of agent stats (App\Import\AgentStatCsvImporter), on /agent-stats/import.
 */
class StatsImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'stats_import.file',
            'help' => 'stats_import.file_help',
            'attr' => ['accept' => '.csv,text/csv'],
            'constraints' => [
                new NotNull(message: 'stats_import.no_file'),
                new File(maxSize: '5M', extensions: ['csv', 'txt']),
            ],
        ]);
    }
}
