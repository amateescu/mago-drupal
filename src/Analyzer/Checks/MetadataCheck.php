<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;

/**
 * One rule that looks at a class through its metadata.
 *
 * Checks read what the codebase knows about the class: attributes, parents,
 * traits, and the members fetched on demand. No source text or syntax tree
 * crosses the worker boundary for them.
 *
 * @internal
 */
interface MetadataCheck
{
    /**
     * Class names the class has to mention (extend, implement, use or type)
     * for the check to apply; an empty list means it always does. The hook
     * reads the mentions off the node's resolved names, so a class mentioning
     * none costs no codebase request.
     *
     * @return list<non-empty-string>
     */
    public function mentionsAny(): array;

    public function check(ClassFacts $class, Reporter $reporter): void;
}
