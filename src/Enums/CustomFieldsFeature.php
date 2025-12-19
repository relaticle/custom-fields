<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

/**
 * Enum defining all available custom fields features
 * Features are grouped by scope: FIELD_, UI_, SYSTEM_
 */
enum CustomFieldsFeature: string
{
    // Field behavior features
    case FIELD_CONDITIONAL_VISIBILITY = 'field_conditional_visibility';
    case FIELD_ENCRYPTION = 'field_encryption';
    case FIELD_OPTION_COLORS = 'field_option_colors';

    // Table/UI integration features
    case UI_TABLE_COLUMNS = 'ui_table_columns';
    case UI_TABLE_FILTERS = 'ui_table_filters';
    case UI_TOGGLEABLE_COLUMNS = 'ui_toggleable_columns';
    case UI_TOGGLEABLE_COLUMNS_HIDDEN_DEFAULT = 'ui_toggleable_columns_hidden_default';

    // System-level features
    case SYSTEM_MANAGEMENT_INTERFACE = 'system_management_interface';
    case SYSTEM_MULTI_TENANCY = 'system_multi_tenancy';
}
