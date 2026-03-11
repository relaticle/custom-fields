<?php

use Illuminate\Contracts\Support\Arrayable;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Normalizers\ArrayableNormalizer;
use Spatie\LaravelData\Normalizers\ArrayNormalizer;
use Spatie\LaravelData\Normalizers\FormRequestNormalizer;
use Spatie\LaravelData\Normalizers\JsonNormalizer;
use Spatie\LaravelData\Normalizers\ModelNormalizer;
use Spatie\LaravelData\Normalizers\ObjectNormalizer;
use Spatie\LaravelData\RuleInferrers\AttributesRuleInferrer;
use Spatie\LaravelData\RuleInferrers\BuiltInTypesRuleInferrer;
use Spatie\LaravelData\RuleInferrers\NullableRuleInferrer;
use Spatie\LaravelData\RuleInferrers\RequiredRuleInferrer;
use Spatie\LaravelData\RuleInferrers\SometimesRuleInferrer;
use Spatie\LaravelData\Support\Creation\ValidationStrategy;
use Spatie\LaravelData\Transformers\ArrayableTransformer;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Transformers\EnumTransformer;

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Strategy
    |--------------------------------------------------------------------------
    */
    'validation_strategy' => ValidationStrategy::Always,

    /*
    |--------------------------------------------------------------------------
    | Transformation Depth
    |--------------------------------------------------------------------------
    */
    'max_transformation_depth' => null,
    'throw_when_max_depth_reached' => false,

    /*
    |--------------------------------------------------------------------------
    | Data Objects
    |--------------------------------------------------------------------------
    */
    'features' => [
        'cast_and_transform_iterables' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Normalizers
    |--------------------------------------------------------------------------
    */
    'normalizers' => [
        ModelNormalizer::class,
        FormRequestNormalizer::class,
        ArrayableNormalizer::class,
        ObjectNormalizer::class,
        ArrayNormalizer::class,
        JsonNormalizer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Transformers
    |--------------------------------------------------------------------------
    */
    'transformers' => [
        DateTimeInterface::class => DateTimeInterfaceTransformer::class,
        Arrayable::class => ArrayableTransformer::class,
        BackedEnum::class => EnumTransformer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Casts
    |--------------------------------------------------------------------------
    */
    'casts' => [
        DateTimeInterface::class => DateTimeInterfaceCast::class,
        BackedEnum::class => EnumCast::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rule Inferrers
    |--------------------------------------------------------------------------
    */
    'rule_inferrers' => [
        SometimesRuleInferrer::class,
        NullableRuleInferrer::class,
        RequiredRuleInferrer::class,
        BuiltInTypesRuleInferrer::class,
        AttributesRuleInferrer::class,
    ],
];
