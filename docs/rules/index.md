# Linter rules

Every linter rule of the extension, grouped by what it checks. Each group has a page, and each rule
has an entry there. An entry starts with the rule's level, what its fix does, either the section of
[Drupal's coding standards](https://project.pages.drupalcode.org/coding_standards/) checked by the
rule or, for a check not covered by the standards, where the check comes from, and then the checks
ported by the rule, from Coder, core's own phpcs or PHPStan config, or phpstan-drupal. Then come
what the rule reports and, where it skips cases, what it leaves alone.

Coder 9 is the parity target. Where the written standards ask for less than Coder, the rules
follow Coder. Where they ask for more, some rules follow the standards, and their entries say so.
The collapsed "Compared with Coder" note under an entry lists where the rule and Coder differ.

Every code starts with `drupal/`. The codes are stable, since projects write them into baselines
and `@mago-expect` comments. See [Development](../development.md#rule-codes).

<!-- docs-gen:rules -->

## Bugs and security

Rules for code that runs input it shouldn't trust, or reads the wrong value.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/insecure-unserialize`](bugs-and-security.md#drupalinsecure-unserialize) | error | Reports unserialize() calls that don't limit the allowed classes. |
| [`drupal/preg-security`](bugs-and-security.md#drupalpreg-security) | error | Reports a preg pattern with the `e` modifier. The modifier evaluates the replacement as PHP. |
| [`drupal/remote-address`](bugs-and-security.md#drupalremote-address) | error | Reports a read of $_SERVER['REMOTE_ADDR']. The read ignores Drupal's reverse-proxy settings. |
| [`drupal/weak-hash`](bugs-and-security.md#drupalweak-hash) | warning | Reports an md5(), sha1() or crc32() call, and a hash() call that uses one of those algorithms. |

## Using the right API

Rules for calls that have a Drupal API in their place, and for the strings read by Drupal's tools
from the source.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/deprecation-message`](right-api.md#drupaldeprecation-message) | warning | Checks that E_USER_DEPRECATED messages follow the documented deprecation grammar. |
| [`drupal/global-function`](right-api.md#drupalglobal-function) | warning | Reports procedural Drupal functions called from inside a class. |
| [`drupal/translatable-literal`](right-api.md#drupaltranslatable-literal) | warning | Reports a translatable string that isn't a literal, a first-class callable such as t(...), and a string joined after the call. |
| [`drupal/translatable-string`](right-api.md#drupaltranslatable-string) | warning | Reports a translatable string built with ".", an empty or padded one, a call with no string, and an escaped quote. |
| [`drupal/translated-exception`](right-api.md#drupaltranslated-exception) | warning | Reports an exception message passed through t(). |
| [`drupal/unsilenced-deprecation`](right-api.md#drupalunsilenced-deprecation) | error | Reports a trigger_error() deprecation notice that doesn't start with "@". |

## Procedural files

Rules for the procedural files of a module. They take the module name from the file name, the part
before the first dot. All of them report only in `.module` and `.install` files, except
`drupal/global-variable`, which checks every file.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/const-prefix`](procedural-files.md#drupalconst-prefix) | warning | Reports const constants that don't start with the module name. |
| [`drupal/constant-prefix`](procedural-files.md#drupalconstant-prefix) | warning | Reports define() constants that don't start with the module name. |
| [`drupal/empty-install-hook`](procedural-files.md#drupalempty-install-hook) | error | Reports hook_install() and hook_uninstall() implementations with an empty body. |
| [`drupal/function-prefix`](procedural-files.md#drupalfunction-prefix) | error | Reports functions in a .module file whose name doesn't start with the module name. |
| [`drupal/global-variable`](procedural-files.md#drupalglobal-variable) | error | Reports global variables that don't start with an underscore. |
| [`drupal/install-hook-location`](procedural-files.md#drupalinstall-hook-location) | error | Reports install-time hooks declared in a .module file instead of a .install file. |
| [`drupal/t-in-hook-schema`](procedural-files.md#drupalt-in-hook-schema) | error | Reports a t() call inside hook_schema(). Drupal never shows those strings to users. |

## Files and PHP tags

Rules for the bytes of a file and for its open and close tags.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/byte-order-mark`](files-and-tags.md#drupalbyte-order-mark) | error | Reports a UTF-8 or UTF-16 byte order mark at the start of a file. |
| [`drupal/empty-php-tags`](files-and-tags.md#drupalempty-php-tags) | warning | Reports an open tag that a close tag follows with only whitespace between. |
| [`drupal/file-encoding`](files-and-tags.md#drupalfile-encoding) | warning | Reports a file whose bytes aren't valid UTF-8. |
| [`drupal/file-start-whitespace`](files-and-tags.md#drupalfile-start-whitespace) | error | Reports whitespace before the first PHP open tag of a file. |
| [`drupal/short-echo-tag`](files-and-tags.md#drupalshort-echo-tag) | error | Reports the <?= echo tag. Drupal writes <?php echo. |
| [`drupal/tab-character`](files-and-tags.md#drupaltab-character) | error | Reports a tab in a comment or in the HTML outside the PHP tags. |

## Info and routing files

Rules for the `.info.yml` file of a module, theme or profile and for `.routing.yml` files. They need
`yml` in Mago's extensions, as [Install](../setup/install.md) shows. Mago reads a YAML file as text
outside PHP tags, and these rules read the YAML in it.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/info-auto-added-keys`](info-and-routing-files.md#drupalinfo-auto-added-keys) | warning | Reports the keys of an .info.yml file that drupal.org packaging adds. |
| [`drupal/info-core-version-requirement`](info-and-routing-files.md#drupalinfo-core-version-requirement) | warning | Reports an .info.yml file without a core_version_requirement key. |
| [`drupal/info-dependencies-array`](info-and-routing-files.md#drupalinfo-dependencies-array) | error | Reports a dependencies key of an .info.yml file that isn't a list. |
| [`drupal/info-description`](info-and-routing-files.md#drupalinfo-description) | warning | Reports an .info.yml file with no description or an empty one. |
| [`drupal/info-namespaced-dependency`](info-and-routing-files.md#drupalinfo-namespaced-dependency) | warning | Reports a dependency in an .info.yml file without its project name. |
| [`drupal/routing-access`](info-and-routing-files.md#drupalrouting-access) | warning | Reports an open route without a comment, and the "access administration pages" permission. |

## Naming and imports

Rules for the names of classes, constants, functions, methods and properties, and for `use`
statements. Mago's own naming rules cover the rest, see
[Configuration](../setup/configuration.md).

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/class-name-acronym`](naming-and-imports.md#drupalclass-name-acronym) | error | Reports class-like names that start with three capitals and have no lower-case letter, such as HTTP. |
| [`drupal/define-name`](naming-and-imports.md#drupaldefine-name) | error | Reports define() constants whose name isn't upper case. |
| [`drupal/enum-case-name`](naming-and-imports.md#drupalenum-case-name) | error | Reports enum cases that don't use UpperCamelCase. |
| [`drupal/fully-qualified-name`](naming-and-imports.md#drupalfully-qualified-name) | error | Reports namespaced classes referenced in full instead of through a use statement. |
| [`drupal/function-name`](naming-and-imports.md#drupalfunction-name) | error | Reports functions whose name isn't lower case. |
| [`drupal/global-class-backslash`](naming-and-imports.md#drupalglobal-class-backslash) | error | Reports a global class written without a leading backslash in a file with no namespace. |
| [`drupal/hook-attribute-name`](naming-and-imports.md#drupalhook-attribute-name) | warning | Reports Hook attributes whose name starts with hook_. |
| [`drupal/literal-constant-case`](naming-and-imports.md#drupalliteral-constant-case) | error | Reports a fully qualified \true, \false or \null that is not written in upper case. |
| [`drupal/method-name-underscore`](naming-and-imports.md#drupalmethod-name-underscore) | warning | Reports method names that start with an underscore, other than PHP magic methods. |
| [`drupal/property-name`](naming-and-imports.md#drupalproperty-name) | error | Reports class properties that don't use lowerCamelCase. |
| [`drupal/redundant-use`](naming-and-imports.md#drupalredundant-use) | error | Reports a use statement that imports a class from the global namespace. |
| [`drupal/test-class-suffix`](naming-and-imports.md#drupaltest-class-suffix) | error | Reports a test class whose name doesn't end in Test, which PHPUnit never runs. |
| [`drupal/use-leading-backslash`](naming-and-imports.md#drupaluse-leading-backslash) | error | Reports a use statement whose first name starts with a backslash. |

## Statements and declarations

Rules for switch statements, ternaries and returns, for variables that are written and never read,
and for how methods, properties and parameters are declared.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/case-break-blank-line`](statements.md#drupalcase-break-blank-line) | error | Checks that one blank line follows the statement that ends a switch case. |
| [`drupal/case-fall-through`](statements.md#drupalcase-fall-through) | error | Reports a non-empty switch case that falls through to the next case with no comment. |
| [`drupal/case-label-line`](statements.md#drupalcase-label-line) | error | Reports a comment before a case or default label on the label's line. |
| [`drupal/case-semicolon`](statements.md#drupalcase-semicolon) | error | Reports a case or default label that ends with a semicolon instead of a colon. |
| [`drupal/comment-in-expression`](statements.md#drupalcomment-in-expression) | error | Reports a comment right after a cast, or between `yield` and `from`. |
| [`drupal/else-if`](statements.md#drupalelse-if) | error | Reports "else if" where Drupal writes "elseif". |
| [`drupal/empty-switch`](statements.md#drupalempty-switch) | error | Reports a switch statement that has no case label. |
| [`drupal/function-blank-line`](statements.md#drupalfunction-blank-line) | error | Checks that a blank line separates a function declaration from the code or comment before it. |
| [`drupal/method-visibility`](statements.md#drupalmethod-visibility) | error | Reports methods declared without an explicit visibility keyword. |
| [`drupal/non-capturing-catch`](statements.md#drupalnon-capturing-catch) | error | Reports a catch variable that nothing reads, which the clause can leave out from PHP 8.0. |
| [`drupal/null-coalesce`](statements.md#drupalnull-coalesce) | error | Reports isset() and strict null checks in a ternary that "??" replaces. |
| [`drupal/parameter-blank-line`](statements.md#drupalparameter-blank-line) | error | Reports a blank line in a multi-line function declaration. |
| [`drupal/property-per-statement`](statements.md#drupalproperty-per-statement) | error | Reports a statement that declares more than one property. |
| [`drupal/property-visibility`](statements.md#drupalproperty-visibility) | error | Reports properties declared with var or without an explicit visibility keyword. |
| [`drupal/redundant-return`](statements.md#drupalredundant-return) | warning | Reports a `return;` that is the last statement of a function body. |
| [`drupal/short-ternary`](statements.md#drupalshort-ternary) | error | Reports a ternary that repeats its condition, which "?:" replaces. |
| [`drupal/short-list`](statements.md#drupalshort-list) | error | Reports a list(...) destructuring that can be written as [...]. |
| [`drupal/unused-variable`](statements.md#drupalunused-variable) | warning | Reports a variable that is written and never read in its function, closure or file. |

## Comment text

These rules read only the text of a comment, and don't compare it against the declaration that it
documents. They cover the part of `Drupal.Commenting.*` that works that way, the `@author` ban and
the line-length check for comments. For the comment whitespace set by `mago format`, see
[Comment whitespace and the formatter](../coder/index.md#comment-whitespace-and-the-formatter).

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/author-tag`](comment-text.md#drupalauthor-tag) | warning | Reports @author tags in docblocks. |
| [`drupal/comment-line-length`](comment-text.md#drupalcomment-line-length) | warning | Reports comment lines longer than 80 characters. |
| [`drupal/doc-comment-array-syntax`](comment-text.md#drupaldoc-comment-array-syntax) | warning | Reports `array()` syntax inside a docblock @code example. |
| [`drupal/doc-type-namespace`](comment-text.md#drupaldoc-type-namespace) | warning | Reports a docblock tag, such as @param or @see, that names a class imported only for docblocks by its short name. |
| [`drupal/expected-exception-tag`](comment-text.md#drupalexpected-exception-tag) | warning | Reports the legacy PHPUnit @expectedException* docblock tags. |
| [`drupal/gender-neutral-comment`](comment-text.md#drupalgender-neutral-comment) | warning | Reports gendered pronouns in comments. |
| [`drupal/inline-comment`](comment-text.md#drupalinline-comment) | warning | Checks that a `//` comment has one space after `//`, starts with a capital letter, and doesn't use `#`, and that no docblock sits inside code. |
| [`drupal/inline-comment-blank-line`](comment-text.md#drupalinline-comment-blank-line) | warning | Checks that no blank line follows a `//` comment on its own line. |
| [`drupal/inline-comment-punctuation`](comment-text.md#drupalinline-comment-punctuation) | warning | Checks that a `//` comment ends with terminal punctuation. |
| [`drupal/long-description-punctuation`](comment-text.md#drupallong-description-punctuation) | warning | Checks that a docblock's long description doesn't end with a letter. |
| [`drupal/post-statement-comment`](comment-text.md#drupalpost-statement-comment) | warning | Reports a `//` comment on the same line as the statement before it, and a comment after the opening brace of a class. |
| [`drupal/todo-comment`](comment-text.md#drupaltodo-comment) | warning | Reports a to-do comment that doesn't follow the "@todo Fix problem X here." format. |

## Docblock structure

These rules check the docblocks asked for in Drupal's
[API documentation and comment standards](https://project.pages.drupalcode.org/coding_standards/php/documentation/):
a docblock on every file, class, function and property, its summary and description, and its tags.
They don't check the types in a docblock against the real signature, which is done by
`mago analyze`, see
[Docblock types and the analyzer](../coder/index.md#docblock-types-and-the-analyzer).

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/class-comment`](docblock-structure.md#drupalclass-comment) | error | Checks the comment style above a class, interface, trait or enum, the blank line below its docblock, and a summary that repeats the name. |
| [`drupal/deprecated-tag`](docblock-structure.md#drupaldeprecated-tag) | warning | Checks the wording of a @deprecated docblock tag and the @see tag that must follow it. |
| [`drupal/doc-comment`](docblock-structure.md#drupaldoc-comment) | error | Checks a docblock's opener, closer, blank lines and spaces, and @inheritdoc without braces. |
| [`drupal/doc-see-tag`](docblock-structure.md#drupaldoc-see-tag) | error | Reports a @see tag with no reference, or with text or punctuation after the reference. |
| [`drupal/doc-sentence`](docblock-structure.md#drupaldoc-sentence) | error | Checks the first letter and the end of a docblock summary and of its @param and @throws descriptions, the first letter of the long description, and a summary on one line. |
| [`drupal/doc-tag-descriptions`](docblock-structure.md#drupaldoc-tag-descriptions) | error | Reports a @param or @return with no description, a @param that repeats one or is out of order, and a method parameter with no @param when others have one. |
| [`drupal/doc-tag-groups`](docblock-structure.md#drupaldoc-tag-groups) | error | Checks that the tags of one kind are in one group, @param first, and the blank lines around the groups. |
| [`drupal/doc-tag-layout`](docblock-structure.md#drupaldoc-tag-layout) | error | Checks where the description of a @param, @return or @throws goes, its indent, and the spacing of the tag. |
| [`drupal/doc-tag-types`](docblock-structure.md#drupaldoc-tag-types) | error | Reports a @param with no type or variable name, a @return with no type, and a second @return. |
| [`drupal/doc-type-name`](docblock-structure.md#drupaldoc-type-name) | error | Reports a @param, @return or @var type spelled another way than the standard asks, such as integer for int. |
| [`drupal/file-comment`](docblock-structure.md#drupalfile-comment) | error | Checks the style, the @file tag and the blank line below the docblock that starts a PHP file, and that a namespaced class file doesn't start with a comment. |
| [`drupal/function-comment`](docblock-structure.md#drupalfunction-comment) | error | Checks the comment style above a function or method, and that no blank line follows its docblock. |
| [`drupal/hook-comment`](docblock-structure.md#drupalhook-comment) | warning | Checks the "Implements hook_x()." docblock convention on a hook implementation. |
| [`drupal/inline-variable-comment`](docblock-structure.md#drupalinline-variable-comment) | warning | Checks the style and word order of an inline @var type declaration. |
| [`drupal/missing-class-comment`](docblock-structure.md#drupalmissing-class-comment) | error | Reports a class, interface, trait or enum with no docblock. |
| [`drupal/missing-file-comment`](docblock-structure.md#drupalmissing-file-comment) | error | Reports a PHP file that isn't a class file and has no comment at its start. |
| [`drupal/missing-function-comment`](docblock-structure.md#drupalmissing-function-comment) | error | Reports a function or method with no docblock, a constructor excepted. |
| [`drupal/missing-property-comment`](docblock-structure.md#drupalmissing-property-comment) | error | Reports a class property with no docblock. |
| [`drupal/missing-summary`](docblock-structure.md#drupalmissing-summary) | error | Reports a docblock with tags and no short description. |
| [`drupal/variable-comment`](docblock-structure.md#drupalvariable-comment) | error | Checks the comment style above a class property and the @var tag of its docblock. |

## DrupalPractice checks

These rules port sniffs of Coder's `DrupalPractice` standard not run by core's `phpcs.xml.dist`, so
the worker's [`--core` argument](../setup/worker-arguments.md#checking-drupal-core) turns them off.

| Rule | Level | What it reports |
| --- | --- | --- |
| [`drupal/class-prefix`](drupal-practice.md#drupalclass-prefix) | warning | Reports a class or interface in the global namespace that doesn't start with the module name. |
| [`drupal/curl-ssl-verify`](drupal-practice.md#drupalcurl-ssl-verify) | warning | Reports curl_setopt() calls that turn off CURLOPT_SSL_VERIFYPEER. |
| [`drupal/form-alter-comment`](drupal-practice.md#drupalform-alter-comment) | warning | Reports a function documented as hook_form_alter() that isn't named after the module. |
| [`drupal/global-constant`](drupal-practice.md#drupalglobal-constant) | warning | Reports a top-level const statement or define() call, as DrupalPractice does. |
| [`drupal/request-superglobal`](drupal-practice.md#drupalrequest-superglobal) | error | Reports a use of $_GET, $_POST, $_COOKIE or $_FILES. The request stack gives the same data. |
| [`drupal/strict-config-schema`](drupal-practice.md#drupalstrict-config-schema) | error | Reports a test class that turns off strict config schema checking. |
| [`drupal/untranslated-options`](drupal-practice.md#drupaluntranslated-options) | warning | Reports a plain string in the #options of a checkboxes, radios, select or tableselect element. |

## Drupal 7 era

These rules check APIs removed in Drupal 8, so they find nothing in a modern codebase. Core's
`phpcs.xml.dist` enables the matching sniffs and Coder 9 ships them, so without these rules a part
of the standard would go unchecked.

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
| [`drupal/discouraged-function`](phpstan-drupal.md#drupaldiscouraged-function) | error | Reports calls to the devel dump helpers and to fnmatch(). Some PHP builds don't have fnmatch(). |
| [`drupal/render-callback`](phpstan-drupal.md#drupalrender-callback) | error | Reports a render array callback that isn't a closure, a service:method string or a class method. |
| [`drupal/symfony-yaml-parse`](phpstan-drupal.md#drupalsymfony-yaml-parse) | warning | Reports a Symfony\Component\Yaml\Yaml::parse() call. It bypasses Drupal\Component\Serialization\Yaml::decode(). |
| [`drupal/todo-current-issue`](phpstan-drupal.md#drupaltodo-current-issue) | error | Reports a @todo comment that links the issue of the current GitLab merge request. |

<!-- /docs-gen -->
