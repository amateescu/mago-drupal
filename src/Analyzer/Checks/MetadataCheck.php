<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;

/**
 * One rule that looks at a class through its metadata.
 *
 * Checks read what the codebase knows about the class: attributes, parents,
 * traits, and the members fetched on demand. A check can name a text gate,
 * and then the class's source text is what decides whether it runs.
 *
 * @internal
 */
interface MetadataCheck
{
    /**
     * A pattern the class's source text has to match for the check to apply,
     * or null when it always applies. The descendant hook checks it before
     * any codebase request, so a class that cannot be reported costs none.
     * The class hook gates its checks on the names in the class instead.
     */
    public function textGate(): ?string;

    public function check(ClassFacts $class, Reporter $reporter): void;
}
