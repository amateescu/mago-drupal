<?php

declare(strict_types=1);

namespace Drupal\corpus;

use function format_date;
use function t;

class Practice
{
    public function translatedException(): never
    {
        // Both rules fire here: the message is translated, and t() is the
        // procedural form of $this->t() inside a class.
        // @mago-expect lint:drupal/translated-exception
        // @mago-expect lint:drupal/global-function
        throw new \RuntimeException(t('This should not be translated.'));
    }

    public function plainException(): never
    {
        throw new \RuntimeException('This is fine.');
    }

    public function proceduralCall(int $timestamp): string
    {
        // @mago-expect lint:drupal/global-function
        return format_date($timestamp);
    }

    public function fullyQualifiedProceduralCall(int $timestamp): string
    {
        // @mago-expect lint:drupal/global-function
        return \format_date($timestamp);
    }

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

    public function translationMethodIsNotProcedural(): string
    {
        // $this->t() reports the same callee name as t(), and it is the fix
        // this rule recommends, so it must never be reported as the problem.
        return $this->t('Already correct.');
    }

    public function globalInAClassFileIsNotChecked(): void
    {
        // drupal/global-variable only fires in .module and .install files.
        global $corpus_unprefixed;

        $corpus_unprefixed = [];
    }
}
