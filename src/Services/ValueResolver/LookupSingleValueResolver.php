<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\ValueResolver;

use Relaticle\CustomFields\Contracts\ValueResolverInterface;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

final readonly class LookupSingleValueResolver implements ValueResolverInterface
{
    public function __construct(private LookupResolver $lookupResolver) {}

    public function resolve(HasCustomFields $record, CustomField $customField, bool $exportable = false): string
    {
        $value = $record->getCustomFieldValue($customField);
        $lookupValue = $this->lookupResolver->resolveLookupValues([$value], $customField)->first();

        return (string) $lookupValue;
    }
}
