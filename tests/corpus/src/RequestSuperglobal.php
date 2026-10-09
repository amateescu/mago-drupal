<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/function-comment
function reads_a_key(): mixed {
  // @mago-expect lint:drupal/request-superglobal
  return $_GET['a'];
}

// @mago-expect lint:drupal/function-comment
function reads_the_whole_array(): array {
  // @mago-expect lint:drupal/request-superglobal
  return $_POST;
}

// @mago-expect lint:drupal/function-comment
function reads_the_other_superglobals(): array {
  // @mago-expect lint:drupal/request-superglobal
  $cookie = $_COOKIE['session'];
  // @mago-expect lint:drupal/request-superglobal
  $file = $_FILES['upload'];

  return [$cookie, $file];
}

// @mago-expect lint:drupal/function-comment
function reads_the_first_key_of_several(): mixed {
  // @mago-expect lint:drupal/request-superglobal
  return $_GET['a']['b'];
}

// @mago-expect lint:drupal/function-comment
function reads_with_a_variable_key(string $key): mixed {
  // @mago-expect analysis:mismatched-array-index
  // @mago-expect lint:drupal/request-superglobal
  return $_GET[$key];
}

// @mago-expect lint:drupal/function-comment
function reads_twice_on_one_line(): mixed {
  // @mago-expect lint:drupal/request-superglobal(2)
  return $_GET['a'] ?? $_POST['b'] ?? '';
}

// @mago-expect lint:drupal/function-comment
function writes_a_key(): void {
  // @mago-expect lint:drupal/request-superglobal
  $_POST['a'] = 'value';
  // @mago-expect lint:drupal/request-superglobal
  $_GET[] = 'value';
}

// @mago-expect lint:drupal/function-comment
function tests_and_clears(): void {
  // @mago-expect lint:drupal/request-superglobal
  if (isset($_GET['a'])) {
    // @mago-expect lint:drupal/request-superglobal
    unset($_GET['a']);
  }

  // @mago-expect lint:drupal/request-superglobal
  foreach ($_COOKIE as $name => $value) {
    unset($name, $value);
  }
}

// @mago-expect lint:drupal/function-comment
function reads_in_a_closure(): callable {
  // @mago-expect lint:drupal/request-superglobal
  return fn (): mixed => $_GET['a'];
}

// @mago-expect lint:drupal/function-comment
function reads_in_a_string(): array {
  return [
    // @mago-expect analysis:array-to-string-conversion
    // @mago-expect lint:drupal/request-superglobal
    "Value $_GET[a]",
    // @mago-expect analysis:array-to-string-conversion
    // @mago-expect lint:drupal/request-superglobal
    "Value {$_POST['a']}",
  ];
}

// @mago-expect lint:drupal/function-comment
function reads_a_static_property(): mixed {
  // The variable of a static property is also the superglobal.
  // @mago-expect lint:drupal/request-superglobal
  return RequestSuperglobalHolder::$_GET;
}

// @mago-expect lint:drupal/function-comment
function leaves_other_names(RequestSuperglobalHolder $object): array {
  return [
    // $_REQUEST belongs to the no-request-variable rule of Mago.
    $_REQUEST['a'],
    $_SERVER['REQUEST_METHOD'],
    $_SESSION['a'] ?? NULL,
    $_ENV['a'],
    $GLOBALS['a'],
    $_get ?? NULL,
    $object->_POST,
    '$_GET[a]',
    // Reads $_GET['a'] in a comment.
    "\$_GET[a]",
  ];
}

/**
 * Declares properties that are named like a superglobal.
 */
class RequestSuperglobalHolder {

  // The declaration of a variable with the name is reported too.
  // @mago-expect lint:drupal/request-superglobal
  // @mago-expect lint:drupal/property-name
  /**
   * The value.
   *
   * @var mixed
   */
  public static $_GET;

  // @mago-expect lint:drupal/request-superglobal
  // @mago-expect lint:drupal/property-name
  /**
   * The value of a property.
   *
   * @var mixed
   */
  public $_POST;

}
