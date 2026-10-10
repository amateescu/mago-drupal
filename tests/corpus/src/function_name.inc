<?php

/**
 * @file
 * Exercises drupal/function-name in an .inc file.
 */

declare(strict_types=1);

/**
 * Has a lower-case name.
 */
function function_name_fine(): void {
}

/**
 * Has a leading underscore, which Coder accepts.
 */
function _function_name_helper(): void {
}

/**
 * Has a camelCase name.
 */
// @mago-expect lint:drupal/function-name
function functionNameCamel(): void {
}

/**
 * Has a capital after an underscore.
 */
// @mago-expect lint:drupal/function-name
function function_name_With_capital(): void {
}

/**
 * Starts with hook_ outside an API file, which Coder checks.
 */
// @mago-expect lint:drupal/function-name
function hook_ENTITY_TYPE_view(): void {
}

/**
 * Declares a function in a function, which Coder checks.
 */
function function_name_declares_helper(): void {
  /**
   * Lives in a function.
   */
  // @mago-expect lint:drupal/function-name
  function functionNameInner(): void {
  }
}

/**
 * Declares functions in methods, which Coder does not check.
 */
final class CorpusFunctionNameNested {

  /**
   * Declares a function when it runs.
   */
  public function build(): object {
    /**
     * Lives in a class method.
     */
    function functionNameInMethod(): void {
    }

    return new class {

      /**
       * Declares a function when it runs.
       */
      public function build(): void {
        /**
         * Lives in an anonymous class method.
         */
        function functionNameInAnonymousClass(): void {
        }
      }

    };
  }

}
