<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Support;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function strtolower;

/**
 * What a class node says about its class before the codebase is asked: the
 * names resolved inside it and its text.
 *
 * @internal
 */
final class ClassMentions
{
    private ?string $text = null;

    /**
     * @param array<string, true> $names Every resolved name inside the class,
     *   lowercased.
     * @param list<non-empty-string> $candidates The names that aren't
     *   imported, in source order. The class's own name is one of them.
     */
    public function __construct(
        public readonly array $names,
        public readonly array $candidates,
        public readonly SourceFile $file,
        public readonly Span $span,
        public readonly Codebase $codebase,
    ) {}

    /**
     * Whether the class names the class-like, in any case.
     */
    public function names(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->names);
    }

    /**
     * The class's source text, comments included.
     */
    public function text(): string
    {
        return $this->text ??= $this->file->getText($this->span);
    }
}
