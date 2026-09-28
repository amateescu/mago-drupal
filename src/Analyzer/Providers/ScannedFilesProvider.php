<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ObjectProperty;
use Mago\Sdk\Analyzer\Type\ObjectShapeType;

use function strtolower;

/**
 * Types the files `scanDirectory()` and the test trait's `getTestFiles()`
 * find as the objects they are.
 *
 * Core documents them as `array` and `object[]`, and describes each file as
 * an object with `uri`, `filename` and `name`. `scanDirectory()` keys them by
 * the chosen property, which PHP may turn into an integer key;
 * `getTestFiles()` sorts them into a list.
 *
 * @internal
 */
final class ScannedFilesProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Drupal\Core\File\FileSystemInterface', 'scanDirectory'),
            MethodTarget::exact('Drupal\Tests\TestFileCreationTrait', 'getTestFiles'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $file = Type::fromAtomic(new ObjectShapeType([
            new ObjectProperty('uri', false, Type::string()),
            new ObjectProperty('filename', false, Type::string()),
            new ObjectProperty('name', false, Type::string()),
        ], sealed: false));

        return strtolower($context->invocation->name) === 'gettestfiles'
            ? Type::list($file)
            : Type::array(Type::union(Type::int(), Type::string()), $file);
    }
}
