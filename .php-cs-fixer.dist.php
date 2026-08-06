<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->notPath('public/build');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_whitespace_before_comma_in_array' => true,
    ])
    ->setFinder($finder);
