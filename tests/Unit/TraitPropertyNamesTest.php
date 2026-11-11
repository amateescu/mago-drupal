<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Traits\TraitPropertyNames;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class TraitPropertyNamesTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/mago-drupal-trait-properties-' . uniqid() . '.php';
        file_put_contents($this->file, data: '');
    }

    protected function tearDown(): void
    {
        unlink($this->file);
    }

    public function testFindsThePropertiesATraitReadsAndDoesNotDeclare(): void
    {
        file_put_contents($this->file, <<<'PHP'
            <?php

            namespace Drupal\Tests\tools\Traits;

            trait ToolTrait {

              protected $declared;

              private static ?object $cache = NULL;

              public function run(string $parameter): string {
                static $counted;
                $local = $this->container->get('tools');
                $this->declared = $this->helper();
                $name = Foo::class . self::class;
                // A comment that reads $this->commented.
                return "{$this->server->id()} $this->label " . $this?->renderer->render($local) . $parameter;
              }

            }

            final class NotATrait {

              public function run(): void {
                $this->outside->run();
              }

            }

            trait SecondTrait {

              public function reset(): void {
                $this->container = NULL;
                $this->second ??= [];
              }

            }
            PHP);

        self::assertSame(
            [
                'Drupal\Tests\tools\Traits\ToolTrait' => ['container', 'server', 'label', 'renderer'],
                'Drupal\Tests\tools\Traits\SecondTrait' => ['container', 'second'],
            ],
            TraitPropertyNames::file($this->file),
        );
    }

    public function testSkipsAFileWithoutATrait(): void
    {
        file_put_contents(
            $this->file,
            data: "<?php\nfinal class Portrait { public function a() { return \$this->container; } }\n",
        );

        self::assertNull(TraitPropertyNames::file($this->file));
    }

    public function testMergesTheNamesOfEveryFile(): void
    {
        $names = TraitPropertyNames::merge([
            'a.php' => ['B\Trait' => ['server', 'container']],
            'b.php' => null,
            'c.php' => ['A\Trait' => ['renderer', ''], 'B\Trait' => ['container', 'kernel']],
        ]);

        self::assertSame(['A\Trait' => ['renderer'], 'B\Trait' => ['server', 'container', 'kernel']], $names->names);
    }
}
