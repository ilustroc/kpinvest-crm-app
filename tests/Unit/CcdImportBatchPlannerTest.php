<?php

namespace Tests\Unit;

use App\Services\Ccd\CcdImportBatchPlanner;
use PHPUnit\Framework\TestCase;

class CcdImportBatchPlannerTest extends TestCase
{
    public function test_it_excludes_imported_items_and_selects_a_deterministic_batch(): void
    {
        $plan = (new CcdImportBatchPlanner)->plan([
            ['source' => '01.pdf', 'status' => 'already_imported', 'sha256' => 'a'],
            ['source' => '02.pdf', 'status' => 'would_import', 'sha256' => 'b'],
            ['source' => '03.pdf', 'status' => 'would_import', 'sha256' => 'c'],
        ], 1);

        $this->assertSame(2, $plan['pending']);
        $this->assertSame('02.pdf', $plan['selected'][0]['source']);
        $this->assertSame(1, $plan['counts']['already_imported']);
    }

    public function test_it_detects_duplicate_content_and_plans_reuse(): void
    {
        $plan = (new CcdImportBatchPlanner)->plan([
            ['source' => '01.pdf', 'status' => 'would_import', 'sha256' => 'same'],
            ['source' => '02.pdf', 'status' => 'would_import', 'sha256' => 'same'],
        ]);

        $this->assertSame(1, $plan['duplicate_hashes']);
        $this->assertSame(1, $plan['counts']['would_import']);
        $this->assertSame(1, $plan['counts']['would_reuse']);
    }

    public function test_it_marks_conflicts_as_critical(): void
    {
        $plan = (new CcdImportBatchPlanner)->plan([
            ['source' => '01.pdf', 'status' => 'conflict', 'sha256' => 'a'],
            ['source' => '02.pdf', 'status' => 'error'],
        ], 25);

        $this->assertSame(2, $plan['critical']);
        $this->assertSame([], $plan['selected']);
    }

    public function test_replan_resumes_after_completed_items(): void
    {
        $plan = (new CcdImportBatchPlanner)->plan([
            ['source' => '01.pdf', 'status' => 'already_imported', 'sha256' => 'a'],
            ['source' => '02.pdf', 'status' => 'already_imported', 'sha256' => 'b'],
            ['source' => '03.pdf', 'status' => 'would_import', 'sha256' => 'c'],
        ], 25);

        $this->assertSame(1, $plan['pending']);
        $this->assertSame('03.pdf', $plan['selected'][0]['source']);
    }
}
