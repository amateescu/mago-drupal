<?php

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;

use function format_date;
use function t;

/**
 * Calls procedural wrappers from a class that cannot get services injected.
 */
class GlobalFunctionPlain {

  /**
   * Translates with t(), which Coder reports in any class.
   */
  public function translate(): string {
    // @mago-expect lint:drupal/global-function
    return t('Hello');
  }

  /**
   * Formats a date, which Coder reports only in an injectable class.
   */
  public function format(int $timestamp): string {
    return format_date($timestamp);
  }

}

/**
 * Calls procedural wrappers from a form.
 */
class GlobalFunctionForm extends FormBase {

  /**
   * Formats a date.
   */
  public function format(int $timestamp): string {
    // @mago-expect lint:drupal/global-function
    return format_date($timestamp);
  }

  /**
   * Formats a date with a fully qualified call, which Coder skips.
   */
  public function formatQualified(int $timestamp): string {
    // @mago-expect lint:drupal/global-function
    return \format_date($timestamp);
  }

  /**
   * Formats a date in a static method, which has no $this.
   */
  public static function formatStatic(int $timestamp): string {
    return format_date($timestamp);
  }

  /**
   * Formats a date in an anonymous class, which is part of the form.
   */
  public function formatInAnonymousClass(): object {
    return new class {

      /**
       * Formats the date.
       */
      public function format(): string {
        // @mago-expect lint:drupal/global-function
        return format_date(0);
      }

    };
  }

  /**
   * Formats a date in an anonymous class inside a static method.
   */
  public static function formatInStaticMethod(): object {
    return new class {

      /**
       * Formats the date.
       */
      public function format(): string {
        return format_date(0);
      }

    };
  }

}

/**
 * Calls a procedural wrapper from a class that the container builds.
 */
class GlobalFunctionInjected implements ContainerInjectionInterface {

  /**
   * Formats a date.
   */
  public function format(int $timestamp): string {
    // @mago-expect lint:drupal/global-function
    return format_date($timestamp);
  }

}

/**
 * Calls a procedural wrapper from a service with a `class` key.
 */
class GlobalFunctionService {

  /**
   * Formats a date.
   */
  public function format(int $timestamp): string {
    // @mago-expect lint:drupal/global-function
    return format_date($timestamp);
  }

}

/**
 * Calls a procedural wrapper from a service named after its class.
 */
class GlobalFunctionAutowired {

  /**
   * Formats a date.
   */
  public function format(int $timestamp): string {
    // @mago-expect lint:drupal/global-function
    return format_date($timestamp);
  }

}

/**
 * Calls t() from a trait, which Coder does not check.
 */
trait GlobalFunctionTrait {

  /**
   * Translates.
   */
  public function translate(): string {
    return t('Hello');
  }

}
