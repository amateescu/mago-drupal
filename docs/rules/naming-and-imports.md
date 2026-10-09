# Naming and imports

Rules for the names of classes, constants, methods and properties, and for `use` statements. Mago's
own naming rules cover the rest, see [Setup](../setup.md#configure-mago-for-drupal).

## drupal/class-name-acronym

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.NamingConventions.ValidClassName.NoUpperAcronyms`

A class, interface, trait or enum name that starts with three upper-case letters and has no
lower-case letter after them, such as `HTTP` or `URL_2`. `HTTPClient` is fine. Digits, underscores
and multi-byte letters do not count as lower case, as in Coder 9.

**Compared with Coder:** the rule reports on the name. Coder 9 reports on the `class` keyword, so
the lines differ when a declaration spans several lines.

## drupal/define-name

- **Level:** error
- **Fix:** none
- **Ports:** `Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase`

A `define()` constant name that is not upper case. The rule reads the first argument, a string
literal or literals joined with `.`, and checks the part after the last backslash. Only ASCII
letters count. It reports in every scanned file type and at any depth. Mago's `constant-name`
covers `const`, the other half of the sniff.

**Compared with Coder:** the rule matches `define` without regard to case, with or without a
leading backslash. A name built from literals joined with `.` is checked as a whole. Coder 9 checks
only the first literal. A name that has a variable in it is skipped. Coder 9 reports
`'mymod_' . $x`.

## drupal/enum-case-name

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.NamingConventions.ValidEnumCase`: `NoUnderscores`, `NoUpperAcronyms`, `StartWithCapital`

An enum case name that does not start with a capital letter, that holds an underscore, or that
starts with three upper-case letters and has no lower-case letter after them. The last check is the
one of `drupal/class-name-acronym`.

## drupal/fully-qualified-name

- **Level:** error
- **Fix:** safe. It adds the import and writes the short name everywhere the file writes the class
  in full.
- **Ports:** `Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing`

A namespaced class written out in full where a `use` statement belongs. The rule skips a name with
no namespace of its own, such as `\Exception`, a namespaced function call or first-class callable,
and an `.api.php` file.

In a file with no namespace, the fix puts the import below the `@file` docblock and any `declare`,
or below the open tag, and after the last `use` when the file has one. There is no fix in a file
with several namespaces or a braced one, where the import would go below code or a constant, and
when the file already uses the short name for something else: another import, a class of that
name, or a docblock that writes the short name in the same case.

**Compared with Coder:**

- Coder reports a namespaced function call. Its fix imports the function name as a class, and the
  call then reaches the global function.
- The rule skips the names in a `use` group. Coder reports the member, and its fix changes the
  statement.
- A name in the conflict block of a trait `use` is not reported. Coder reports it.
- In a file with no namespace, the fix never puts an import in the clause of a closure. Coder's fix
  does when a closure holds the first `use` of the file.
- There is no fix when the short name is taken by an interface, a trait, an enum, an import that
  differs in case only, or a class whose name has a comment before it. Coder reports a fixable
  error there, and its fix writes code that PHP rejects.

## drupal/hook-attribute-name

- **Level:** warning
- **Fix:** none
- **Ports:** `Drupal.Attributes.ValidHookName.HookPrefix`
- **Off with `--core`**

A `Drupal\Core\Hook\Attribute\Hook` attribute whose first argument, positional or `hook:`, is a
hook name that starts with `hook_`. The attribute holds the hook name without the prefix, so such a
name is never invoked.

**Compared with Coder:**

- Coder's fix removes the prefix. The rule has no fix, because removing the prefix changes which
  hook runs.
- The rule resolves the attribute name, so an alias or a fully qualified name counts, and a bare
  `Hook` that is not imported from Drupal's class does not. Every attribute of a group is checked.
- It reads only the attribute's own first argument. Coder 9 reads the next string literal in the
  file, so it also reports an unrelated string below a `#[Hook(self::CRON)]` or a `#[Hook]`
  attribute.
- A hook name built from literals joined with `.` is checked as a whole.

## drupal/method-name-underscore

- **Level:** warning
- **Fix:** none
- **Ports:**
    - `Drupal.NamingConventions.ValidFunctionName.MethodDoubleUnderscore`
    - `PSR2.Methods.MethodDeclaration.Underscore`
- **Off with `--core`**

A method name that starts with one underscore to mark it private, and a name that starts with two
underscores and is not a PHP magic method or a `SoapClient` method. The names are matched without
regard to case. A name that is only two underscores, or starts with three, is fine.

The two-underscore check reads the same 29 names as Coder 9: the 17 magic methods of PHP and the
12 methods of `SoapClient`. Mago's `method-name` rule reports no name that starts with two
underscores, so no other rule repeats this check.

**Compared with Coder:** the rule reports on the name. Coder 9 reports on the `function` keyword,
so the lines differ when a declaration spans several lines.

## drupal/property-name

- **Level:** error
- **Fix:** none
- **Ports:**
    - `Drupal.NamingConventions.ValidVariableName.LowerCamelName`
    - `PSR2.Classes.PropertyDeclaration.Underscore`

A class property whose name does not start with a lower-case letter or holds an underscore. Local
variables are not checked, and Coder does not check them either.

Config entities and plugin annotations may name their properties in any case. Like Coder, the rule
skips a class whose parent name contains `ConfigEntity` or is `Plugin` or
`ViewsPluginAnnotationBase`, and a class that implements `AnnotationInterface`. The names are
compared as written, so `\Drupal\Core\Config\Entity\ConfigEntityBase` counts, while an alias of
`Plugin` or `\Drupal\Component\Annotation\Plugin` does not. Only the first parent of an interface
counts. The rule reads the outermost class around the property, and only when that class is at the
top level of the file. An anonymous class in a method follows the class around it, and a class
declared inside an `if` or a function is checked. A name that starts with an underscore is still
reported in these classes.

**Compared with Coder:** core's config turns `PSR2.Classes.PropertyDeclaration.Underscore` off. The
rule still reports a leading underscore under `--core`. The lowerCamelCase check covers it in most
classes, but not in the config entities and plugin annotations above, where Coder reports nothing
under core's config.

## drupal/redundant-use

- **Level:** error
- **Fix:** safe. It removes the import and writes the name with a leading backslash, such as
  `\Exception`, at every reference.
- **Ports:** `Drupal.Classes.UseGlobalClass.RedundantUseStatement`

A `use` statement that imports a class from the global namespace. The fix reads the references from
the resolved names. There is no fix while a docblock in the file names the class in the same case
without a leading backslash, in a type, an annotation or prose. `drupal/doc-type-namespace` fixes
the types.

**Compared with Coder:** the rule skips `use const` and grouped imports such as `use Foo\{Bar, Baz};`.
Coder reports both, and phpcbf breaks the code there: it writes `\const` for a constant import and
the group prefix for a grouped name.

## drupal/use-leading-backslash

- **Level:** error
- **Fix:** safe. It removes the backslash.
- **Ports:** `SlevomatCodingStandard.Namespaces.UseDoesNotStartWithBackslash.UseStartsWithBackslash`

An import whose first name starts with a backslash, as in `use \Drupal\node\Entity\Node;`. A
`use` statement always names a class, function or constant from the root, so the backslash adds
nothing. The rule reads the name after `use`, `use function` or `use const`, or the prefix of a
group, and a comment before the name does not hide it.

The rule checks only the first name of a statement, as Coder does, so `use A, \B;` is not
reported. `mago format` writes each name on its own `use` statement, and then every name is
checked.

**Compared with Coder:** Coder reads `function` and `const` only in lower case, so it does not
report `use FUNCTION \foo;`. The rule reads the keyword in any case, as PHP does.
