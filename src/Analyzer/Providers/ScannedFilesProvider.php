<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
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
 * `getTestFiles()` sorts them into a list. `scanDirectory()` builds each one
 * as a `stdClass`, and the type says so too, since Mago takes an object shape
 * alone for something that is never a `stdClass`.
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
        $shape = new ObjectShapeType([
            new ObjectProperty('uri', false, Type::string()),
            new ObjectProperty('filename', false, Type::string()),
            new ObjectProperty('name', false, Type::string()),
        ], sealed: false);
        $file = Type::fromAtomic(new NamedObjectType('stdClass', null, null, false, false, [$shape], false));

        return strtolower($context->invocation->name) === 'gettestfiles'
            ? Type::list($file)
            : Type::array(Type::union(Type::int(), Type::string()), $file);
    }
}
