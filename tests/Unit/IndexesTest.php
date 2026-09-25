<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Indexes;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
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
