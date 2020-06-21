<?php

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax'=>'short'],
        'cast_spaces' => true,
        'compact_nullable_typehint' => true,
        'declare_strict_types' => true,
        'include' => true,
        'list_syntax' => ['syntax'=>'short'],
        'lowercase_cast' => true,
        'magic_constant_casing' => true,
        'lowercase_static_reference' => true,
        'native_function_casing' => true,
        'native_function_invocation' => true,
        'native_constant_invocation' => true,
        'native_function_type_declaration_casing' => true,
        'no_alias_functions' => true,
        'trim_array_spaces' => true,
        'unary_operator_spaces' => true,
        'trailing_comma_in_multiline_array' => true,
        'ternary_to_null_coalescing' => true,
        'static_lambda' => true,
        'standardize_increment' => true,
        'single_quote' => true,
    ])
    ->setFinder(PhpCsFixer\Finder::create()
        ->exclude('vendor')
        ->in(__DIR__)
    )
    ;
