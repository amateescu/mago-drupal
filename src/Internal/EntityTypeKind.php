<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

/**
 * Which entity type attribute a class carries, since each fills in different
 * default handlers.
 *
 * @internal
 */
enum EntityTypeKind
{
    case Content;
    case Config;
    case Base;
}
