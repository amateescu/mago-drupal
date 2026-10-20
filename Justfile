set dotenv-load := false

# Set MAGO=/path/to/mago to run the checks against a different binary, such
# as a local build.
mago := env_var_or_default("MAGO", "vendor/bin/mago")

# The Zensical version that builds the docs site.
zensical := "0.0.69"

validate:
    composer validate --strict --no-check-publish

test:
    vendor/bin/phpunit --configuration phpunit.xml

lint:
    {{mago}} --config mago.toml lint

analyze:
    {{mago}} --config mago.toml analyze

# The corpus and tests/ignores have their own workspaces, formatted with
# Drupal's preset.
format:
    {{mago}} --config mago.toml format
    {{mago}} --workspace tests/corpus format
    {{mago}} --workspace tests/ignores format

format-check:
    {{mago}} --config mago.toml format --check
    {{mago}} --workspace tests/corpus format --check
    {{mago}} --workspace tests/ignores format --check

# Applies safe fixes from every tool and loops until nothing changes. This
# repository's mago.toml does not load the extension, so only Mago's built-in
# rules run here.
fix:
    {{mago}} --config mago.toml fix

# Starts a real worker and checks the inline `@mago-expect` annotations in
# tests/corpus/src. Mago reports an expectation that is not fulfilled as an
# issue, and an issue that no expectation takes as well. Mago lets an
# expectation inside a function body take any later issue of its code in the
# file, though, so the last step runs the corpus again through
# tests/CorpusPins.php, which holds every issue to the lines its expectation is
# written above. The fixtures are bad on purpose, so the lint run is
# limited to the rule codes of this extension. That keeps the built-in rules
# from failing the run. The lint run poses as the GitLab merge request of issue
# 3456789, so drupal/todo-current-issue has an issue to compare links with.
# The first step compares the rules that Mago registers
# against tests/corpus/expected-rules.txt. That step is necessary because
# `--only` skips the expectations for the rules it filters out. Without
# the step, a rule can lose its registration and its fixtures are no longer
# checked, and the run stays green. The last step checks the pins of
# tests/ignores, whose worker reads phpcs and PHPStan comments.
test-corpus:
    {{mago}} --workspace tests/corpus extension list --json | php -r '$registered = json_decode(json: stream_get_contents(STDIN), associative: true, flags: JSON_THROW_ON_ERROR); $actual = []; foreach ($registered["extensions"] as $extension) { foreach ($extension["linter-rules"] as $rule) { $actual[] = $rule["code"]; } } sort($actual); $expected = array_map("trim", file("tests/corpus/expected-rules.txt")); sort($expected); if ($actual === $expected) { exit(0); } fwrite(STDERR, "Registered rules drifted from tests/corpus/expected-rules.txt\n"); foreach (array_diff($expected, $actual) as $code) { fwrite(STDERR, "  no longer registered: " . $code . "\n"); } foreach (array_diff($actual, $expected) as $code) { fwrite(STDERR, "  not pinned: " . $code . "\n"); } exit(1);'
    CI_MERGE_REQUEST_SOURCE_BRANCH_NAME=3456789-corpus {{mago}} --workspace tests/corpus lint --only "$(paste -sd, - < tests/corpus/expected-rules.txt)"
    {{mago}} --workspace tests/corpus analyze
    php tests/CorpusPins.php {{mago}}
    php tests/CorpusPins.php {{mago}} tests/ignores

# Runs every rule's fix over the before and after pairs in tests/fixes. See
# tests/FixCases.php for the layout.
test-fixes:
    php tests/FixCases.php {{mago}}

# Lints the reported and the fine half of every example in the rule docs, and
# runs the fix where the example says it turns one into the other. See
# tests/DocExamples.php for the format. Then analyzes both halves of every
# example in the analyzer docs, see tests/CheckExamples.php.
test-examples:
    php tests/DocExamples.php {{mago}}
    php tests/CheckExamples.php {{mago}}

# Loads the config files that projects extend. Mago fails on a setting or a
# rule name that it doesn't know. Then compares the files that the linter
# reads in tests/presets, a site that extends drupal.mago.toml, with
# tests/presets/linter-files.txt.
test-presets:
    {{mago}} --config drupal.mago.toml config > /dev/null
    {{mago}} --config drupal-core.mago.toml config > /dev/null
    {{mago}} --workspace tests/presets list-files --command linter | LC_ALL=C sort | diff tests/presets/linter-files.txt -

# Compares the files that drupal-core.mago.toml leaves out with what a core
# checkout's phpcs skips, such as `just check-core-excludes ../drupal`. Not
# part of check-all, since CI doesn't have a core checkout.
check-core-excludes checkout:
    php tests/CoreExcludes.php {{mago}} {{checkout}}

# Rewrites the generated parts of the docs pages from the registered rules
# and tests/coder-map.json. DocsTest fails when they are out of date.
docs-gen:
    php tests/GenerateDocs.php

# Serves the docs site with live reload on http://127.0.0.1:8000.
docs: docs-env
    .venv/bin/zensical serve

# Builds the docs site into site/ and fails on a broken link or anchor.
docs-build: docs-env
    .venv/bin/zensical build --strict

# Installs the pinned Zensical into .venv, and again when the pin changes.
docs-env:
    test "$(.venv/bin/zensical --version 2>/dev/null)" = "{{zensical}}" || (python3 -m venv .venv && .venv/bin/pip install --quiet "zensical=={{zensical}}")

check: validate format-check test lint analyze

check-all: check test-presets test-corpus test-fixes test-examples
