# Linter rules

Every rule that the extension registers, in groups by what it checks. The rules in each group are in
alphabetical order.

## Bugs and security

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/insecure-unserialize` | Error | An `unserialize()` call that does not limit `allowed_classes`. The payload then decides which objects get built. The rule skips a payload that the same function built with `serialize()`: `unserialize(serialize($x))`, or a local variable whose every assignment is a `serialize()` call. |
| `drupal/preg-security` | Error | A `preg_*` pattern that uses the `e` modifier. That modifier evaluates the replacement as PHP. |
| `drupal/remote-address` | Error | A read of `$_SERVER['REMOTE_ADDR']`. Such a read ignores the reverse-proxy settings. |
| `drupal/weak-hash` | Warning | A `md5()`, `sha1()` or `crc32()` call, and a `hash()` call with `md5`, `sha1`, `crc32` or `crc32b`. An unsafe fix changes the call to `hash('xxh64', …)`. |

## Using the right API

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/deprecation-message` | Warning | An `E_USER_DEPRECATED` message that breaks the deprecation grammar. The grammar includes the version format and the change-record format. |
| `drupal/global-function` | Warning | A procedural wrapper that is called from inside a class. The most frequent case is `t()` where `$this->t()` applies. |
| `drupal/translatable-string` | Warning | A translatable string that is built by concatenation or interpolation, is padded with whitespace, or is empty. The rule covers `t()`, `formatPlural()` and `new TranslatableMarkup()`. |
| `drupal/translated-exception` | Warning | An exception message that is passed through `t()`. |
| `drupal/unsilenced-deprecation` | Error | A `trigger_error()` deprecation notice without the `@` prefix. Drupal turns an unsilenced notice into a test failure. A fix adds the `@`. |

## Procedural files

These rules report only in `.module` and `.install` files.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/constant-prefix` | Warning | A `define()` constant without the module prefix. |
| `drupal/empty-install-hook` | Error | An empty `hook_install()` or `hook_uninstall()` body. |
| `drupal/global-variable` | Error | A module global without the leading underscore. |
| `drupal/install-hook-location` | Error | A `hook_install()` or other install hook that is declared in `.module` and not in `.install`. |
| `drupal/t-in-hook-schema` | Error | A `t()` call inside `hook_schema()`. |

## Naming, imports and syntax

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/else-if` | Error | An `else if` written as two keywords. Drupal writes `elseif`. A fix joins the keywords. The rule skips a braced `else { if ... }`. |
| `drupal/enum-case-name` | Error | An enum case that is not UpperCamelCase. |
| `drupal/fully-qualified-name` | Error | A namespaced class written out in full where a `use` statement belongs. The rule skips a name with no namespace of its own, such as `\Exception`, and a namespaced function call. The rule skips an `.api.php` file completely. |
| `drupal/method-visibility` | Error | A method declared without `public`, `protected` or `private`. A fix adds `public`. |
| `drupal/property-name` | Error | A class property that is not lowerCamelCase. |
| `drupal/redundant-use` | Error | A `use` statement that imports a class from the global namespace. |
| `drupal/use-leading-backslash` | Error | An import whose class name starts with a backslash. A fix removes the backslash. |

## Comment text

These rules read only a comment's text. They do not compare it against the declaration that it
documents. The group has the part of `Drupal.Commenting.*` that works that way, the `@author` ban,
and the line-length check for comments.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/author-tag` | Warning | An `@author` tag. The tag goes out of date as other people edit the file. |
| `drupal/comment-line-length` | Warning | A comment line that is longer than 80 characters. |
| `drupal/doc-comment-array-syntax` | Warning | The `array()` syntax inside a docblock `@code` example. |
| `drupal/doc-type-namespace` | Warning | A `@param`, `@return`, `@var` or `@throws` type written as an imported short name and not as the fully qualified name. |
| `drupal/expected-exception-tag` | Warning | A legacy PHPUnit `@expectedException*` docblock tag. |
| `drupal/gender-neutral-comment` | Warning | A gendered pronoun in a comment. |
| `drupal/inline-comment` | Warning | A `//` comment that starts with a lowercase letter, has no terminal punctuation, or uses `#` and not `//`. |
| `drupal/post-statement-comment` | Warning | A `//` comment on the same line as the statement before it. |
| `drupal/todo-comment` | Warning | A to-do comment that does not follow the `@todo Fix problem X here.` format. |

Mago's own `tagged-todo` rule must have a `TODO(@user)` or `TODO(#123)` reference. That format is
not compatible with Drupal's `@todo Fix problem X here.` convention, so `tagged-todo` is not a
substitute for `drupal/todo-comment`. Do not run the two rules on the same codebase.

## Docblock structure

