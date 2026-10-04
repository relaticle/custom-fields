<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Models\CustomField;
use RuntimeException;

final class UniqueCustomFieldValueTakenException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly Model $record,
        public readonly CustomField $customField,
        public readonly string $value,
    ) {
        parent::__construct(__('custom-fields::custom-fields.validation.unique_value', ['value' => $value]));
    }
}
