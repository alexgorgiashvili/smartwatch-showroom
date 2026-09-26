<?php

namespace Tests\Unit;

use App\Services\ContentStudioRevisionDiff;
use PHPUnit\Framework\TestCase;

class ContentStudioRevisionDiffTest extends TestCase
{
    public function test_it_reports_only_changed_nested_fields(): void
    {
        $diff = (new ContentStudioRevisionDiff())->between(
            ['title_en' => 'Before', 'meta' => ['description' => 'same']],
            ['title_en' => 'After', 'meta' => ['description' => 'same']],
        );

        $this->assertSame(['title_en' => ['from' => 'Before', 'to' => 'After']], $diff);
    }
}
