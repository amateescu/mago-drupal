# Ported from phpstan-drupal

These rules port checks of [phpstan-drupal](https://github.com/mglaman/phpstan-drupal) that need
only the syntax.

## drupal/discouraged-function

- **Level:** error
- **Fix:** none
- **Ports:**
    - `Drupal.Functions.DiscouragedFunctions.Discouraged`
    - phpstan-drupal's `DiscouragedFunctionsRule`

A call to a dump helper of the devel module, such as `dpm()`, `dsm()`, `ksm()` or `kint()`, or a
call to `fnmatch()`, which some PHP builds do not have. The devel helpers are the ones that Coder 9
lists. A first-class callable such as `dpm(...)` counts as a call, as in Coder 9.

Coder 9 also lists `eval`, which Mago's own `no-eval` rule reports.

## drupal/render-callback

- **Level:** error
- **Fix:** none
- **Ports:** phpstan-drupal's `RenderCallbackRule`, the part that needs only the syntax

A [render callback](https://www.drupal.org/node/2966725) that is a plain function name string.
Drupal trusts only closures, `service:method` strings and class methods. The rule reads the
callbacks of:

- `#pre_render`, `#post_render`, `#lazy_builder` and `#access_callback`;
- the date callbacks `#date_date_callbacks` and `#date_time_callbacks`;
- the component callbacks `#propsAlter` and `#slotsAlter`.

It also reports a value that is not an array literal at all, such as
`'#pre_render' => $callbacks`, because nothing can be checked there. It skips core's `Renderer` and
`PlaceholderGenerator` for `#lazy_builder`, because they pass the key through
`array_intersect_key()`.

## drupal/symfony-yaml-parse

- **Level:** warning
- **Fix:** none
- **Ports:** phpstan-drupal's `SymfonyYamlParseRule`

A `Symfony\Component\Yaml\Yaml::parse()` call. Drupal's own
`\Drupal\Component\Serialization\Yaml::decode()` picks the fast parser when it is available and
applies Drupal's parser flags.
