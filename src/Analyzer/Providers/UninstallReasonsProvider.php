<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Types the reasons `validateUninstall()` collects as strings or markup.
 *
 * Core documents `string[][]`, keyed by module, but the validators return
 * translatable markup, which keeps their placeholders safe to render. The
 * `module-uninstall-validator.stub` says the same for each validator.
 *
 * @internal
 */
final class UninstallReasonsProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Core\Extension\ModuleInstallerInterface', 'validateUninstall')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $reason = Type::union(Type::string(), Type::namedObject('Drupal\Component\Render\MarkupInterface'));

        return Type::array(Type::string(), Type::list($reason));
    }
}
