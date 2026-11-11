<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Injection;

use amateescu\MagoDrupal\Analyzer\Support\ClassMentions;
use amateescu\MagoDrupal\Analyzer\Support\MentionedCheck;
use amateescu\MagoDrupal\Analyzer\Support\Reporter;
use amateescu\MagoDrupal\Internal\Declaration\ClassFacts;

/**
 * Runs the serialization check on a class that uses the trait itself.
 *
 * The descendants of the bases that use the trait are the descendant hook's
 * case, so a class that extends one is left to it. A class that only names
 * the trait without using it isn't checked.
 *
 * @internal
 */
final class ComposedSerializationCheck implements MentionedCheck
{
    public function __construct(
        private readonly DependencySerializationCheck $check,
    ) {}

    public function textGate(): ?string
    {
        return null;
    }

    public function mentionedIn(ClassMentions $class): bool
    {
        return $this->check->namedBy($class->names);
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        if (!$class->composes(DependencySerializationCheck::TRAIT) || $class->extendsAny($this->check->bases())) {
            return;
        }

        $this->check->check($class, $reporter);
    }
}
