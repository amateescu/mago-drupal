set dotenv-load := false

# Set MAGO=/path/to/mago to run the checks against a different binary, such
# as a local build.
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
# tests/corpus/src. Mago reports an expectation that is not fulfilled as an
# issue, so a clean run means that every rule reported exactly where the
# fixtures say it must. The fixtures are bad on purpose, so the lint run is
# limited to the rule codes of this extension. That keeps the built-in rules
# from failing the run. The first step compares the rules that Mago registers
# against tests/corpus/expected-rules.txt. That step is necessary because
# `--only` skips the expectations for the rules it filters out. Without
# the step, a rule can lose its registration and its fixtures are no longer
# checked, and the run stays green.
test-corpus:
    {{mago}} --workspace tests/corpus extension list --json | php -r '$registered = json_decode(json: stream_get_contents(STDIN), associative: true, flags: JSON_THROW_ON_ERROR); $actual = []; foreach ($registered["extensions"] as $extension) { foreach ($extension["linter-rules"] as $rule) { $actual[] = $rule["code"]; } } sort($actual); $expected = array_map("trim", file("tests/corpus/expected-rules.txt")); sort($expected); if ($actual === $expected) { exit(0); } fwrite(STDERR, "Registered rules drifted from tests/corpus/expected-rules.txt\n"); foreach (array_diff($expected, $actual) as $code) { fwrite(STDERR, "  no longer registered: " . $code . "\n"); } foreach (array_diff($actual, $expected) as $code) { fwrite(STDERR, "  not pinned: " . $code . "\n"); } exit(1);'
    {{mago}} --workspace tests/corpus lint --only "$(paste -sd, - < tests/corpus/expected-rules.txt)"
    {{mago}} --workspace tests/corpus analyze

check: validate format-check test lint analyze

check-all: check test-corpus
