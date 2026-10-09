<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;

/**
 * Lookups on the labels of a switch statement.
 *
 * @internal
 */
final class SwitchCases
{
    private function __construct() {}

    /**
     * Returns the `case` and `default` labels that belong to the switch
     * itself, in source order, for both the brace and the colon syntax.
     * Labels of a switch nested in a case body are not included.
     *
     * @return list<Node>
     */
    public static function labels(SourceFile $file, Node $switch): array
    {
        $children = $file->getChildren($switch);
        $body = $file->getChildren($children[count($children) - 1] ?? $switch)[0] ?? null;
        if ($body === null) {
            return [];
        }

        $labels = [];
        foreach ($file->getChildren($body) as $case) {
            if ($case->kind !== NodeKind::SwitchCase) {
                continue;
            }

            $label = $file->getChildren($case)[0] ?? null;
            if ($label === null) {
                continue;
            }

            $labels[] = $label;
        }

        return $labels;
    }
}
