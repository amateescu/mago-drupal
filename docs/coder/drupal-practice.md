# DrupalPractice standard

Core's `phpcs.xml.dist` runs only `ExpectedException`, `ExceptionT`, `GlobalFunction`,
`NamespacedDependency` and part of `VariableAnalysis` from this standard. The worker's
[`--core` argument](../setup/worker-arguments.md#checking-drupal-core) turns off the linter rules
that port the other sniffs. The analyzer check `drupal/global-drupal-call`, which ports
`GlobalDrupal` in part, stays on.

<!-- docs-gen:coder-practice -->

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `DrupalPractice.Commenting.AuthorTag` | [`drupal/author-tag`](../rules/comment-text.md#drupalauthor-tag) |  |
| `DrupalPractice.Commenting.CommentEmptyLine` | [`drupal/inline-comment-blank-line`](../rules/comment-text.md#drupalinline-comment-blank-line), `mago format`, partly |  |
| `DrupalPractice.Commenting.ExpectedException` | [`drupal/expected-exception-tag`](../rules/comment-text.md#drupalexpected-exception-tag) |  |
| `DrupalPractice.Constants.GlobalConstant` | [`drupal/global-constant`](../rules/drupal-practice.md#drupalglobal-constant) |  |
| `DrupalPractice.Constants.GlobalDefine` | [`drupal/global-constant`](../rules/drupal-practice.md#drupalglobal-constant) |  |
| `DrupalPractice.FunctionCalls.CurlSslVerifier` | [`drupal/curl-ssl-verify`](../rules/drupal-practice.md#drupalcurl-ssl-verify) |  |
| `DrupalPractice.FunctionCalls.InsecureUnserialize` | [`drupal/insecure-unserialize`](../rules/bugs-and-security.md#drupalinsecure-unserialize), partly |  |
| `DrupalPractice.FunctionDefinitions.FormAlterDoc` | [`drupal/form-alter-comment`](../rules/drupal-practice.md#drupalform-alter-comment) |  |
| `DrupalPractice.General.ClassName` | [`drupal/class-prefix`](../rules/drupal-practice.md#drupalclass-prefix) |  |
| `DrupalPractice.General.DescriptionT` | Nothing | A `#description` value that starts with a string literal of more than three characters, not counting tags, as in `'#description' => 'Some text'`. Not ported. |
| `DrupalPractice.General.ExceptionT` | [`drupal/translated-exception`](../rules/right-api.md#drupaltranslated-exception) |  |
| `DrupalPractice.General.OptionsT` | [`drupal/untranslated-options`](../rules/drupal-practice.md#drupaluntranslated-options) |  |
| `DrupalPractice.InfoFiles.CoreVersionRequirement` | [`drupal/info-core-version-requirement`](../rules/info-and-routing-files.md#drupalinfo-core-version-requirement) |  |
| `DrupalPractice.InfoFiles.Description` | [`drupal/info-description`](../rules/info-and-routing-files.md#drupalinfo-description) |  |
| `DrupalPractice.InfoFiles.NamespacedDependency` | [`drupal/info-namespaced-dependency`](../rules/info-and-routing-files.md#drupalinfo-namespaced-dependency) |  |
| `DrupalPractice.Objects.GlobalClass` | Nothing | A static `Node::load()` style call in a class that can have the storage injected. Not ported: it needs the analyzer. |
| `DrupalPractice.Objects.GlobalDrupal` | [`drupal/global-drupal-call`](../analyzer/injection.md#drupalglobal-drupal-call), partly | Coder reports a `\Drupal::` call in a non-static method of a service, of a class that implements `ContainerInjectionInterface`, or of a class that extends one of 12 base classes such as `FormBase`. The analyzer check needs `ContainerInjectionInterface` or `ContainerFactoryPluginInterface` among the class's interfaces, or a `#[Hook]` on the class or one of its methods, so it skips other services and a `BlockBase` plugin without `create()`. It also reports a hook class, which Coder skips. |
| `DrupalPractice.Objects.GlobalFunction` | [`drupal/global-function`](../rules/right-api.md#drupalglobal-function) |  |
| `DrupalPractice.Objects.StrictSchemaDisabled` | [`drupal/strict-config-schema`](../rules/drupal-practice.md#drupalstrict-config-schema) |  |
| `DrupalPractice.Objects.UnusedPrivateMethod` | `mago analyze` (`unused-method`) | The analyzer also reports an unused private static method, an unused private method of an enum, and a private method that is called only from its own body, which Coder skips. Coder reports a private `__destruct()` and a private method that is called only as `$other->helper()`, `self::helper()`, `static::helper()` or `[self::class, 'helper']`; the analyzer reports none of them. |
| `DrupalPractice.Variables.GetRequestData` | [`drupal/request-superglobal`](../rules/drupal-practice.md#drupalrequest-superglobal), Mago `no-request-variable` |  |
| `DrupalPractice.Yaml.RoutingAccess` | [`drupal/routing-access`](../rules/info-and-routing-files.md#drupalrouting-access) |  |
| `VariableAnalysis.CodeAnalysis.VariableAnalysis` | `SelfOutsideClass`: `mago analyze` (`self-outside-class-scope`)<br>`StaticOutsideClass`: `mago analyze` (`static-outside-class-scope`)<br>`UndefinedUnsetVariable`, `VariableRedeclaration`: Nothing<br>`UnusedVariable`: Mago `no-redundant-variable`, Mago `no-unused-closure-capture`, partly | Coder's ruleset skips `*.tpl.php` files. Mago checks them too. `UndefinedUnsetVariable`: An `unset()` of a variable that was never set. Not ported. Core's `phpcs.xml.dist` turns this code off. A read after `unset()` is `UndefinedVariable`, which Coder's ruleset turns off, and `mago analyze` reports it as `possibly-undefined-variable`. `UnusedVariable`: With `no-redundant-variable = { enabled = true }`, and `no-unused-closure-capture = { enabled = true }` for a variable in a closure's `use` list. Variables at file scope are skipped. Mago also reports an unused `catch` variable and an unused `foreach` value, which Coder allows. `VariableRedeclaration`: A variable declared twice, such as a parameter redeclared with `static`. Not ported: it needs flow analysis. |

<!-- /docs-gen -->

## Checks for the Drupal 7 API

These checks look for Drupal 7 functions, hooks and arrays, such as `check_plain()`, `hook_menu()`
or `$form_state['input']`, and Coder runs some of them only on a Drupal 7 module. They aren't
ported. On Drupal 8 and later most of those functions don't exist, and `mago analyze` reports a call
to one as an unknown function. Nothing in Mago reports the `hook_menu()` checks or an `'und'` key.
`mago analyze` reports `$form_state['input']` only when `$form_state` is typed as
`FormStateInterface`, as an array access on an object. The Drupal 7 checks of the `Drupal` standard
are on the [Drupal standard](drupal.md) page, since core's config still runs them.

<!-- docs-gen:coder-drupal7 -->

| Sniff | Handled by |
| --- | --- |
| `DrupalPractice.FunctionCalls.CheckPlain` | Not ported |
| `DrupalPractice.FunctionCalls.DbQuery` | Not ported |
| `DrupalPractice.FunctionCalls.DbSelectBraces` | Not ported |
| `DrupalPractice.FunctionCalls.DefaultValueSanitize` | Not ported |
| `DrupalPractice.FunctionCalls.FormErrorT` | Not ported |
| `DrupalPractice.FunctionCalls.LCheckPlain` | Not ported |
| `DrupalPractice.FunctionCalls.MessageT` | Not ported |
| `DrupalPractice.FunctionCalls.TCheckPlain` | Not ported |
| `DrupalPractice.FunctionCalls.Theme` | Not ported |
| `DrupalPractice.FunctionCalls.VariableSetSanitize` | Not ported |
| `DrupalPractice.FunctionDefinitions.AccessHookMenu` | Not ported |
| `DrupalPractice.FunctionDefinitions.HookInitCss` | Not ported |
| `DrupalPractice.FunctionDefinitions.InstallT` | Not ported |
| `DrupalPractice.General.AccessAdminPages` | Not ported |
| `DrupalPractice.General.FormStateInput` | Not ported |
| `DrupalPractice.General.LanguageNone` | Not ported |
| `DrupalPractice.General.VariableName` | Not ported |

<!-- /docs-gen -->
