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
        // The message is translated, and t() is the procedural form of $this->t().
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
    public function globalInAClassFileIsNotChecked(): void
    {
        // drupal/global-variable only fires in .module and .install files.
        global $corpus_unprefixed;

        $corpus_unprefixed = [];
    }
}
