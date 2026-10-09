<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/function-comment
function verification_off(\CurlHandle $handle): void {
  // @mago-expect lint:drupal/curl-ssl-verify
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
}

// @mago-expect lint:drupal/function-comment
function verification_off_with_zero(\CurlHandle $handle): void {
  // @mago-expect lint:drupal/curl-ssl-verify
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
}

// @mago-expect lint:drupal/function-comment
function verification_off_in_other_spellings(\CurlHandle $handle): void {
  // @mago-expect lint:drupal/curl-ssl-verify
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
  // @mago-expect lint:drupal/curl-ssl-verify
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
  // @mago-expect lint:drupal/curl-ssl-verify
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, \FALSE);
  // @mago-expect lint:drupal/curl-ssl-verify
  curl_setopt($handle, \CURLOPT_SSL_VERIFYPEER, FALSE);
  // @mago-expect lint:drupal/curl-ssl-verify
  \curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
  // @mago-expect lint:drupal/curl-ssl-verify
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0x0);
}

// @mago-expect lint:drupal/function-comment
function verification_off_with_names(\CurlHandle $handle): void {
  // @mago-expect lint:drupal/curl-ssl-verify
  curl_setopt(handle: $handle, option: CURLOPT_SSL_VERIFYPEER, value: FALSE);
}

// @mago-expect lint:drupal/function-comment
function verification_off_over_several_lines(\CurlHandle $handle): void {
  // @mago-expect lint:drupal/curl-ssl-verify
  curl_setopt(
    $handle,
    CURLOPT_SSL_VERIFYPEER,
    FALSE,
  );
}

// @mago-expect lint:drupal/function-comment
function verification_off_in_a_closure(\CurlHandle $handle): callable {
  return function () use ($handle): void {
    // @mago-expect lint:drupal/curl-ssl-verify
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
  };
}

// @mago-expect lint:drupal/function-comment
function verification_on(\CurlHandle $handle): void {
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, TRUE);
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 1);
}

// @mago-expect lint:drupal/function-comment
function other_options(\CurlHandle $handle, bool $verify): void {
  curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, FALSE);
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, $verify);
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, NULL);
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, '0');
  curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, !TRUE);
  curl_setopt($handle, CURLOPT_FOLLOWLOCATION, FALSE);
}

// @mago-expect lint:drupal/function-comment
function spread_arguments(\CurlHandle $handle): void {
  $arguments = [CURLOPT_SSL_VERIFYPEER, FALSE];
  curl_setopt($handle, ...$arguments);
}

/**
 * Has methods that share the name of the function.
 */
class CurlClient {

  /**
   * Stands in for the function.
   */
  public function curl_setopt(\CurlHandle $handle, int $option, mixed $value): bool {
    return TRUE;
  }

  /**
   * Stands in for the function.
   */
  public static function setOption(\CurlHandle $handle, int $option, mixed $value): bool {
    return TRUE;
  }

  /**
   * Calls the methods.
   */
  public function call(\CurlHandle $handle, ?CurlClient $other): void {
    $this->curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
    $other?->curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
    self::setOption($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
  }

}
