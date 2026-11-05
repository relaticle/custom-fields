<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

/**
 * What a field type declares about itself in FieldSchema::capabilities().
 */
enum FieldCapability: string
{
    case Searchable = 'searchable';
    case Sortable = 'sortable';
    case Filterable = 'filterable';
    case Encryptable = 'encryptable';
    case WithoutUserOptions = 'without_user_options';
    case RequiresRelationship = 'requires_relationship';
    case SupportsPairing = 'supports_pairing';
    case CarriesOptionCategories = 'carries_option_categories';
    case AcceptsArbitraryValues = 'accepts_arbitrary_values';
    case SupportsMultiValue = 'supports_multi_value';
    case SupportsUniqueConstraint = 'supports_unique_constraint';
    case SystemOnly = 'system_only';
}
