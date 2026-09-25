<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

use function strtolower;

/**
 * Tags config objects with the name they were loaded for.
 *
 * @internal
 */
final class ConfigFactoryProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return Configs::loaders();
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $name = $invocation->getArgument(0, 'name')?->type?->getLiteralString();
        if ($name === null || $name === '') {
            return null;
        }

        // `\Drupal::config()` and `$factory->get()` hand out immutable configs;
        // `getEditable()` and a form's `config()` hand out editable ones. No
        // schema is needed, so every literal name gets tagged.
        $immutable =
            $invocation->declaringClass !== Configs::FORM_TRAIT && strtolower($invocation->name) !== 'geteditable';

        return Configs::tagged($immutable ? Configs::IMMUTABLE : Configs::EDITABLE, $name);
    }
}
