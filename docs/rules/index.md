# Rules

Every linter rule of the extension, in groups by what it checks. Each rule's entry gives its level,
what its fix does, and the Coder checks that it ports.

Every code starts with `drupal/`. The codes are stable, see [Development](../development.md#rule-codes).

<!-- docs-gen:rules -->

## Bugs and security

Rules for code that runs input it should not trust, or reads the wrong value.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/insecure-unserialize`](bugs-and-security.md#drupalinsecure-unserialize) | error | Reports unserialize() calls that do not limit the allowed classes. |
| [`drupal/preg-security`](bugs-and-security.md#drupalpreg-security) | error | Reports a preg pattern with the `e` modifier. The modifier evaluates the replacement as PHP. |
| [`drupal/remote-address`](bugs-and-security.md#drupalremote-address) | error | Reports a read of $_SERVER['REMOTE_ADDR']. The read ignores Drupal's reverse-proxy settings. |
| [`drupal/weak-hash`](bugs-and-security.md#drupalweak-hash) | warning | Reports an md5(), sha1() or crc32() call, and a hash() call that uses one of those algorithms. |

## Using the right API

Rules for calls that have a Drupal API in their place, and for the strings that Drupal's tools read
from the source.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/deprecation-message`](right-api.md#drupaldeprecation-message) | warning | Checks that E_USER_DEPRECATED messages follow the documented deprecation grammar. |
| [`drupal/global-function`](right-api.md#drupalglobal-function) | warning | Reports procedural Drupal functions called from inside a class. |
| [`drupal/translatable-string`](right-api.md#drupaltranslatable-string) | warning | Checks that a translatable string is one literal without concatenation or padding. |
| [`drupal/translated-exception`](right-api.md#drupaltranslated-exception) | warning | Reports an exception message passed through t(). |
| [`drupal/unsilenced-deprecation`](right-api.md#drupalunsilenced-deprecation) | error | Reports a trigger_error() deprecation notice that does not start with "@". |

## Procedural files

These rules report only in `.module` and `.install` files. They take the module name from the file
name, the part before the first dot.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/const-prefix`](procedural-files.md#drupalconst-prefix) | warning | Reports const constants that do not start with the module name. |
| [`drupal/constant-prefix`](procedural-files.md#drupalconstant-prefix) | warning | Reports define() constants that do not start with the module name. |
| [`drupal/empty-install-hook`](procedural-files.md#drupalempty-install-hook) | error | Reports hook_install() and hook_uninstall() implementations with an empty body. |
| [`drupal/function-prefix`](procedural-files.md#drupalfunction-prefix) | error | Reports functions in a .module file whose name does not start with the module name. |
| [`drupal/global-variable`](procedural-files.md#drupalglobal-variable) | error | Reports module globals that do not start with an underscore and the module's name. |
| [`drupal/install-hook-location`](procedural-files.md#drupalinstall-hook-location) | error | Reports install-time hooks declared in a .module file instead of a .install file. |
| [`drupal/t-in-hook-schema`](procedural-files.md#drupalt-in-hook-schema) | error | Reports a t() call inside hook_schema(). Drupal never shows those strings to users. |

## Files and PHP tags

Rules for the bytes of a file and for its open and close tags.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/byte-order-mark`](files-and-tags.md#drupalbyte-order-mark) | error | Reports a UTF-8 or UTF-16 byte order mark at the start of a file. |
| [`drupal/empty-php-tags`](files-and-tags.md#drupalempty-php-tags) | warning | Reports an open tag that a close tag follows with only whitespace between. |
| [`drupal/file-encoding`](files-and-tags.md#drupalfile-encoding) | warning | Reports a file whose bytes are not valid UTF-8. |
| [`drupal/file-start-whitespace`](files-and-tags.md#drupalfile-start-whitespace) | error | Reports whitespace before the first PHP open tag of a file. |
| [`drupal/short-echo-tag`](files-and-tags.md#drupalshort-echo-tag) | error | Reports the <?= echo tag. Drupal writes <?php echo. |

## Naming and imports

Rules for the names of classes, constants, methods and properties, and for `use` statements. Mago's
own naming rules cover the rest, see [Setup](../setup.md#configure-mago-for-drupal).

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/class-name-acronym`](naming-and-imports.md#drupalclass-name-acronym) | error | Reports class-like names that start with three capitals and have no lower-case letter, such as HTTP. |
| [`drupal/define-name`](naming-and-imports.md#drupaldefine-name) | error | Reports define() constants whose name is not upper case. |
| [`drupal/enum-case-name`](naming-and-imports.md#drupalenum-case-name) | error | Reports enum cases that do not use UpperCamelCase. |
| [`drupal/fully-qualified-name`](naming-and-imports.md#drupalfully-qualified-name) | error | Reports namespaced classes referenced in full instead of through a use statement. |
| [`drupal/hook-attribute-name`](naming-and-imports.md#drupalhook-attribute-name) | warning | Reports Hook attributes whose name starts with hook_. |
| [`drupal/method-name-underscore`](naming-and-imports.md#drupalmethod-name-underscore) | warning | Reports method names that start with an underscore, other than PHP magic methods. |
| [`drupal/property-name`](naming-and-imports.md#drupalproperty-name) | error | Reports class properties that do not use lowerCamelCase. |
| [`drupal/redundant-use`](naming-and-imports.md#drupalredundant-use) | error | Reports a use statement that imports a class from the global namespace. |
| [`drupal/use-leading-backslash`](naming-and-imports.md#drupaluse-leading-backslash) | error | Reports a use statement whose first name starts with a backslash. |

## Statements and declarations

Rules for switch statements, ternaries and returns, and for how methods, properties and parameters
are declared.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/case-break-blank-line`](statements.md#drupalcase-break-blank-line) | error | Checks that one blank line follows the statement that ends a switch case. |
| [`drupal/case-fall-through`](statements.md#drupalcase-fall-through) | error | Reports a non-empty switch case that falls through to the next case with no comment. |
| [`drupal/case-semicolon`](statements.md#drupalcase-semicolon) | error | Reports a case or default label that ends with a semicolon instead of a colon. |
| [`drupal/comment-in-expression`](statements.md#drupalcomment-in-expression) | error | Reports a comment right after a cast, or between `yield` and `from`. |
| [`drupal/else-if`](statements.md#drupalelse-if) | error | Reports "else if" where Drupal writes "elseif". |
| [`drupal/empty-switch`](statements.md#drupalempty-switch) | error | Reports a switch statement that has no case label. |
| [`drupal/method-visibility`](statements.md#drupalmethod-visibility) | error | Reports methods declared without an explicit visibility keyword. |
| [`drupal/null-coalesce`](statements.md#drupalnull-coalesce) | error | Reports isset() and strict null checks in a ternary that "??" replaces. |
| [`drupal/parameter-blank-line`](statements.md#drupalparameter-blank-line) | error | Reports a blank line in a multi-line function declaration. |
| [`drupal/property-per-statement`](statements.md#drupalproperty-per-statement) | error | Reports a statement that declares more than one property. |
| [`drupal/property-visibility`](statements.md#drupalproperty-visibility) | error | Reports properties declared with var or without an explicit visibility keyword. |
| [`drupal/redundant-return`](statements.md#drupalredundant-return) | warning | Reports a `return;` that is the last statement of a function body. |
| [`drupal/short-list`](statements.md#drupalshort-list) | error | Reports a list(...) destructuring that can be written as [...]. |

## Comment text

These rules read only a comment's text. They do not compare it against the declaration that it
documents. The group has the part of `Drupal.Commenting.*` that works that way, the `@author` ban,
and the line-length check for comments. [Comment whitespace and the
formatter](../coder/index.md#comment-whitespace-and-the-formatter) says which comment whitespace
`mago format` sets.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/author-tag`](comment-text.md#drupalauthor-tag) | warning | Reports @author tags in docblocks. |
| [`drupal/comment-line-length`](comment-text.md#drupalcomment-line-length) | warning | Reports comment lines longer than 80 characters. |
| [`drupal/doc-comment-array-syntax`](comment-text.md#drupaldoc-comment-array-syntax) | warning | Reports `array()` syntax inside a docblock @code example. |
| [`drupal/doc-type-namespace`](comment-text.md#drupaldoc-type-namespace) | warning | Reports @param, @return, @var and @throws types written as the short name of a class imported only for docblocks. |
| [`drupal/expected-exception-tag`](comment-text.md#drupalexpected-exception-tag) | warning | Reports the legacy PHPUnit @expectedException* docblock tags. |
| [`drupal/gender-neutral-comment`](comment-text.md#drupalgender-neutral-comment) | warning | Reports gendered pronouns in comments. |
| [`drupal/inline-comment`](comment-text.md#drupalinline-comment) | warning | Checks that a `//` comment has one space after `//`, starts with a capital letter, and does not use `#`, and that no docblock sits inside code. |
| [`drupal/inline-comment-blank-line`](comment-text.md#drupalinline-comment-blank-line) | warning | Checks that no blank line follows a `//` comment on its own line. |
| [`drupal/inline-comment-punctuation`](comment-text.md#drupalinline-comment-punctuation) | warning | Checks that a `//` comment ends with terminal punctuation. |
| [`drupal/long-description-punctuation`](comment-text.md#drupallong-description-punctuation) | warning | Checks that a docblock's long description does not end with a letter. |
| [`drupal/post-statement-comment`](comment-text.md#drupalpost-statement-comment) | warning | Reports a `//` comment on the same line as the statement before it. |
| [`drupal/todo-comment`](comment-text.md#drupaltodo-comment) | warning | Reports a to-do comment that does not follow the "@todo Fix problem X here." format. |

## Docblock structure

These rules read a docblock's summary, description and tag list. They do not need the declaration's
real signature, which `mago analyze` checks, see [Docblock types and the
analyzer](../coder/index.md#docblock-types-and-the-analyzer). The group has the rest of
`Drupal.Commenting.*`.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/class-comment`](docblock-structure.md#drupalclass-comment) | error | Checks that a class, interface, trait or enum has a docblock. |
| [`drupal/deprecated-tag`](docblock-structure.md#drupaldeprecated-tag) | warning | Checks the wording of a @deprecated docblock tag and the @see tag that must follow it. |
| [`drupal/doc-comment`](docblock-structure.md#drupaldoc-comment) | warning | Checks a docblock's short description, long description and tag order. |
| [`drupal/file-comment`](docblock-structure.md#drupalfile-comment) | error | Checks that a procedural file starts with a docblock tagged @file. |
| [`drupal/function-comment`](docblock-structure.md#drupalfunction-comment) | error | Checks that a function or method has a well-formed docblock. |
| [`drupal/hook-comment`](docblock-structure.md#drupalhook-comment) | warning | Checks the "Implements hook_x()." docblock convention on a hook implementation. |
| [`drupal/inline-variable-comment`](docblock-structure.md#drupalinline-variable-comment) | warning | Checks the style and word order of an inline @var type declaration. |
| [`drupal/variable-comment`](docblock-structure.md#drupalvariable-comment) | error | Checks that a class property has a @var docblock. |

## Docblock types

This rule compares a docblock with the declaration's signature. Coder has no matching sniff.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/nullable-param-tag`](docblock-types.md#drupalnullable-param-tag) | warning | Reports an @param type without null on an untyped parameter that defaults to NULL. |

## DrupalPractice checks

These rules port sniffs of Coder's `DrupalPractice` standard that core's `phpcs.xml.dist` does not
run, so the worker's [`--core` argument](../setup.md#checking-drupal-core) turns them off.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/class-prefix`](drupal-practice.md#drupalclass-prefix) | warning | Reports a class or interface in the global namespace that does not start with the module name. |
| [`drupal/curl-ssl-verify`](drupal-practice.md#drupalcurl-ssl-verify) | warning | Reports curl_setopt() calls that turn off CURLOPT_SSL_VERIFYPEER. |
| [`drupal/form-alter-comment`](drupal-practice.md#drupalform-alter-comment) | warning | Reports a function documented as hook_form_alter() that is not named after the module. |
| [`drupal/global-constant`](drupal-practice.md#drupalglobal-constant) | warning | Reports a top-level const statement or define() call. A constant belongs in a class or interface. |
| [`drupal/request-superglobal`](drupal-practice.md#drupalrequest-superglobal) | error | Reports a use of $_GET, $_POST, $_COOKIE or $_FILES. The request stack gives the same data. |
| [`drupal/strict-config-schema`](drupal-practice.md#drupalstrict-config-schema) | error | Reports a test class that turns off strict config schema checking. |
| [`drupal/untranslated-options`](drupal-practice.md#drupaluntranslated-options) | warning | Reports a plain string in the #options of a checkboxes, radios, select or tableselect element. |

## Drupal 7 era

These rules target APIs that Drupal 8 removed, so they do not report on a modern codebase. Core's
`phpcs.xml.dist` still enables the matching sniffs, and Coder 9 still ships them. Without these
rules, a part of the standard is not checked.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/link-text-translatable`](drupal-7.md#drupallink-text-translatable) | error | Reports literal link text passed to l() without a t() wrapper. |
| [`drupal/t-in-hook-menu`](drupal-7.md#drupalt-in-hook-menu) | error | Reports a t() call inside hook_menu(). Drupal translates the strings when it renders them. |
| [`drupal/watchdog-message`](drupal-7.md#drupalwatchdog-message) | error | Reports a watchdog() message wrapped in t() or built by concatenation. |

## Ported from phpstan-drupal

These rules port checks of [phpstan-drupal](https://github.com/mglaman/phpstan-drupal) that need
only the syntax.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/discouraged-function`](phpstan-drupal.md#drupaldiscouraged-function) | error | Reports calls to the devel dump helpers and to fnmatch(). Some PHP builds do not have fnmatch(). |
| [`drupal/render-callback`](phpstan-drupal.md#drupalrender-callback) | error | Reports a render array callback that is not a closure, a service:method string or a class method. |
| [`drupal/symfony-yaml-parse`](phpstan-drupal.md#drupalsymfony-yaml-parse) | warning | Reports a Symfony\Component\Yaml\Yaml::parse() call. It bypasses Drupal\Component\Serialization\Yaml::decode(). |

<!-- /docs-gen -->
