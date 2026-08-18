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
# The lint run is pinned to this extension's rule codes so the built-in rules
# cannot fail the deliberately-bad fixtures. Mago's registration is compared
# against tests/corpus/expected-rules.txt first, because `--only` skips the
# expectations for rules it filters out: without that check a rule could stop
# registering and take its fixture coverage with it while the run stayed green.
test-corpus:
    {{mago}} --workspace tests/corpus extension list --json | php -r '$registered = json_decode(json: stream_get_contents(STDIN), associative: true, flags: JSON_THROW_ON_ERROR); $actual = []; foreach ($registered["extensions"] as $extension) { foreach ($extension["linter-rules"] as $rule) { $actual[] = $rule["code"]; } } sort($actual); $expected = array_map("trim", file("tests/corpus/expected-rules.txt")); sort($expected); if ($actual === $expected) { exit(0); } fwrite(STDERR, "Registered rules drifted from tests/corpus/expected-rules.txt\n"); foreach (array_diff($expected, $actual) as $code) { fwrite(STDERR, "  no longer registered: " . $code . "\n"); } foreach (array_diff($actual, $expected) as $code) { fwrite(STDERR, "  not pinned: " . $code . "\n"); } exit(1);'
    {{mago}} --workspace tests/corpus lint --only "$(paste -sd, - < tests/corpus/expected-rules.txt)"
    {{mago}} --workspace tests/corpus analyze

check: validate format-check test lint analyze

check-all: check test-corpus
