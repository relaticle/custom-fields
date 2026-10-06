<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\ValueResolver;

use Relaticle\CustomFields\Contracts\ValueResolverInterface;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Throwable;

final readonly class LookupMultiValueResolver implements ValueResolverInterface
{
    public function __construct(private LookupResolver $lookupResolver) {}

    /**
     * @return array<int, mixed>
     *
     * @throws Throwable
     */
    public function resolve(HasCustomFields $record, CustomField $customField, bool $exportable = false): array
    {
        $value = $record->getCustomFieldValue($customField) ?? [];
        $lookupValues = $this->lookupResolver->resolveLookupValues($value, $customField);

        return $lookupValues->isNotEmpty() ? $lookupValues->toArray() : [];
    }
}
