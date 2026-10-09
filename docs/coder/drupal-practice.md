# DrupalPractice standard

Core's `phpcs.xml.dist` runs only `ExpectedException`, `ExceptionT`, `GlobalFunction`,
`NamespacedDependency` and part of `VariableAnalysis` from this standard. The worker's
[`--core` argument](../setup.md#checking-drupal-core) turns off the rules that port the other sniffs.

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
| `DrupalPractice.General.ExceptionT` | [`drupal/translated-exception`](../rules/right-api.md#drupaltranslated-exception) |  |
| `DrupalPractice.General.OptionsT` | [`drupal/untranslated-options`](../rules/drupal-practice.md#drupaluntranslated-options) |  |
| `DrupalPractice.InfoFiles.CoreVersionRequirement` | phpcs | Checks `.info.yml` files. |
| `DrupalPractice.InfoFiles.Description` | phpcs | Checks `.info.yml` files. |
| `DrupalPractice.InfoFiles.NamespacedDependency` | phpcs | Checks `.info.yml` files. |
| `DrupalPractice.Objects.GlobalClass` | Nothing | A static `Node::load()` style call in a class that can have the storage injected. Needs the analyzer; not ported yet. |
| `DrupalPractice.Objects.GlobalDrupal` | The analyzer (not released), partly | The analyzer half, on the `analyzer` branch, reports `\Drupal::` calls in a class with a `create()` method, ported from phpstan-drupal. |
| `DrupalPractice.Objects.GlobalFunction` | [`drupal/global-function`](../rules/right-api.md#drupalglobal-function) |  |
| `DrupalPractice.Objects.StrictSchemaDisabled` | [`drupal/strict-config-schema`](../rules/drupal-practice.md#drupalstrict-config-schema) |  |
| `DrupalPractice.Objects.UnusedPrivateMethod` | `mago analyze` (`unused-method`) |  |
| `DrupalPractice.Variables.GetRequestData` | [`drupal/request-superglobal`](../rules/drupal-practice.md#drupalrequest-superglobal), Mago `no-request-variable` |  |
| `DrupalPractice.Yaml.RoutingAccess` | phpcs | Checks `.routing.yml` files. |
| `VariableAnalysis.CodeAnalysis.VariableAnalysis` | `UndefinedUnsetVariable`, `VariableRedeclaration`: Nothing<br>`UnusedVariable`: Mago `no-redundant-variable`, partly | `UndefinedUnsetVariable`: A variable read after `unset()`. Not ported yet: it needs flow analysis. `UnusedVariable`: With `no-redundant-variable = { enabled = true }`. Variables at file scope are skipped. `VariableRedeclaration`: A variable declared twice, such as a parameter redeclared with `static`. Not ported yet: it needs flow analysis. |

<!-- /docs-gen -->

## Checks for the Drupal 7 API

These checks look for Drupal 7 functions and hooks, such as `check_plain()` or `db_query()`, and
Coder runs some of them only on a Drupal 7 module. They are not ported. On Drupal 8 and later those
functions do not exist, and `mago analyze` reports a call to one as an unknown function. The Drupal 7
checks of the `Drupal` standard are on the [Drupal standard](drupal.md) page, since core's config
still runs them.

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
| `DrupalPractice.General.DescriptionT` | Not ported |
| `DrupalPractice.General.FormStateInput` | Not ported |
| `DrupalPractice.General.LanguageNone` | Not ported |
| `DrupalPractice.General.VariableName` | Not ported |

<!-- /docs-gen -->