These rules read a docblock's summary, description and tag list. They do not need the declaration's
real signature. The group has the rest of `Drupal.Commenting.*`.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/class-comment` | Error | A class, interface, trait or enum with no docblock, with the wrong comment style, or with a summary that only repeats the name. |
| `drupal/deprecated-tag` | Warning | A `@deprecated` tag that breaks the version-and-reason grammar, or that has no `@see` tag after it. |
| `drupal/doc-comment` | Warning | A docblock with no summary, or with `@param` tags that are not in the first group. Also a summary that is not capitalized, that has no punctuation, or that spans more than one line. |
| `drupal/file-comment` | Error | A procedural file that does not start with a docblock that has the `@file` tag. |
| `drupal/function-comment` | Error | A function or method with no docblock or with the wrong comment style. Also a `@param`, `@return`, `@throws` or `@see` tag that is malformed, has no description, or is not capitalized. |
| `drupal/hook-comment` | Warning | A hook implementation that is not documented as `Implements hook_foo().`, or that duplicates the `@param` or `@return` documentation. |
| `drupal/inline-variable-comment` | Warning | An inline `@var` declaration that uses `//` and not `/** */`, or that writes the variable name before the type. |
| `drupal/variable-comment` | Error | A class property with no `@var` docblock, with the wrong comment style, or with more than one `@var` tag. |

## Drupal 7 era

These rules target APIs that Drupal 8 removed, so they do not report on a modern codebase. Core's
`phpcs.xml.dist` still enables the matching sniffs and Coder 9 still ships them. Without these
rules, a part of the standard is not checked.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/link-text-translatable` | Error | Literal link text passed to `l()` without `t()`. |
| `drupal/t-in-hook-menu` | Error | A `t()` call inside `hook_menu()`. |
| `drupal/watchdog-message` | Error | A `watchdog()` message that is wrapped in `t()` or built by concatenation. |

## Parity notes

The two comment groups complete `Drupal.Commenting.*`, with two exceptions. `mago format` already
produces the pure-whitespace sub-codes. The last paragraph of this section describes the
`DocCommentStar` gap.

`Drupal.Commenting.FunctionComment` compares a docblock against a function's real parameter list
and return type. Most of those checks are redundant with `mago analyze`. The analyzer reads
`@param`, `@return`, `@var` and `@throws` as authoritative types when there is no native hint, the
same way phpstan and psalm do. It already reports an `@return void` on a function that returns a
value, a function with no `return` at all, an `@param` that names an unknown parameter, and most
type aliases with the wrong case (`Boolean` does not resolve as a class).

`drupal/function-comment` covers the rest: docblock presence, prose quality,
and one check that depends on the signature. That check reports a method with partial `@param`
coverage that has no entry for a real parameter. The terminal-punctuation check skips a `@param`
description that ends in a `@code` example. The sniff makes the same exemption, because such a
description ends on the example and not on a sentence.

`Drupal.Files.LineLength` is a linter rule here and not a formatter task. The formatter does the
rest of the line-width work. The sniff measures only lines that end in a comment. `mago format`
wraps code but leaves comment text as written, so the formatter cannot produce the sniff's result.
The rule keeps the sniff's exemptions. All of them are for text that cannot be wrapped
without damage: docblock tag lines, `@code` examples, `// @see`-style reference lines, annotation
values, and the `Implements hook_foo()` and `Contains ...` lines. The rule also skips a line whose
last word is too long for a line of its own. That exemption lets a URL or a long class path through.

Two things differ from a phpcs run of the same sniffs. A phpcs suppression comment
(`phpcs:ignore`, `phpcs:disable`, `phpcs:ignoreFile`, `@codingStandardsIgnoreFile`) does not
suppress a Mago issue. A codebase that uses those comments must convert them to
`// @mago-expect lint:<code>` or to a Mago exclude. Core's generated `ProxyClass` files are the most
frequent case. Also, the rule reports a `/* ... */` line that holds the closing `*/`, where phpcs
skips it. Such a line ends on a whitespace token and not on a comment token, so the sniff's own
check never runs on it. Both tools report the lines above the closer.

One `Drupal.Commenting.*` sniff is pure whitespace and is not ported, because `mago format` already
produces its result: `DocCommentAlignment` (star spacing and alignment). `DocCommentStar` adds a
star to a docblock line that has none. `mago format` leaves such a line as it is and does not add
the star. That is a gap in the upstream formatter, and a linter rule cannot fix it. A hand-edited
docblock line without its `*` stays unfixed either way.

## Ported from phpstan-drupal

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/discouraged-function` | Error | A call to a dump helper of the devel module (`dpm()`, `dsm()`, `dpr()`, `kpr()` and the other dump helpers), or a call to `fnmatch()`. Some PHP builds do not have `fnmatch()`. |
| `drupal/symfony-yaml-parse` | Warning | A `Symfony\Component\Yaml\Yaml::parse()` call. It bypasses `\Drupal\Component\Serialization\Yaml::decode()`. |
| `drupal/render-callback` | Error | A `#pre_render`, `#post_render`, `#lazy_builder`, `#access_callback` or date callback that is a plain function name string. Drupal trusts only closures, `service:method` strings and class methods. The rule also reports a value that is not an array literal at all, such as `'#pre_render' => $callbacks`, because nothing can be checked there. The rule skips core's `Renderer` and `PlaceholderGenerator` for `#lazy_builder`, because they pass the key through `array_intersect_key()`. |
