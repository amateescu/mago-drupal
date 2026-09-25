<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Analyzer\Hooks\DeprecationScopeFilter;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\TypeComparator;
use Mago\Sdk\Internal\Analyzer\MetadataCache;
use Mago\Sdk\Internal\HostClient;
use Mago\Sdk\Internal\Io\ResourceWriter;
use Mago\Sdk\Internal\Protocol\FrameCodec;
use Mago\Sdk\Internal\SignalCancellationToken;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Reporting\Annotation;
use Mago\Sdk\Reporting\AnnotationKind;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\ReportedIssue;
use Mago\Sdk\Span;
use PHPUnit\Framework\TestCase;

use function fopen;
use function str_replace;
use function strpos;

final class DeprecationScopeFilterTest extends TestCase
{
    /**
     * A function outside any class, so an issue outside the scope needs no
     * codebase lookup for an overridden deprecated method.
     */
    private const MARKED = <<<'PHP'
        <?php
        /** @group legacy */
        function legacy_test_old(): void { deprecated_thing(); }
        PHP;

    public function testDropsADeprecationInsideALegacyScope(): void
    {
        $filter = new DeprecationScopeFilter();

        self::assertSame(IssueFilterDecision::Remove, $filter->filterIssue(self::context(self::MARKED)));
    }

    /**
     * The same worker sees the same path again after an edit, so the bytes
     * decide, not the name of the file.
     */
    public function testStopsDroppingOnceTheMarkerIsGone(): void
    {
        $filter = new DeprecationScopeFilter();
        $filter->filterIssue(self::context(self::MARKED));

        $edited = str_replace('/** @group legacy */', replace: '/** Nothing legacy here. */', subject: self::MARKED);
        self::assertSame(IssueFilterDecision::Keep, $filter->filterIssue(self::context($edited)));
    }

    private static function context(string $contents): IssueFilterContext
    {
        $stream = fopen('php://memory', mode: 'w');
        self::assertIsResource($stream);
        $host = new HostClient(new FrameCodec(), new ResourceWriter($stream));
        $codebase = new Codebase($host, 1, new SignalCancellationToken(), new MetadataCache(1));
        $offset = strpos($contents, needle: 'deprecated_thing');
        self::assertIsInt($offset);

        return new IssueFilterContext(
            PHPVersion::fromParts(major: 8, minor: 1),
            $codebase,
            new TypeComparator($host, 1, new SignalCancellationToken(), new MetadataCache(1)),
            new SignalCancellationToken(),
            'modules/legacy/tests/src/Unit/LegacyTest.php',
            $contents,
            new ReportedIssue(
                Level::Error,
                'deprecated-function',
                'Call to a deprecated function.',
                [],
                null,
                null,
                [new Annotation(AnnotationKind::Primary, new Span($offset, $offset + 16))],
                [],
            ),
        );
    }
}
