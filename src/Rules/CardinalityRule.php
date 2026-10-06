<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Relaticle\CustomFields\Data\RecordLinkPayload;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CardinalityGuard;

/**
 * Reports what the link writer would refuse, before a form is submitted or an import row
 * is written.
 */
final class CardinalityRule implements ValidationRule
{
    public function __construct(
        private readonly CustomField $customField,
        private readonly string|int|null $recordId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $definition = $this->customField->relationshipDefinition();

        if (! $definition instanceof CustomFieldRelationship) {
            return;
        }

        $payload = RecordLinkPayload::fromValue($value);

        $violations = app(CardinalityGuard::class)->violations(
            $definition,
            $definition->writeDirectionFor($this->customField),
            $this->recordId,
            $payload->ids,
            $payload->confirmed,
        );

        foreach ($violations as $violation) {
            $fail($violation);
        }
    }
}
