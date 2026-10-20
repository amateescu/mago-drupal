# Config

The analyzer types config objects, and the values read from them by their config schema. The checks
report config names without a schema, and keys that a fully validatable schema doesn't list.

## Config objects

These calls return a config object:

- `\Drupal::config('name')`;
- a config factory's `get('name')` and `getEditable('name')`;
- the `config('name')` method of a class that uses `ConfigFormBaseTrait`, or that extends
  `ControllerBase` or `FormBase`.

For a literal name, the analyzer tags the object with the name, as in
`ImmutableConfig<'system.maintenance'>`. The `config()` method of a controller or of a plain form
returns an immutable object, whatever `ControllerBase` documents.

`get('key')` on a tagged object is typed from the config schema. The schema is read from
`core/config/schema/` and from the `config/schema/` directory of every module, profile and theme
under the Drupal root, test extensions included.

```php
\Drupal::config('system.maintenance')->get('message');   // string|null
\Drupal::config('system.cron')->get('threshold.requirements_warning'); // int|null
\Drupal::config('user.role.anonymous')->get('permissions'); // array<int|string, string>|null
\Drupal::config('system.maintenance')->get('nope');      // reported as drupal/config-unknown-key
// array{'requirements_error': int, 'requirements_warning': int}|null
\Drupal::config('system.cron')->get('threshold');
```

Only a config whose schema has the `FullyValidatable` constraint is typed and checked, since only
then do the stored values have to match the schema. Any other config keeps the declared `mixed`.

The schema is read the way `TypedConfigManager` reads it:

- the `type:` of a definition names a parent, whose keys the definition inherits;
- a config name without a definition of its own falls back to a wildcard name, such as
  `user.role.*`;
- a `type:` with a placeholder in the `[%key]` style is left untyped.

The schema types map to PHP types:

- `string`, for `string`, `label`, `text`, `path`, `uri`, `email`, `uuid`, `langcode` and the other
  string types;
- `int`, for `integer`, `weight` and `timestamp`;
- `float` and `bool`;
- `array<int|string, T>`, for a sequence;
- an array shape, for a mapping. The shape lists every key of the mapping. A key is required unless
  it says `requiredKey: false` or is marked `deprecated:`, and `nullable: true` adds `null` to its
  type. Drupal rejects the keys that a fully validatable mapping doesn't list, so the shape doesn't
  hold any others. A mapping that doesn't list any keys is `array<string, mixed>`.

A key passed to `get()` can always be absent, so `null` stays in its type. `get()` without a key
gives the shape of the whole object.

## Config storage

`read('name')` on a config `StorageInterface` returns the stored data of the whole object, with the
same shape, or `false` when the storage doesn't have the name:

```php
// array{'_core'?: array{'default_config_hash': string}, 'langcode'?: string,
//   'module': array<int|string, int>, 'profile'?: null|string, 'theme': array<int|string, int>}
//   |false
$storage->read('core.extension');
```

A storage for another collection, such as a language override, holds only part of the object. The
shape doesn't show that.

---

## drupal/config-unknown-key

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `ConfigGetUnknownKeyRule`

phpstan-drupal reports a config key that the schema doesn't list. A fully validatable schema lists
every key, so a key that it doesn't list is a mistake.

**Reports:**

- a `get('key')` call on a config object with a known name, for a key that the fully validatable
  schema of that config doesn't list. A nested key, such as `threshold.requirements_warning`, is
  checked part by part.

**Not reported:**

- a config whose schema isn't fully validatable;
- a config object whose name isn't a literal;
- a key that isn't one literal string;
- a key that the schema can't rule out: one below a `type:` with a placeholder, below a type that
  the schema doesn't spell out, or the part of a key that indexes a sequence;
- update code (`.install` and `.post_update.php` files), which reads the keys that an older version
  of the module wrote.

??? example "Example"

    ```yaml title="modules/example/config/schema/example.schema.yml"
    example.settings:
      type: config_object
      label: 'Example settings'
      constraints:
        FullyValidatable: ~
      mapping:
        message:
          type: label
    ```

    ```php
    // Reported.
    function example_message(): ?string {
      return \Drupal::config('example.settings')->get('mesage');
    }

    // Fine.
    function example_message(): ?string {
      return \Drupal::config('example.settings')->get('message');
    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal reads the config name only when `get()` is called on the result of the loader
      call itself, as in `\Drupal::config('name')->get('key')`. A config object kept in a variable
      isn't checked there. The check follows the tagged object.
    - phpstan-drupal doesn't check the `config()` method of a controller or of a plain form.
    - phpstan-drupal also checks a key that is one of several literals, and it checks update code.

---

## drupal/config-unknown-name

- **Level:** warning
- **Source:** Drupal's config schema

Drupal returns an empty config object for a name that doesn't exist, with no warning. Config
without a schema also fails Drupal's strict schema checks in tests.

**Reports:**

- a literal config name without a schema. The check reads the name passed to
  `\Drupal::config()`, a config factory's `get()` or `getEditable()`, or the `config()` method of a
  class that uses `ConfigFormBaseTrait` or extends `ControllerBase` or `FormBase`. A name that a
  wildcard definition such as `user.role.*` covers counts as having a schema.

**Not reported:**

- a name whose first part isn't `core` and doesn't name a module in the codebase. A module that
  reads the config of an optional module can't expect its schema;
- any name while core's own schema isn't in the index;
- update code (`.install` and `.post_update.php` files);
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).

??? example "Example"

    ```yaml title="modules/example/config/schema/example.schema.yml"
    example.settings:
      type: config_object
      label: 'Example settings'
      mapping:
        message:
          type: label
    ```

    ```php
    // Reported.
    function example_message(): mixed {
      return \Drupal::config('example.setings')->get('message');
    }

    // Fine.
    function example_message(): mixed {
      return \Drupal::config('example.settings')->get('message');
    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal doesn't check config names.
