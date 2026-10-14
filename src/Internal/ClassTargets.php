<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\ClassLikeTarget;
use Mago\Sdk\Exception\InvalidArgumentException;

/**
 * Filters class names read off disk down to the ones the SDK takes as
 * class-like targets.
 *
 * The names come off the tokens or a regex over user code. One the SDK
 * rejects, such as a namespace segment named `Enum`, must not fail the whole
 * registration.
 *
 * @internal
 */
final class ClassTargets
{
    private function __construct() {}

    /**
     * @param list<string> $names
     * @return list<non-empty-string>
     */
    public static function accepted(array $names): array
    {
        $accepted = [];
        foreach ($names as $name) {
            try {
                ClassLikeTarget::descendantsOf($name);
            } catch (InvalidArgumentException) {
                continue;
            }

            if ($name !== '') {
                $accepted[] = $name;
            }
        }

        return $accepted;
    }
}
