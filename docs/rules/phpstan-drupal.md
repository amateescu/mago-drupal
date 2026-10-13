# Ported from phpstan-drupal

These rules port checks of [phpstan-drupal](https://github.com/mglaman/phpstan-drupal) that need
only the syntax.

---

## drupal/discouraged-function

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard and phpstan-drupal
- **Ports:**
    - `Drupal.Functions.DiscouragedFunctions.Discouraged`
    - phpstan-drupal's `DiscouragedFunctionsRule`

Coder and phpstan-drupal report calls to debug helpers and to functions that are not always
available.

**Reports:**

- a call to a dump helper of the devel module, such as `dpm()`, `dsm()`, `ksm()` or `kint()`. The
  devel helpers are the ones that Coder 9 lists. A first-class callable such as `dpm(...)` counts as
  a call;
- a call to `fnmatch()`, which some PHP builds do not have.

Coder 9 also lists `eval`, which Mago's own `no-eval` rule reports.

---

## drupal/render-callback

- **Level:** error
- **Fix:** none
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `RenderCallbackRule`, the part that needs only the syntax

phpstan-drupal reports a [render callback](https://www.drupal.org/node/2966725) that Drupal does not
trust. Drupal trusts only closures, `service:method` strings and class methods.

**Reports:**

- a render callback that is a plain function name string, in the callbacks of:
    - `#pre_render`, `#post_render`, `#lazy_builder` and `#access_callback`;
    - the date callbacks `#date_date_callbacks` and `#date_time_callbacks`;
    - the component callbacks `#propsAlter` and `#slotsAlter`;
- a value of those keys that is not an array literal at all, such as `'#pre_render' => $callbacks`,
  because nothing can be checked there.

**Not reported:**

- `#lazy_builder` in core's `Renderer` and `PlaceholderGenerator`, because they pass the key
  through `array_intersect_key()`.

??? example "Example"

    ```php
    // Reported.
    $build = [
      '#markup' => '',
      '#pre_render' => ['example_pre_render'],
    ];

    // Fine.
    $build = [
      '#markup' => '',
      '#pre_render' => [static fn (array $element): array => $element],
    ];
    ```

---

## drupal/symfony-yaml-parse

- **Level:** warning
- **Fix:** none
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `SymfonyYamlParseRule`

phpstan-drupal reports a parse with Symfony's YAML component. Drupal's own
`\Drupal\Component\Serialization\Yaml::decode()` picks the fast parser when it is available and
applies Drupal's parser flags.

**Reports:**

- a `Symfony\Component\Yaml\Yaml::parse()` call.

??? example "Example"

    ```php
    // Reported.
    use Symfony\Component\Yaml\Yaml;

    $data = Yaml::parse($text);

    // Fine.
    use Drupal\Component\Serialization\Yaml;

    $data = Yaml::decode($text);
    ```
