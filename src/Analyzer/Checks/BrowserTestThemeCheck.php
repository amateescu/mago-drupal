<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\Types;
use Closure;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\PropertyMetadata;

use function array_key_exists;
use function in_array;
use function str_ends_with;

/**
 * Functional tests have to declare the theme they run with; runs on
 * BrowserTestBase descendants.
 *
 * Ports phpstan-drupal's BrowserTestBaseDefaultThemeRule with Drupal's own
 * rule for which profiles need it: those that ship no `system.theme` config.
 * `$profile` and `$defaultTheme` are read from the nearest declaration up the
 * class chain, so a base class can set them for the tests below it.
 *
 * @internal
 */
final class BrowserTestThemeCheck implements MetadataCheck
{
    public const CODE = 'browser-test-default-theme';

    public const ANCESTORS = ['Drupal\Tests\BrowserTestBase'];

    /**
     * Update path tests install the site from a database dump, so they run
     * without choosing a theme.
     */
    private const EXEMPT = ['Drupal\FunctionalTests\Update\UpdatePathTestBase'];

    /**
     * How far up the class chain nearest() looks.
     */
    private const MAX_DEPTH = 16;

    /**
     * Core's profiles that install no theme, for a profile the root does not
     * have on disk.
     */
    private const THEMELESS_PROFILES = [
        'testing',
        'nightwatch_testing',
        'testing_config_overrides',
        'testing_missing_dependencies',
        'testing_multilingual',
        'testing_multilingual_with_english',
        'testing_requirements',
    ];

    /**
     * @param Closure(): array<string, bool> $profileThemes Each profile under
     *   the root, with whether it ships `system.theme` config.
     */
    public function __construct(
        private readonly Closure $profileThemes,
    ) {}

    /**
     * A class that sets a non-empty `$defaultTheme` itself cannot be
     * reported, and most core tests do.
     */
    public function textGate(): ?string
    {
        return '/\A(?!.*\$defaultTheme\s*+=\s*+([\'"])(?!\1))/s';
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        $name = $class->name();
        if (
            $class->class->flags->contains(MetadataFlags::ABSTRACT)
            || !str_ends_with($name, 'Test')
            || $class->extendsAny(self::EXEMPT)
        ) {
            return;
        }

        // A profile with a theme of its own needs no explicit default theme,
        // and a NULL or FALSE one installs from existing configuration, whose
        // theme the test then takes.
        $profile = self::nearest($class, '$profile')?->defaultType?->type;
        $profileName = $profile?->getLiteralString();
        if (
            Types::includesNull($profile)
            || $profile?->getLiteralBool() === false
            || $profileName !== null && !$this->themeless($profileName)
        ) {
            return;
        }

        $theme = self::nearest($class, '$defaultTheme')?->defaultType?->type->getLiteralString();
        if ($theme !== null && $theme !== '') {
            return;
        }

        $reporter->error(self::CODE, Reporter::issue(
            "{$name} sets no \$defaultTheme.",
            $class->class->nameLocation ?? $class->class->location,
            'Functional tests have to declare the theme they run with: protected $defaultTheme = \'stark\';',
            'https://www.drupal.org/node/3083055',
        ));
    }

    /**
     * Whether the profile installs no theme: it ships no `system.theme`
     * config, which is what Drupal checks, or, for a profile not on disk,
     * it is one of core's themeless ones.
     */
    private function themeless(string $profile): bool
    {
        $themes = ($this->profileThemes)();

        return array_key_exists($profile, $themes)
            ? !$themes[$profile]
            : in_array($profile, self::THEMELESS_PROFILES, strict: true);
    }

    /**
     * The property as the nearest class up the chain declares it. Mago's
     * property lookup sees only a class's own declarations, so a test
     * inheriting the value from a base class needs the walk.
     */
    private static function nearest(ClassFacts $class, string $property): ?PropertyMetadata
    {
        $codebase = $class->codebase;
        $name = $class->name();
        for ($depth = 0; $depth < self::MAX_DEPTH && $name !== null; $depth++) {
            $found = $codebase->getProperty($name, $property);
            if ($found !== null) {
                return $found;
            }

            $name = $codebase->getClassLike($name)?->directParentClass;
        }

        return null;
    }
}
