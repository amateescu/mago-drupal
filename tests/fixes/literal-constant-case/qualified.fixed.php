<?php

namespace Drupal\example;

const EXAMPLE_ENABLED = TRUE;

class LiteralConstants
{
    public const DISABLED = FALSE;

    public function check(?bool $flag = NULL): array
    {
        if ($flag === FALSE) {
            return [TRUE, NULL];
        }

        return [\TRUE, \FALSE, \NULL, namespace\true];
    }
}
