<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MethodMetadataProjection;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;
use WeakMap;

use function array_key_exists;
use function strtolower;

/**
 * Places a span inside the class and method declared around it.
 *
 * A method-call hook receives the call node alone. The file's resolved names
 * give every class declared in it, the codebase gives their locations, and
 * the innermost location holding the span is the enclosing declaration. Both
 * lookups are cached per source file snapshot, which the hooks of one request
 * share.
 *
 * The snapshot is the key of a weak map and is handed back in on every call,
 * never kept here. A value that references its own key keeps the entry alive
 * on PHP 8.1 and 8.2, where the cycle collector does not reach into a weak
 * map, and every file a worker ever analyzed would stay in memory.
 *
 * @internal
 */
final class FileMembers
{
    /**
     * @var WeakMap<SourceFile, self>|null
     */
    private static ?WeakMap $files = null;

    /**
     * @var list<ClassLikeMetadata>|null
     */
    private ?array $classes = null;

    /**
     * @var array<string, list<MethodMetadataProjection>>
     */
    private array $methods = [];

    private function __construct() {}

    public static function of(SourceFile $file): self
    {
        self::$files ??= new WeakMap();
        $files = self::$files;
        if (!$files->offsetExists($file)) {
            $files->offsetSet($file, new self());
        }

        return $files->offsetGet($file);
    }

    public function classAt(Codebase $codebase, SourceFile $file, Span $span): ?ClassLikeMetadata
    {
        $found = null;
        foreach ($this->classes($codebase, $file) as $class) {
            $location = $class->location->span;
            if (!$location->contains($span)) {
                continue;
            }

            if ($found === null || $found->location->span->contains($location)) {
                $found = $class;
            }
        }

        return $found;
    }

    public function methodAt(Codebase $codebase, ClassLikeMetadata $class, Span $span): ?MethodMetadataProjection
    {
        $key = strtolower($class->name);
        if (!array_key_exists($key, $this->methods)) {
            // The same field mask as ClassFacts, so the codebase serves both
            // from one cached answer.
            $this->methods[$key] = $codebase->findMethods(
                class: $class->name,
                fields: ClassFacts::METHOD_FIELDS,
                declaredOnly: true,
            );
        }

        foreach ($this->methods[$key] as $method) {
            if ($method->location !== null && $method->location->span->contains($span)) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @return list<ClassLikeMetadata>
     */
    private function classes(Codebase $codebase, SourceFile $file): array
    {
        if ($this->classes !== null) {
            return $this->classes;
        }

        $candidates = DeclaredClass::candidates($file, null);
        $classes = [];
        foreach ($candidates === [] ? [] : $codebase->getMultipleClassLikes($candidates) as $class) {
            if ($class === null || !self::inFile($class, $file)) {
                continue;
            }

            $classes[] = $class;
        }

        return $this->classes = $classes;
    }

    private static function inFile(ClassLikeMetadata $class, SourceFile $file): bool
    {
        return $class->location->file === $file->path;
    }
}
