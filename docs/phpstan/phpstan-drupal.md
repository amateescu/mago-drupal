# phpstan-drupal

This page lists every rule and extension registered by phpstan-drupal 2.2.2, and what takes its
place in a Mago setup with this extension. To find a check, search for the identifier printed by
PHPStan with an error, or named in a baseline or an `@phpstan-ignore` comment.

Each identifier and extension is followed by what handles it:

- an analyzer check of this extension, such as `drupal/config-unknown-key`. Its entry under
  [Analyzer](../analyzer/index.md) says where it differs from phpstan-drupal;
- a linter rule of this extension, such as `drupal/discouraged-function`, for a check that needs no
  types. Its entry is under [Linter rules](../rules/index.md);
- `mago analyze` with one of Mago's own issues;
- a section of the analyzer docs, for an extension that types Drupal's APIs;
- nothing, when no tool checks it yet. The note says why.

The analyzer checks run by default, including the ones whose phpstan-drupal rule is off by default.
To turn one off, use `[analyzer] ignore`, as
[Turning rules off](../setup/worker-arguments.md#turning-rules-off) shows. A `@phpstan-ignore`
comment with one of phpstan-drupal's identifiers doesn't drop the matching Mago issue, see
[PHPStan ignores](../analyzer/phpstan-ignores.md).

## Rules

<!-- docs-gen:phpstan-drupal-rules -->

### AccessResultConditionRule

- `identical.alwaysFalse`: `mago analyze` (`redundant-comparison`)
- `notIdentical.alwaysFalse`: `mago analyze` (`redundant-comparison`)

Mago reports a strict comparison that is always true or always false anywhere, not only as the condition of `AccessResult::allowedIf()` or `forbiddenIf()`.

### BrowserTestBaseDefaultThemeRule

- `BrowserTestBase.defaultThemeRequired`: [`drupal/browser-test-default-theme`](../analyzer/tests.md#drupalbrowser-test-default-theme)

### CacheableDependencyRule

- `cacheable.dependency`: [`drupal/cacheable-dependency`](../analyzer/render-and-forms.md#drupalcacheable-dependency)

### ClassExtendsInternalClassRule

- `classExtendsInternalClass.classExtendsInternalClass`: [`drupal/internal-class-extension`](../analyzer/hooks.md#drupalinternal-class-extension)

### ConfigEntityConfigExportRule

- `configEntityConfigExport.noConfigExportKey`: [`drupal/config-entity-export`](../analyzer/entities.md#drupalconfig-entity-export)

### ConfigGetUnknownKeyRule

- `drupal.configGetUnknownKey`: [`drupal/config-unknown-key`](../analyzer/config.md#drupalconfig-unknown-key)

Off by default in phpstan-drupal.

### DependencySerializationTraitPropertyRule

- `dependencySerializationTraitProperty.unsupportedPrivateProperty`: [`drupal/dependency-serialization-property`](../analyzer/injection.md#drupaldependency-serialization-property)
- `dependencySerializationTraitProperty.unsupportedReadOnlyProperty`: [`drupal/dependency-serialization-property`](../analyzer/injection.md#drupaldependency-serialization-property)

### DeprecatedHookImplementation

- `deprecatedHookImplementation.other`: [`drupal/deprecated-hook`](../analyzer/hooks.md#drupaldeprecated-hook)
- `deprecatedHookImplementation.useHookFieldWidgetCompleteFormAlter`: nothing. Drupal 10 removed the documentation of `hook_field_widget_multivalue_form_alter()`, and phpstan-drupal reports its implementations from a list of its own.
- `deprecatedHookImplementation.useHookFieldWidgetMulitValueWidgetTypeFormAlter`: nothing. The same for `hook_field_widget_multivalue_WIDGET_TYPE_form_alter()`.

phpstan-drupal runs it only when a project adds it to `rules`.

### DiscouragedFunctionsRule

- `discouragedFunctions.discouragedFunctionCalled`: [`drupal/discouraged-function`](../rules/phpstan-drupal.md#drupaldiscouraged-function)

### EntityListBuilderOperationsCacheabilityRule

- `drupal.entityListBuilderMissingCacheabilityParameter`: [`drupal/list-builder-cacheability`](../analyzer/entities.md#drupallist-builder-cacheability)

### EntityQueryHasAccessCheckRule

- `entityQueryHasAccessCheck.noAccessCheck`: [`drupal/entity-query-access-check`](../analyzer/entities.md#drupalentity-query-access-check)

### EntityStorageDirectInjectionRule

- `drupal.entityStorageDirectInjection`: [`drupal/entity-storage-injection`](../analyzer/injection.md#drupalentity-storage-injection)

### EntityStoragePropertyAssignmentRule

- `drupal.entityStoragePropertyAssignment`: [`drupal/entity-storage-property`](../analyzer/injection.md#drupalentity-storage-property)

### GetDeprecatedServiceRule

- `getDeprecatedService.deprecated`: [`drupal/deprecated-service`](../analyzer/services.md#drupaldeprecated-service)

### GlobalDrupalDependencyInjectionRule

- `globalDrupalDependencyInjection.useDependencyInjection`: [`drupal/global-drupal-call`](../analyzer/injection.md#drupalglobal-drupal-call), partly

### HookEntityOperationCacheabilityRule

- `drupal.hookEntityOperationAlterMissingCacheabilityParameter`: [`drupal/hook-entity-operation-cacheability`](../analyzer/hooks.md#drupalhook-entity-operation-cacheability)
- `drupal.hookEntityOperationMissingCacheabilityParameter`: [`drupal/hook-entity-operation-cacheability`](../analyzer/hooks.md#drupalhook-entity-operation-cacheability)

### HookFormAlterRule

- `hookFormAlter.formParameterNotByRef`: [`drupal/hook-form-alter-signature`](../analyzer/hooks.md#drupalhook-form-alter-signature)
- `hookFormAlter.invalidFormIdParameterType`: [`drupal/hook-form-alter-signature`](../analyzer/hooks.md#drupalhook-form-alter-signature)
- `hookFormAlter.invalidFormParameterType`: [`drupal/hook-form-alter-signature`](../analyzer/hooks.md#drupalhook-form-alter-signature)
- `hookFormAlter.invalidFormStateParameterType`: [`drupal/hook-form-alter-signature`](../analyzer/hooks.md#drupalhook-form-alter-signature)
- `hookFormAlter.invalidParameterCount`: [`drupal/hook-form-alter-signature`](../analyzer/hooks.md#drupalhook-form-alter-signature)

### LoadIncludes

- `loadIncludes.fileNotLoadable`: [`drupal/load-include`](../analyzer/hooks.md#drupalload-include)
- `loadIncludes.moduleNotFound`: [`drupal/load-include`](../analyzer/hooks.md#drupalload-include)

### LoggerFromFactoryPropertyAssignmentRule

- `loggerFromFactory.propertyAssignment`: [`drupal/logger-from-factory`](../analyzer/injection.md#drupallogger-from-factory)

### ModuleLoadInclude

- `moduleLoadInclude.moduleNotFound`: nothing
- `moduleLoadInclude.moduleNotLoadable`: nothing

Drupal 11 removed `module_load_include()`.

### PluginAnnotationContextDefinitionsRule

- `pluginAnnotationContextDefinitions.useContextDefinitionsKey`: [`drupal/plugin-annotation-context`](../analyzer/plugins.md#drupalplugin-annotation-context)

### PluginManagerInspectionRule

- `pluginManagerInspection.alterInfoMissing`: [`drupal/plugin-manager-alter-info`](../analyzer/plugins.md#drupalplugin-manager-alter-info)
- `pluginManagerInspection.yamlPluginManagersInvokesParentConstructor`: nothing. A plugin manager with YAML discovery that calls `parent::__construct()`.

Off by default in phpstan-drupal.

### PluginManagerSetsCacheBackendRule

- `pluginManagerSetsCacheBackend.missingCacheBackend`: [`drupal/plugin-manager-cache-backend`](../analyzer/plugins.md#drupalplugin-manager-cache-backend)

### ProceduralHookEntityOperationCacheabilityRule

- `drupal.proceduralHookEntityOperationAlterMissingCacheabilityParameter`: [`drupal/hook-entity-operation-cacheability`](../analyzer/hooks.md#drupalhook-entity-operation-cacheability)
- `drupal.proceduralHookEntityOperationMissingCacheabilityParameter`: [`drupal/hook-entity-operation-cacheability`](../analyzer/hooks.md#drupalhook-entity-operation-cacheability)

### RenderCallbackRule

- `renderCallback.callbackNotImplementsTrustedCallback`: [`drupal/untrusted-callback`](../analyzer/render-and-forms.md#drupaluntrusted-callback)
- `renderCallback.callbackNotImplementsTrustedCallbackInterface`: [`drupal/untrusted-callback`](../analyzer/render-and-forms.md#drupaluntrusted-callback)
- `renderCallback.containsClosure`: [`drupal/form-closure-callback`](../analyzer/render-and-forms.md#drupalform-closure-callback)
- `renderCallback.expectsArrayOfCallbacks`: [`drupal/render-callback`](../rules/phpstan-drupal.md#drupalrender-callback)
- `renderCallback.expectsCallableArrayWithArguments`: [`drupal/render-callback`](../rules/phpstan-drupal.md#drupalrender-callback)
- `renderCallback.invalidKeyValue`: nothing. A callback whose type isn't known to be callable, such as a number or a string variable.
- `renderCallback.nonCallableCallback`: [`drupal/unknown-callback`](../analyzer/render-and-forms.md#drupalunknown-callback)
- `renderCallback.nonTrustedCallback`: [`drupal/render-callback`](../rules/phpstan-drupal.md#drupalrender-callback)

### StaticServiceDeprecatedServiceRule

- `staticServiceDeprecatedService.deprecated`: [`drupal/deprecated-service`](../analyzer/services.md#drupaldeprecated-service)

### SymfonyYamlParseRule

- `drupal.symfonyYamlParse`: [`drupal/symfony-yaml-parse`](../rules/phpstan-drupal.md#drupalsymfony-yaml-parse)

### TestClassesProtectedPropertyModulesRule

- `testClass.propertyModulesNonProtected`: [`drupal/test-modules-visibility`](../analyzer/tests.md#drupaltest-modules-visibility)

### TestClassSuffixNameRule

- `nonAbstractTestClass.NameNoSuffixTest`: [`drupal/test-class-suffix`](../rules/naming-and-imports.md#drupaltest-class-suffix), partly

### TodoCommentWithIssueUrlRule

- `drupal.todoCurrentIssue`: [`drupal/todo-current-issue`](../rules/phpstan-drupal.md#drupaltodo-current-issue)

phpstan-drupal runs it only when a project adds it to `rules`.

<!-- /docs-gen -->

## Extensions

phpstan-drupal's extensions type what Drupal's APIs return, read entity fields as properties,
replace core's docblocks with stub files and decide where a deprecated call is allowed. The analyzer
plugin does the same work, and the linked section says how.

<!-- docs-gen:phpstan-drupal-extensions -->

- `AccessCheckTypeSpecifyingExtension`: [Entities: Entity queries](../analyzer/entities.md#entity-queries)
- `ConfigGetDynamicReturnTypeExtension`: [Config: Config objects](../analyzer/config.md#config-objects). Off by default in phpstan-drupal.
- `ContainerDynamicReturnTypeExtension`: [Services: Container lookups](../analyzer/services.md#container-lookups)
- `DeprecationHelperScope`: [Deprecations: Deprecation scopes](../analyzer/deprecations.md#deprecation-scopes)
- `DrupalClassResolverDynamicReturnTypeExtension`: [Services: Container lookups](../analyzer/services.md#container-lookups)
- `DrupalClassResolverDynamicStaticReturnTypeExtension`: [Services: Container lookups](../analyzer/services.md#container-lookups)
- `DrupalServiceDynamicReturnTypeExtension`: [Services: Container lookups](../analyzer/services.md#container-lookups)
- `DrupalStaticEntityQueryDynamicReturnTypeExtension`: [Entities: Entity queries](../analyzer/entities.md#entity-queries)
- `DrupalStubFilesExtension`: [Core types and stubs: Stub files](../analyzer/core-types.md#stub-files), partly. Not every stub is translated, see [Not ported from phpstan-drupal](../analyzer/core-types.md#not-ported-from-phpstan-drupal).
- `EntityAccessControlHandlerReturnTypeExtension`: [Entities: Access checks](../analyzer/entities.md#access-checks)
- `EntityFieldMethodsViaMagicReflectionExtension`: [Entities: Magic entity fields](../analyzer/entities.md#magic-entity-fields)
- `EntityFieldsViaMagicReflectionExtension`: [Entities: Magic entity fields](../analyzer/entities.md#magic-entity-fields)
- `EntityIdNarrowedByNew`: nothing. `id()` isn't null once `isNew()` is false, see [Not ported from phpstan-drupal](../analyzer/core-types.md#not-ported-from-phpstan-drupal).
- `EntityQueryAccessCheckDynamicReturnTypeExtension`: [Entities: Entity queries](../analyzer/entities.md#entity-queries)
- `EntityQueryDynamicReturnTypeExtension`: [Entities: Entity queries](../analyzer/entities.md#entity-queries)
- `EntityRepositoryReturnTypeExtension`: [Entities: Handlers and storage](../analyzer/entities.md#handlers-and-storage)
- `EntityStorageDynamicReturnTypeExtension`: [Entities: Handlers and storage](../analyzer/entities.md#handlers-and-storage)
- `EntityTypeIdTypeNodeResolverExtension`: nothing. The `entity-type-id` docblock type.
- `EntityTypeKeyByExistence`: [Entities: Entity type keys](../analyzer/entities.md#entity-type-keys), partly. `getKey()` is a string for the keys that every entity type has. It isn't narrowed by a `hasKey()` call for the same key.
- `EntityTypeLinkTemplateByExistence`: nothing. `getLinkTemplate()` isn't false once `hasLinkTemplate()` is true for the same template, see [Not ported from phpstan-drupal](../analyzer/core-types.md#not-ported-from-phpstan-drupal).
- `EntityTypeManagerGetStorageDynamicReturnTypeExtension`: [Entities: Handlers and storage](../analyzer/entities.md#handlers-and-storage)
- `GetQueryReturnTypeExtension`: [Entities: Entity queries](../analyzer/entities.md#entity-queries)
- `GroupLegacyScope`: [Deprecations: Deprecation scopes](../analyzer/deprecations.md#deprecation-scopes)
- `IgnoreDeprecationsScope`: [Deprecations: Deprecation scopes](../analyzer/deprecations.md#deprecation-scopes)
- `InspectorTypeExtension`: [Core types and stubs: Inspector assertions](../analyzer/core-types.md#inspector-assertions)

<!-- /docs-gen -->
