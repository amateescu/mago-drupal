<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Three capitals in a row at the start of a class name.
 */
// @mago-expect lint:drupal/class-name-acronym
class ACA {}

/**
 * An abstract class.
 */
// @mago-expect lint:drupal/class-name-acronym
abstract class ACB {}

/**
 * A final class that extends another one. The base name is not checked twice.
 */
// @mago-expect lint:drupal/class-name-acronym
final class ACC extends ACA {}

/**
 * An interface.
 */
// @mago-expect lint:drupal/class-name-acronym
interface ACD {}

/**
 * A trait.
 */
// @mago-expect lint:drupal/class-name-acronym
trait ACE {}

/**
 * A pure enum.
 */
// @mago-expect lint:drupal/class-name-acronym
enum ACF {

  case One;

}

/**
 * A backed enum.
 */
// @mago-expect lint:drupal/class-name-acronym
enum ACG: string {

  case One = 'one';

}

/**
 * A class with an attribute.
 */
// @mago-expect lint:drupal/class-name-acronym
#[CorpusAttribute]
class ACH {}

/**
 * Digits and underscores after the capitals do not end the acronym.
 */
// @mago-expect lint:drupal/class-name-acronym
class ACI9_ {}

/**
 * The test works on bytes, so a multi-byte letter does not count as lower case.
 */
// @mago-expect lint:drupal/class-name-acronym
class ACJé {}

// @mago-format-ignore-start
/**
 * The report is on the name, which is on its own line.
 */
// @mago-expect lint:drupal/class-name-acronym
final
class
ACK
{}
// @mago-format-ignore-end

/**
 * A lower-case letter after two capitals.
 */
class ACl {}

/**
 * A lower-case letter right after three capitals.
 */
class ACMn {}

/**
 * Two capitals only.
 */
class AC {}

/**
 * A digit between the capitals.
 */
class A1B2C3 {}

/**
 * A normal UpperCamelCase name.
 */
class UpperCamelCase {}

/**
 * Uses the names of the classes above without declaring them.
 */
class ClassNameAcronymUsers {

  /**
   * Names a class with three capitals without declaring it.
   */
  public function uses(): string {
    $anonymous = new class() extends ACA {};

    return ACA::class . $anonymous::class;
  }

}
