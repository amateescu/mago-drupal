# DrupalPractice standard

Core's `phpcs.xml.dist` runs only `ExpectedException`, `ExceptionT`, `GlobalFunction`,
`NamespacedDependency` and part of `VariableAnalysis` from this standard. The worker's
[`--core` argument](../setup/worker-arguments.md#checking-drupal-core) turns off the rules and the
analyzer checks that port the other sniffs.

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
| `DrupalPractice.FunctionCalls.TCheckPlain` | [`drupal/escaped-placeholder`](../rules/drupal-practice.md#drupalescaped-placeholder) |  |
| `DrupalPractice.FunctionDefinitions.FormAlterDoc` | [`drupal/form-alter-comment`](../rules/drupal-practice.md#drupalform-alter-comment) |  |
| `DrupalPractice.General.ClassName` | [`drupal/class-prefix`](../rules/drupal-practice.md#drupalclass-prefix) |  |
| `DrupalPractice.General.DescriptionT` | [`drupal/untranslated-description`](../rules/drupal-practice.md#drupaluntranslated-description) |  |
| `DrupalPractice.General.ExceptionT` | [`drupal/translated-exception`](../rules/right-api.md#drupaltranslated-exception) |  |
| `DrupalPractice.General.OptionsT` | [`drupal/untranslated-options`](../rules/drupal-practice.md#drupaluntranslated-options) |  |
| `DrupalPractice.InfoFiles.CoreVersionRequirement` | [`drupal/info-core-version-requirement`](../rules/info-and-routing-files.md#drupalinfo-core-version-requirement) |  |
| `DrupalPractice.InfoFiles.Description` | [`drupal/info-description`](../rules/info-and-routing-files.md#drupalinfo-description) |  |
| `DrupalPractice.InfoFiles.NamespacedDependency` | [`drupal/info-namespaced-dependency`](../rules/info-and-routing-files.md#drupalinfo-namespaced-dependency) |  |
| `DrupalPractice.Objects.GlobalClass` | [`drupal/static-entity-load`](../analyzer/injection.md#drupalstatic-entity-load), partly | Coder reports a static `load()` or `loadMultiple()` call on one of 46 core entity classes, written with an imported name, in the same classes as its GlobalDrupal sniff. The analyzer check takes every entity class and a name written in full, and checks the classes of `drupal/global-drupal-call`. It skips a first-class callable, a constructor with a nullable parameter, deprecated code, anonymous classes and test code, which Coder reports. |
| `DrupalPractice.Objects.GlobalDrupal` | [`drupal/global-drupal-call`](../analyzer/injection.md#drupalglobal-drupal-call), partly | Coder reports a `\Drupal::` call in a non-static method of a service, of a class that implements `ContainerInjectionInterface`, or of a class that extends one of 12 base classes such as `FormBase`. It reads the services from the nearest, shortest-named services file, so it misses a service whose id names its class, and the services of a module with a second services file such as `drush.services.yml`. The analyzer check reads every services file, and also reports entity handlers, container derivers, hook classes and subclasses of those base classes. It skips a constructor with a nullable parameter, deprecated code, anonymous classes and test code, which Coder reports. |
| `DrupalPractice.Objects.GlobalFunction` | [`drupal/global-function`](../rules/right-api.md#drupalglobal-function) |  |
| `DrupalPractice.Objects.StrictSchemaDisabled` | [`drupal/strict-config-schema`](../rules/drupal-practice.md#drupalstrict-config-schema) |  |
| `DrupalPractice.Objects.UnusedPrivateMethod` | `mago analyze` (`unused-method`) | The analyzer also reports an unused private static method, an unused private method of an enum, and a private method that is called only from its own body, which Coder skips. Coder reports a private `__destruct()` and a private method that is called only as `$other->helper()`, `self::helper()`, `static::helper()` or `[self::class, 'helper']`; the analyzer reports none of them. |
| `DrupalPractice.Variables.GetRequestData` | [`drupal/request-superglobal`](../rules/drupal-practice.md#drupalrequest-superglobal), Mago `no-request-variable` |  |
| `DrupalPractice.Yaml.RoutingAccess` | [`drupal/routing-access`](../rules/info-and-routing-files.md#drupalrouting-access) |  |
| `VariableAnalysis.CodeAnalysis.VariableAnalysis` | `SelfOutsideClass`: `mago analyze` (`self-outside-class-scope`)<br>`StaticOutsideClass`: `mago analyze` (`static-outside-class-scope`)<br>`UndefinedUnsetVariable`, `VariableRedeclaration`: Nothing<br>`UnusedVariable`: [`drupal/unused-variable`](../rules/statements.md#drupalunused-variable) | Coder's ruleset skips `*.tpl.php` files, and so does `drupal/unused-variable`. `mago analyze` checks them. `UndefinedUnsetVariable`: An `unset()` of a variable that was never set. Not ported. Core's `phpcs.xml.dist` turns this code off. A read after `unset()` is `UndefinedVariable`, which Coder's ruleset turns off, and `mago analyze` reports it as `possibly-undefined-variable`. `VariableRedeclaration`: A variable declared twice, such as a parameter redeclared with `static`. Not ported: it needs flow analysis. |

<!-- /docs-gen -->

## Checks for the Drupal 7 API

These checks look for Drupal 7 functions, hooks and arrays, such as `check_plain()`, `hook_menu()`
or `$form_state['input']`, and Coder runs some of them only on a Drupal 7 module. Most aren't
ported. On Drupal 8 and later most of those functions don't exist, and `mago analyze` reports a call
to one as an unknown function. Nothing in Mago reports the `hook_menu()` checks or an `'und'` key.
`mago analyze` reports `$form_state['input']` only when `$form_state` is typed as
`FormStateInterface`, as an array access on an object. `FormErrorT` and `MessageT` report a literal
message passed to `form_set_error()`, `form_error()` or `drupal_set_message()`, and
[`drupal/untranslated-message`](../analyzer/translation.md#drupaluntranslated-message) reports the
same mistake in the form state and messenger methods that replace those functions. The Drupal 7
checks of the `Drupal` standard are on the [Drupal standard](drupal.md) page, since core's config
still runs them.

<!-- docs-gen:coder-drupal7 -->

| Sniff | Handled by |
| --- | --- |
| `DrupalPractice.FunctionCalls.CheckPlain` | Not ported |
| `DrupalPractice.FunctionCalls.DbQuery` | Not ported |
| `DrupalPractice.FunctionCalls.DbSelectBraces` | Not ported |
| `DrupalPractice.FunctionCalls.DefaultValueSanitize` | Not ported |
| `DrupalPractice.FunctionCalls.FormErrorT` | [`drupal/untranslated-message`](../analyzer/translation.md#drupaluntranslated-message), partly |
| `DrupalPractice.FunctionCalls.LCheckPlain` | Not ported |
| `DrupalPractice.FunctionCalls.MessageT` | [`drupal/untranslated-message`](../analyzer/translation.md#drupaluntranslated-message), partly |
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
