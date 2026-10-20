# DrupalPractice standard

Core's `phpcs.xml.dist` runs only `ExpectedException`, `ExceptionT`, `GlobalFunction`,
`NamespacedDependency` and part of `VariableAnalysis` from this standard. The rules and the analyzer
checks that port the other sniffs are [off in Drupal core](../setup/configuration.md#checking-drupal-core).

<!-- docs-gen:coder-practice -->

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| `DrupalPractice.Commenting.AuthorTag` | [`drupal/author-tag`](../rules/comment-text.md#drupalauthor-tag) |
| `DrupalPractice.Commenting.CommentEmptyLine` | [`drupal/inline-comment-blank-line`](../rules/comment-text.md#drupalinline-comment-blank-line), `mago format`, partly |
| `DrupalPractice.Commenting.ExpectedException` | [`drupal/expected-exception-tag`](../rules/comment-text.md#drupalexpected-exception-tag) |
| `DrupalPractice.Constants.GlobalConstant` | [`drupal/global-constant`](../rules/drupal-practice.md#drupalglobal-constant) |
| `DrupalPractice.Constants.GlobalDefine` | [`drupal/global-constant`](../rules/drupal-practice.md#drupalglobal-constant) |
| `DrupalPractice.FunctionCalls.CurlSslVerifier` | [`drupal/curl-ssl-verify`](../rules/drupal-practice.md#drupalcurl-ssl-verify) |
| `DrupalPractice.FunctionCalls.InsecureUnserialize` | [`drupal/insecure-unserialize`](../rules/bugs-and-security.md#drupalinsecure-unserialize), partly |
| `DrupalPractice.FunctionCalls.TCheckPlain` | [`drupal/escaped-placeholder`](../rules/drupal-practice.md#drupalescaped-placeholder) |
| `DrupalPractice.FunctionDefinitions.FormAlterDoc` | [`drupal/form-alter-comment`](../rules/drupal-practice.md#drupalform-alter-comment) |
| `DrupalPractice.General.ClassName` | [`drupal/class-prefix`](../rules/drupal-practice.md#drupalclass-prefix) |
| `DrupalPractice.General.DescriptionT` | [`drupal/untranslated-description`](../rules/drupal-practice.md#drupaluntranslated-description) |
| `DrupalPractice.General.ExceptionT` | [`drupal/translated-exception`](../rules/right-api.md#drupaltranslated-exception) |
| `DrupalPractice.General.OptionsT` | [`drupal/untranslated-options`](../rules/drupal-practice.md#drupaluntranslated-options) |
| `DrupalPractice.InfoFiles.CoreVersionRequirement` | [`drupal/info-core-version-requirement`](../rules/info-and-routing-files.md#drupalinfo-core-version-requirement) |
| `DrupalPractice.InfoFiles.Description` | [`drupal/info-description`](../rules/info-and-routing-files.md#drupalinfo-description) |
| `DrupalPractice.InfoFiles.NamespacedDependency` | [`drupal/info-namespaced-dependency`](../rules/info-and-routing-files.md#drupalinfo-namespaced-dependency) |
| [`DrupalPractice.Objects.GlobalClass`](#drupalpracticeobjectsglobalclass) | [`drupal/static-entity-load`](../analyzer/injection.md#drupalstatic-entity-load), partly |
| [`DrupalPractice.Objects.GlobalDrupal`](#drupalpracticeobjectsglobaldrupal) | [`drupal/global-drupal-call`](../analyzer/injection.md#drupalglobal-drupal-call), partly |
| `DrupalPractice.Objects.GlobalFunction` | [`drupal/global-function`](../rules/right-api.md#drupalglobal-function) |
| `DrupalPractice.Objects.StrictSchemaDisabled` | [`drupal/strict-config-schema`](../rules/drupal-practice.md#drupalstrict-config-schema) |
| [`DrupalPractice.Objects.UnusedPrivateMethod`](#drupalpracticeobjectsunusedprivatemethod) | `mago analyze` (`unused-method`) |
| `DrupalPractice.Variables.GetRequestData` | [`drupal/request-superglobal`](../rules/drupal-practice.md#drupalrequest-superglobal), Mago `no-request-variable` |
| `DrupalPractice.Yaml.RoutingAccess` | [`drupal/routing-access`](../rules/info-and-routing-files.md#drupalrouting-access) |
| [`VariableAnalysis.CodeAnalysis.VariableAnalysis`](#variableanalysiscodeanalysisvariableanalysis) | `mago analyze` (`self-outside-class-scope`), `mago analyze` (`static-outside-class-scope`), nothing, [`drupal/unused-variable`](../rules/statements.md#drupalunused-variable), [`drupal/variable-redeclaration`](../rules/statements.md#drupalvariable-redeclaration) |

## `DrupalPractice.Objects.GlobalClass`

Coder reports a static `load()` or `loadMultiple()` call on one of 46 core entity classes, written with an imported name, in the same classes as its GlobalDrupal sniff. The analyzer check takes every entity class and a name written in full, and checks the classes of `drupal/global-drupal-call`. It skips a first-class callable, a constructor with a nullable parameter, deprecated code, anonymous classes and test code, which Coder reports.

## `DrupalPractice.Objects.GlobalDrupal`

Coder reports a `\Drupal::` call in a non-static method of a service, of a class that implements `ContainerInjectionInterface`, or of a class that extends one of 12 base classes such as `FormBase`. It reads the services from the nearest, shortest-named services file, so it misses a service whose id names its class, and the services of a module with a second services file such as `drush.services.yml`. The analyzer check reads every services file, and also reports entity handlers, container derivers, hook classes and subclasses of those base classes. It skips a constructor with a nullable parameter, deprecated code, anonymous classes and test code, which Coder reports.

## `DrupalPractice.Objects.UnusedPrivateMethod`

The analyzer also reports an unused private static method, an unused private method of an enum, and a private method that is called only from its own body, which Coder skips. Coder reports a private `__destruct()` and a private method that is called only as `$other->helper()`, `self::helper()`, `static::helper()` or `[self::class, 'helper']`; the analyzer doesn't report any of them.

## `VariableAnalysis.CodeAnalysis.VariableAnalysis`

Coder's ruleset skips `*.tpl.php` files, and so do `drupal/unused-variable` and `drupal/variable-redeclaration`. `mago analyze` checks them.

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `SelfOutsideClass` | `mago analyze` (`self-outside-class-scope`) |
| `StaticOutsideClass` | `mago analyze` (`static-outside-class-scope`) |
| `UndefinedUnsetVariable` | nothing |
| `UnusedVariable` | [`drupal/unused-variable`](../rules/statements.md#drupalunused-variable) |
| `VariableRedeclaration` | [`drupal/variable-redeclaration`](../rules/statements.md#drupalvariable-redeclaration) |

- `UndefinedUnsetVariable`: An `unset()` of a variable that was never set. Not ported. Core's `phpcs.xml.dist` turns this code off. Coder doesn't count an element write such as `$x['k'] = 1` as setting `$x`, so it also reports `unset($x['k'])` after one. `mago analyze` reports such a variable as `undefined-variable` at its first element write. A read after `unset()` is `UndefinedVariable`, which Coder's ruleset turns off, and `mago analyze` reports it as `possibly-undefined-variable`.

<!-- /docs-gen -->

## Checks for the Drupal 7 API

These checks look for Drupal 7 functions, hooks and arrays, such as `check_plain()`, `hook_menu()`
or `$form_state['input']`, and Coder runs some of them only on a Drupal 7 module. Most aren't
ported. On Drupal 8 and later most of those functions don't exist, and `mago analyze` reports a call
to one as an unknown function. The notes say where a check has a Drupal 8 form that something else
reports. The Drupal 7 checks of the `Drupal` standard are on the [Drupal standard](drupal.md) page,
since core's config still runs them.

<!-- docs-gen:coder-drupal7 -->

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `DrupalPractice.FunctionCalls.CheckPlain` | nothing |  |
| `DrupalPractice.FunctionCalls.DbQuery` | nothing | A Drupal 7 API, deprecated in Drupal 8. On Drupal 9 and later the function doesn't exist, and `mago analyze` reports it as unknown. |
| `DrupalPractice.FunctionCalls.DbSelectBraces` | nothing | A Drupal 7 API, deprecated in Drupal 8. On Drupal 9 and later the function doesn't exist, and `mago analyze` reports it as unknown. |
| `DrupalPractice.FunctionCalls.DefaultValueSanitize` | nothing | Reports a `'#default_value'` set to the result of `check_plain()`, `check_markup()`, `check_url()`, `filter_xss()` or `filter_xss_admin()`. Of these, only `check_markup()` exists on Drupal 11, and it is deprecated in 11.4. |
| `DrupalPractice.FunctionCalls.FormErrorT` | [`drupal/untranslated-message`](../analyzer/translation.md#drupaluntranslated-message), partly | Coder checks only the Drupal 7 functions `form_set_error()` and `form_error()`, which don't exist on Drupal 8 and later, and `mago analyze` reports a call to one as unknown. The analyzer check reports the same mistake in the methods that replace them instead: `setErrorByName()` and `setError()` of `FormStateInterface`. |
| `DrupalPractice.FunctionCalls.LCheckPlain` | nothing |  |
| `DrupalPractice.FunctionCalls.MessageT` | [`drupal/untranslated-message`](../analyzer/translation.md#drupaluntranslated-message), partly | Coder checks only the Drupal 7 function `drupal_set_message()`, which doesn't exist on Drupal 9 and later, and `mago analyze` reports a call to it as unknown. The analyzer check reports the same mistake in the methods that replace it instead: `addMessage()`, `addStatus()`, `addWarning()` and `addError()` of `MessengerInterface`. |
| `DrupalPractice.FunctionCalls.Theme` | nothing | Coder also reports a call to `theme_settings_convert_to_config()`, which still exists on Drupal 11. That report is wrong: the function isn't a theme function, and the call is the right API. Mago doesn't report it. |
| `DrupalPractice.FunctionCalls.VariableSetSanitize` | nothing |  |
| `DrupalPractice.FunctionDefinitions.AccessHookMenu` | nothing | Reports an `'access callback' => TRUE` with no comment above it in `hook_menu()`, a Drupal 7 API. Mago doesn't report it. The routing file form, an open route with `_access: 'TRUE'` and no comment above it, is reported by `drupal/routing-access`. |
| `DrupalPractice.FunctionDefinitions.HookInitCss` | nothing |  |
| `DrupalPractice.FunctionDefinitions.InstallT` | nothing | Coder runs it only on a Drupal 7 module, for `t()` and `st()` in `hook_install()` and `hook_requirements()`. |
| `DrupalPractice.General.AccessAdminPages` | nothing | Reports the `access administration pages` permission in `hook_menu()`, a Drupal 7 API. Mago doesn't report it. The routing file form, `_permission: 'access administration pages'`, is reported by `drupal/routing-access`. |
| `DrupalPractice.General.FormStateInput` | nothing | Reports `$form_state['input']`, a read of the Drupal 7 form state array. When `$form_state` is typed as `FormStateInterface`, `mago analyze` reports any array access on it as `invalid-array-access`. Without the type, Mago doesn't report the key. |
| `DrupalPractice.General.LanguageNone` | nothing | Reports `['und']` written without spaces, as an index or as an array with one item, where Drupal 7 field code uses `LANGUAGE_NONE`. Mago doesn't report the key. On Drupal 8 and later `LANGUAGE_NONE` doesn't exist, and `mago analyze` reports it as `non-existent-constant`. |
| `DrupalPractice.General.VariableName` | nothing | A Drupal 7 `variable_get()` name without the module prefix, as the value of a `'#default_value'` key. |

<!-- /docs-gen -->
