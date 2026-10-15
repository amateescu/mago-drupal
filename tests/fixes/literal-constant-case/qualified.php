<?php

namespace Drupal\example;

const EXAMPLE_ENABLED = \true;

class LiteralConstants
{
    public const DISABLED = \False;

    public function check(?bool $flag = \null): array
    {
        if ($flag === \false) {
            return [\true, \Null];
        }

        return [\TRUE, \FALSE, \NULL, namespace\true];
    }
}
