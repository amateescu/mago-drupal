<?php

declare(strict_types=1);

namespace Drupal\corpus;

use function format_date;
use function t;

/**
 * Exercises the DrupalPractice rules ported as native linter rules.
 */
class Practice
{
    // @mago-expect lint:drupal/function-comment
    public function translatedException(): never
    {
        // Both rules fire here.
        // The message is translated, and t() is the procedural form of
        // $this->t().
        // @mago-expect lint:drupal/translated-exception
        // @mago-expect lint:drupal/global-function
        throw new \RuntimeException(t('This should not be translated.'));
    }

    // @mago-expect lint:drupal/function-comment
    public function plainException(): never
    {
        throw new \RuntimeException('This is fine.');
    }

    // @mago-expect lint:drupal/function-comment
    public function proceduralCall(int $timestamp): string
    {
        // @mago-expect lint:drupal/global-function
        return format_date($timestamp);
    }

    // @mago-expect lint:drupal/function-comment
    public function fullyQualifiedProceduralCall(int $timestamp): string
    {
        // @mago-expect lint:drupal/global-function
        return \format_date($timestamp);
    }

    // @mago-expect lint:drupal/function-comment
    public static function staticCallIsExempt(int $timestamp): string
    {
        return format_date($timestamp);
    }

    /**
     * Stands in for StringTranslationTrait::t().
     */
    protected function t(string $string): string
    {
        return $string;
    }

    // @mago-expect lint:drupal/function-comment
    public function translationMethodIsNotProcedural(): string
    {
        // $this->t() reports the same callee name as t().
        // It is the fix this rule recommends, so it must never be flagged.
        return $this->t('Already correct.');
    }

    // @mago-expect lint:drupal/function-comment
    public function unserializing(string $payload): void
    {
        // @mago-expect lint:drupal/insecure-unserialize
        unserialize($payload);
        // @mago-expect lint:drupal/insecure-unserialize
        unserialize($payload, ['max_depth' => 2]);
        // @mago-expect lint:drupal/insecure-unserialize
        unserialize($payload, ['allowed_classes' => TRUE]);

        unserialize($payload, ['allowed_classes' => FALSE]);
        unserialize($payload, ['allowed_classes' => [self::class]]);

        // PHP binds this by parameter name, so the options are there.
        unserialize($payload, options: ['allowed_classes' => FALSE]);

        // The rule cannot see into a variable, and says so.
        $options = ['allowed_classes' => FALSE];
        // @mago-expect lint:drupal/insecure-unserialize
        unserialize($payload, $options);

        // A payload this function serialized itself carries no foreign class.
        unserialize(serialize($payload));
        $serialized = serialize([self::class]);
        unserialize($serialized);

        // A variable that is also written from elsewhere is not trusted.
        $mixed = serialize($payload);
        $mixed = $payload;
        // @mago-expect lint:drupal/insecure-unserialize
        unserialize($mixed);
    }

    // @mago-expect lint:drupal/function-comment
    public function upperCaseBranching(int $value): int
    {
        if ($value === 1) {
            return 1;
        }
        // PHP keywords are case-insensitive, so this is the same construct.
        // @mago-expect lint:drupal/else-if
        ELSE IF ($value === 2) {
            return 2;
        }

        return 0;
    }

    // @mago-expect lint:drupal/function-comment
    public function branching(int $value): int
    {
        if ($value === 1) {
            return 1;
        }
        // @mago-expect lint:drupal/else-if
        else if ($value === 2) {
            return 2;
        }
        elseif ($value === 3) {
            return 3;
        }
        else {
            // A braced nested if is a different construct.
            if ($value === 4) {
                return 4;
            }
        }

        return 0;
    }

    // @mago-expect lint:drupal/function-comment
    public function globalInAClassFileIsNotChecked(): void
    {
        // drupal/global-variable only fires in .module and .install files.
        global $corpus_unprefixed;

        $corpus_unprefixed = [];
    }
}
