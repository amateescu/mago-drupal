<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Indexes;
use amateescu\MagoDrupal\Internal\ServiceArguments;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function file_put_contents;
use function mkdir;
use function realpath;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

/**
 * An editor session analyzes again in the same worker, and an incremental
 * analysis runs the codebase scan only when a file it targets changed. The
 * indexes have to notice a new analysis without the scan.
 */
final class IndexesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mago-drupal-indexes-' . uniqid();
        mkdir($this->root . '/core/lib', recursive: true);
        file_put_contents($this->root . '/core/lib/Drupal.php', data: "<?php\n");
        $this->writeServices(['first' => 'Drupal\Core\First']);
    }

    protected function tearDown(): void
    {
        DiskCacheTest::remove($this->root);
    }

    public function testRebuildsForANewAnalysis(): void
    {
        $indexes = new Indexes($this->root);
        self::assertTrue($indexes->services(AnalysisGenerationTest::codebase(1))->has('first'));

        $this->writeServices(['first' => 'Drupal\Core\First', 'second' => 'Drupal\Core\Second']);
        self::assertFalse($indexes->services(AnalysisGenerationTest::codebase(1))->has('second'));
        self::assertTrue($indexes->services(AnalysisGenerationTest::codebase(2))->has('second'));
    }

    public function testKeepsWhatTheLastScanFound(): void
    {
        $indexes = new Indexes($this->root);
        $indexes->setProvided(['provided' => ['class' => 'Drupal\Core\Provided']]);
        self::assertTrue($indexes->services(AnalysisGenerationTest::codebase(1))->has('provided'));

        // A scan that did not run found nothing new.
        self::assertTrue($indexes->services(AnalysisGenerationTest::codebase(2))->has('provided'));

        // A scan that runs starts over and publishes what it finds.
        $indexes->reset();
        self::assertFalse($indexes->services(AnalysisGenerationTest::codebase(2))->has('provided'));
    }

    /**
     * The scan hands over the text an editor has not saved, which wins over
     * the file on disk until the next scan.
     */
    public function testReadsAnnotatedPluginsFromTheScannedText(): void
    {
        mkdir($this->root . '/modules/alpha/src/Plugin/Block', recursive: true);
        touch($this->root . '/modules/alpha/alpha.info.yml');
        $file = $this->root . '/modules/alpha/src/Plugin/Block/Alpha.php';
        file_put_contents($file, data: self::block('old_id'));
        $block = 'Drupal\Core\Block\Attribute\Block';

        $indexes = new Indexes($this->root);
        self::assertSame(['old_id'], array_keys($indexes->annotated()->plugins[$block] ?? []));

        $indexes->setAnnotatedSources([(string) realpath($file) => self::block('new_id')]);
        self::assertSame(['new_id'], array_keys($indexes->annotated()->plugins[$block] ?? []));

        // A new analysis keeps what the last scan found.
        $indexes->services(AnalysisGenerationTest::codebase(2));
        self::assertSame(['new_id'], array_keys($indexes->annotated()->plugins[$block] ?? []));
    }

    private static function block(string $id): string
    {
        return "<?php\n\nnamespace Drupal\\alpha\\Plugin\\Block;\n\n/**\n * @Block(\n *   id = \"{$id}\",\n * )\n */\nclass Alpha {}\n";
    }

    public function testProvidedDefinitionsKeepTheirVisibility(): void
    {
        $indexes = new Indexes($this->root);
        $indexes->setProvided([
            'provided.private' => ['class' => 'Drupal\Core\Provided', 'public' => false],
            'provided.public' => ['class' => 'Drupal\Core\Provided'],
            // A child definition takes its parent's visibility unless it
            // sets its own.
            'provided.child' => ['parent' => 'first'],
            'provided.closed_child' => ['parent' => 'first', 'public' => false],
        ]);
        $services = $indexes->services(AnalysisGenerationTest::codebase(1));

        self::assertFalse($services->get('provided.private')?->public);
        self::assertTrue($services->get('provided.public')?->public);
        self::assertTrue($services->get('provided.child')?->public);
        self::assertSame('Drupal\Core\First', $services->get('provided.child')?->class);
        self::assertFalse($services->get('provided.closed_child')?->public);
    }

    public function testReadsParametersNextToTheServices(): void
    {
        file_put_contents(
            $this->root . '/core/core.services.yml',
            data: "parameters:\n  app.root: ''\nservices:\n  first:\n    class: Drupal\\Core\\First\n",
        );
        $indexes = new Indexes($this->root);

        self::assertSame(
            'string',
            (string) $indexes->parameters(AnalysisGenerationTest::codebase(1))->type('app.root'),
        );
        self::assertTrue($indexes->services(AnalysisGenerationTest::codebase(1))->has('first'));
    }

    public function testCountsArgumentsOfTheYamlServicesOnly(): void
    {
        file_put_contents(
            $this->root . '/core/core.services.yml',
            data: "services:\n  first:\n    class: Drupal\\Core\\First\n    arguments: ['@a']\n",
        );
        $indexes = new Indexes($this->root);
        $indexes->setProvided(['provided' => ['class' => 'Drupal\Core\Provided']]);
        $wiring = $indexes->wiring(AnalysisGenerationTest::codebase(1));

        self::assertSame(
            [1],
            array_map(
                static fn(ServiceArguments $service): int => $service->count,
                $wiring->arguments('Drupal\Core\First'),
            ),
        );
        self::assertFalse($wiring->hasArguments('Drupal\Core\Provided'));
    }

    /**
     * Writes core's services file with one service per id.
     *
     * @param array<string, string> $classes
     */
    private function writeServices(array $classes): void
    {
        $yaml = "services:\n";
        foreach ($classes as $id => $class) {
            $yaml .= "  {$id}:\n    class: {$class}\n";
        }

        file_put_contents($this->root . '/core/core.services.yml', $yaml);
    }
}
