<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

// How the create and edit field form opens. Only the container changes; the schema,
// the validation and the save path are the same either way.
enum FieldFormPresentation: string
{
    case SlideOver = 'slide_over';

    case Modal = 'modal';

    public function isSlideOver(): bool
    {
        return $this === self::SlideOver;
    }
}
