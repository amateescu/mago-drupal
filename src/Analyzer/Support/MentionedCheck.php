<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Support;

/**
 * A check the class hook runs on every class that mentions what it is about.
 *
 * @internal
 */
interface MentionedCheck extends MetadataCheck
{
    /**
     * Whether the names and the text of a class say the check can apply.
     *
     * The class hook asks this for every class, before any codebase request
     * for the class itself, so the answer has to be cheap. A true for a class
     * the check then doesn't report on only costs the lookup.
     */
    public function mentionedIn(ClassMentions $class): bool;
}
