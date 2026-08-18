set dotenv-load := false

# Override with MAGO=/path/to/mago to run against a different binary, such as
# a local build.
mago := env_var_or_default("MAGO", "vendor/bin/mago")

validate:
    composer validate --strict --no-check-publish

test:
    vendor/bin/phpunit --configuration phpunit.xml

lint:
    {{mago}} --config mago.toml lint

analyze:
    {{mago}} --config mago.toml analyze

format:
    {{mago}} --config mago.toml format

format-check:
    {{mago}} --config mago.toml format --check

# Starts a real worker and checks the inline `@mago-expect` annotations in
# tests/corpus/src. An unfulfilled expectation is reported as an issue, so a
# clean run means every rule fired exactly where the fixtures say it should.
# The lint run is pinned to this extension's rule codes, read from the
# extension itself, so built-in rules cannot fail the deliberately-bad
# fixtures.
test-corpus:
    codes="$(php -r 'require "vendor/autoload.php"; $codes = []; foreach (\amateescu\MagoDrupal\DrupalExtension::create()->linterRules as $rule) { $codes[] = $rule->getDefinition()->code; } echo implode(",", $codes);')" && test -n "$codes" && {{mago}} --workspace tests/corpus lint --only "$codes"
    {{mago}} --workspace tests/corpus analyze

check: validate format-check test lint analyze

check-all: check test-corpus
