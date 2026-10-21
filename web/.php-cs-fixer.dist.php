<?php

declare(strict_types=1);

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->notName('bootstrap.php')
;

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'native_function_invocation' => ['include' => ['@all']],
        'php_unit_method_casing' => ['case' => 'camel_case'],
    ])
    ->setFinder($finder)
;
