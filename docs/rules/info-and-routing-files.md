# Info and routing files

Rules for the `.info.yml` file of a module, theme or profile and for `.routing.yml` files. They need
`yml` in Mago's extensions, as [Setup](../setup.md#install) shows. Mago reads a YAML file as text
outside PHP tags, and these rules read the YAML in it.

## drupal/info-auto-added-keys

- **Level:** warning
- **Fix:** none
- **Ports:** `Drupal.InfoFiles.AutoAddedKeys`: `Project`, `Timestamp`, `Version`

A `project`, `datestamp` or `version` key in an `.info.yml` file. The drupal.org packaging script
adds them to the info files of a release, so the files in the repository must not have them. An
info file under a `core/` directory may keep `version`, as in Coder.

**Compared with Coder:** the rule reports each key on its line. Coder reports them on the first line
of the file.

## drupal/info-core-version-requirement

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.InfoFiles.CoreVersionRequirement.CoreVersionRequirement`
- **Off with `--core`**

An `.info.yml` file with a `type` key and no `core_version_requirement` key. Drupal installs the
extension only on the core versions that the key names. A test module, with `package: Testing`, may
leave the key out. A file whose name has a dot before `.info.yml` is skipped, as in Coder, since a
config file can be named that way.

**Compared with Coder:** Coder skips a file with a dot anywhere in its path before `.info.yml`, such
as in a directory named `my.site`. The rule looks at the file name only.

## drupal/info-dependencies-array

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.InfoFiles.DependenciesArray.Dependencies`
- **Off with `--core`**

A `dependencies` key in an `.info.yml` file that does not hold a list, such as
`dependencies: drupal:node`.

## drupal/info-description

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.InfoFiles.Description`: `Empty`, `Missing`
- **Off with `--core`**

An `.info.yml` file with a `type` key and no `description`, or an empty one. The rule skips the same
files as `drupal/info-core-version-requirement`.

**Compared with Coder:** the rule reports an empty description on its line. Coder reports it on the
first line of the file.

## drupal/info-namespaced-dependency

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.InfoFiles.NamespacedDependency.NonNamespaced`

A dependency in an `.info.yml` file without the name of its project, such as `- node` for
`- drupal:node`. As in Coder, the rule reads the lines of a `dependencies:` list until a line that
is neither a dependency nor a comment. A list written on one line, as in `[node]`, is not read, and
a theme is skipped.

## drupal/routing-access

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.Yaml.RoutingAccess`: `OpenCallback`, `PermissionFound`
- **Off with `--core`**

In a `.routing.yml` file:

- an `_access: 'TRUE'` line without a comment on the line above. A route that anyone may open
  needs a comment that says why;
- an `_permission: 'access administration pages'` line. That permission lets a user view an
  administration page. A page that changes settings needs `administer site configuration`.

As in Coder, the rule matches the lines as written, with single quotes, so `_access: "TRUE"` is not
reported.
