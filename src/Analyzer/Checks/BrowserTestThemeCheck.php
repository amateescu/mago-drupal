<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\Types;
use Closure;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;

use function array_key_exists;
use function in_array;
use function str_ends_with;
use function strtolower;

/**
 * Functional tests have to declare the theme they run with; runs on
 * BrowserTestBase descendants.
 *
 * Ports phpstan-drupal's BrowserTestBaseDefaultThemeRule with Drupal's own
 * rule for which profiles need it: those that ship no `system.theme` config.
 * `$profile` and `$defaultTheme` are read from the declaration the class
 * gets, so a base class or a trait can set them for the tests using it.
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
     * Where core declares the method that installs `$defaultTheme` and throws
     * when it is not set.
     */
    private const THEME_INSTALLER = 'drupal\core\test\functionaltestsetuptrait';

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
     * reported, and most core tests do. Neither can one that assigns
     * `$this->defaultTheme`, such as in `setUp()` before the parent's.
     */
    public function textGate(): ?string
    {
        return '/\A(?!.*(?:\$defaultTheme\s*+=\s*+([\'"])(?!\1)|->defaultTheme\s*+=[^=]))/s';
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

        // Mago keeps the declaration a class gets for each property: its own,
        // then a trait's, then a parent's, which is the order PHP applies.
        [$profileProperty, $themeProperty] = $class->codebase->getMultipleDeclaringProperties([
            new MemberIdentifier($name, '$profile'),
            new MemberIdentifier($name, '$defaultTheme'),
        ]);

        // A profile with a theme of its own needs no explicit default theme,
        // and a NULL or FALSE one installs from existing configuration, whose
        // theme the test then takes. A profile read from a constant cannot be
        // checked, so it is left alone.
        $profile = $profileProperty?->defaultType?->type;
        $profileName = $profile?->getLiteralString();
        if (
            Types::includesNull($profile)
            || $profile?->getLiteralBool() === false
            || $profile !== null && $profileName === null
            || $profileName !== null && !$this->themeless($profileName)
        ) {
            return;
        }

        if (Types::holdsValue($themeProperty?->defaultType?->type) || self::installsOwnTheme($class)) {
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
     * Whether the class, a base class or a trait overrides the method that
     * installs the theme, as a trait setting `$this->defaultTheme` at run
     * time does. What the override does is not known, so the class is not
     * reported.
     */
    private static function installsOwnTheme(ClassFacts $class): bool
    {
        $declaring =
            $class->codebase->findMethods(
                class: $class->class->name,
                name: 'installDefaultThemeFromClassProperty',
                fields: 0,
            )[0]->identifier->class ?? null;

        return $declaring !== null && strtolower($declaring) !== self::THEME_INSTALLER;
    }
}
