# Info and routing files

Rules for the `.info.yml` file of a module, theme or profile and for `.routing.yml` files. They need
`yml` in Mago's extensions, as [Setup](../setup.md#install) shows. Mago reads a YAML file as text
outside PHP tags, and these rules read the YAML in it.

---

## drupal/info-auto-added-keys

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.InfoFiles.AutoAddedKeys`: `Project`, `Timestamp`, `Version`

Coder asks for no `project`, `datestamp` or `version` key in an `.info.yml` file. The drupal.org
packaging script adds them to the info files of a release, so the files in the repository must not
have them.

**Reports:**

- a `project`, `datestamp` or `version` key in an `.info.yml` file, each on its line.

**Not reported:**

- `version` in an info file under a `core/` directory.

??? note "Compared with Coder"

    - Coder reports the keys on the first line of the file.

---

## drupal/info-core-version-requirement

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.InfoFiles.CoreVersionRequirement.CoreVersionRequirement`
- **Off with `--core`**

Coder's `DrupalPractice` standard asks the `.info.yml` file of an extension for a
`core_version_requirement` key. Drupal installs the extension only on the core versions that the
key names.

**Reports:**

- an `.info.yml` file with a `type` key and no `core_version_requirement` key.

**Not reported:**

- a test module, with `package: Testing`;
- a file whose name has a dot before `.info.yml`, since a config file can be named that way.

??? example "Example"

    ```yaml title="example.info.yml"
    # Reported.
    name: Example
    type: module
    description: 'Provides examples.'
    # Fine.
    name: Example
    type: module
    description: 'Provides examples.'
    core_version_requirement: ^10.3 || ^11
    ```

??? note "Compared with Coder"

    - Coder skips a file with a dot anywhere in its path before `.info.yml`, such as in a directory
      named `my.site`. The rule looks at the file name only.

---

## drupal/info-dependencies-array

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.InfoFiles.DependenciesArray.Dependencies`
- **Off with `--core`**

Coder asks for the `dependencies` of an `.info.yml` file as a list.

**Reports:**

- a `dependencies` key in an `.info.yml` file that does not hold a list, such as
  `dependencies: drupal:node`.

??? example "Example"

    ```yaml title="example.info.yml"
    # Reported.
    name: Example
    type: module
    dependencies: drupal:node
    # Fine.
    name: Example
    type: module
    dependencies:
      - drupal:node
    ```

---

## drupal/info-description

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.InfoFiles.Description`: `Empty`, `Missing`
- **Off with `--core`**

Coder's `DrupalPractice` standard asks the `.info.yml` file of an extension for a description.

**Reports:**

- an `.info.yml` file with a `type` key and no `description`;
- an empty description, on its line.

**Not reported:**

- the files that `drupal/info-core-version-requirement` skips.

??? note "Compared with Coder"

    - Coder reports an empty description on the first line of the file.

---

## drupal/info-namespaced-dependency

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.InfoFiles.NamespacedDependency.NonNamespaced`

Coder's `DrupalPractice` standard asks for each dependency with the name of its project.

**Reports:**

- a dependency in an `.info.yml` file without the name of its project, such as `- node` for
  `- drupal:node`.

The rule reads the lines of a `dependencies:` list until a line that is neither a dependency nor a
comment.

**Not reported:**

- a list written on one line, as in `[node]`;
- a theme.

---

## drupal/routing-access

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.Yaml.RoutingAccess`: `OpenCallback`, `PermissionFound`
- **Off with `--core`**

Coder's `DrupalPractice` standard checks the access requirements of routes.

**Reports:**

- an `_access: 'TRUE'` line in a `.routing.yml` file without a comment on the line above. A route
  that anyone may open needs a comment that says why;
- an `_permission: 'access administration pages'` line in a `.routing.yml` file. That permission
  lets a user view an administration page. A page that changes settings needs
  `administer site configuration`.

The rule matches the lines as written, with single quotes.

**Not reported:**

- `_access: "TRUE"`, with double quotes.

???+ example "Example"

    ```yaml title="example.routing.yml"
    # Reported.
    example.page:
      path: '/example'
      defaults:
        _controller: '\Drupal\example\Controller\ExampleController::page'
      requirements:
        _access: 'TRUE'
    # Fine.
    example.page:
      path: '/example'
      defaults:
        _controller: '\Drupal\example\Controller\ExampleController::page'
      requirements:
        # The page shows only public information.
        _access: 'TRUE'
    ```
