<?php

declare(strict_types=1);

namespace Drupal\corpus;

use Symfony\Component\Yaml\Yaml;

/**
 * Calls with a comment between the callee and its parenthesis.
 *
 * The rules check the file text before they read any node. This file doesn't
 * have any other call to the names they look for, so each case shows that the
 * text check lets the file through.
 */
class CommentBeforeParenthesis {

  // @mago-format-ignore-start
  /**
   * Calls that the rules built on CallRule report.
   */
  public function calls(\CurlHandle $handle, string $text): string {
    // @mago-expect lint:drupal/curl-ssl-verify
    curl_setopt /* Reason. */ ($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
    // @mago-expect lint:drupal/preg-security
    $text = (string) preg_replace /* Reason. */ ('/a/e', 'b', $text);

    // @mago-expect lint:drupal/weak-hash
    return md5
      // Reason.
      ($text);
  }

  /**
   * A static call to the Symfony parser.
   */
  public function parse(string $yaml): mixed {
    // @mago-expect lint:drupal/symfony-yaml-parse
    return Yaml:: /* Reason. */ parse /* Reason. */ ($yaml);
  }

  /**
   * A translated exception message.
   */
  public function fail(): never {
    // @mago-expect lint:drupal/global-function
    // @mago-expect lint:drupal/translated-exception
    // @mago-expect analysis:invalid-argument
    throw new \RuntimeException(t /* Reason. */ ('Failed.'));
  }

  /**
   * A translated string with a trailing space, through a method.
   */
  public function label(): string {
    // @mago-expect lint:drupal/translatable-string
    return $this->t /* Reason. */ ('Save ');
  }

  /**
   * Stands in for StringTranslationTrait::t().
   */
  protected function t(string $string): string {
    return $string;
  }

  /**
   * A compact() call may read the catch variable.
   */
  public function compacted(): array {
    try {
      $this->parse('');
    }
    catch (\RuntimeException $e) {
    }

    return compact /* Reason. */ ('e');
  }
  // @mago-format-ignore-end

}
