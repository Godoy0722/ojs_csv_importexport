<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Handlers/DryModeCurrentIssueStatusTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DryModeCurrentIssueStatusTest
 *
 * @brief Unpublished imported issues stay out of current-issue selection
 */

namespace APP\plugins\importexport\csv\tests\Unit\Handlers;

use APP\issue\Collector as IssueCollector;
use APP\issue\Issue;
use APP\issue\Repository as IssueRepository;
use APP\plugins\importexport\csv\classes\handlers\DryModeCurrentIssue;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use ReflectionProperty;

#[CoversClass(DryModeCurrentIssue::class)]
class DryModeCurrentIssueStatusTest extends BaseTestCase
{
    public function testRememberKeepsAnUnpublishedIssueOutUntilAPublishedRowArrives(): void
    {
        $handler = new DryModeCurrentIssue();
        $issue = $this->issue(5, false, '2024-01-01');

        $handler->remember($issue, 1, true);
        $this->assertFalse($this->snapshots($handler)[0]['published']);

        $issue->setPublished(true);
        $handler->remember($issue, 1, false);
        $this->assertTrue($this->snapshots($handler)[0]['published']);
    }

    public function testUnpublishedSnapshotDoesNotReplaceTheJournalCurrentIssue(): void
    {
        $current = new Issue();
        $current->setId(10);
        $current->setJournalId(1);
        $current->setPublished(true);
        $current->setYear(2024);
        $current->setDatePublished('2024-01-01');

        $collector = \Mockery::mock(IssueCollector::class);
        $collector->shouldReceive('filterByContextIds')->andReturnSelf();
        $collector->shouldReceive('filterByPublished')->andReturnSelf();
        $collector->shouldReceive('getMany')->andReturn(new LazyCollection([$current]));

        $this->backupContainerInstance(IssueRepository::class);
        $repository = \Mockery::mock(IssueRepository::class);
        $repository->shouldReceive('getCollector')->andReturn($collector);
        app()->instance(IssueRepository::class, $repository);

        $handler = new DryModeCurrentIssue();
        $method = new ReflectionMethod(DryModeCurrentIssue::class, 'selectCurrentIssueForJournal');
        $method->setAccessible(true);

        $selected = $method->invoke($handler, 1, [[
            'journalId' => 1,
            'issueId' => 99,
            'createdByImport' => true,
            'published' => false,
            'volume' => '',
            'number' => '',
            'year' => '2030',
            'title' => 'Future',
            'locale' => 'en',
            'issueDatePublished' => '2030-01-01',
            'articleDatePublished' => '',
        ]], $current);

        $this->assertSame(10, $selected->getId());
    }

    private function issue(int $id, bool $published, string $date): Issue
    {
        $issue = new Issue();
        $issue->setId($id);
        $issue->setPublished($published);
        $issue->setDatePublished($date);
        $issue->setVolume(1);
        $issue->setNumber('1');
        $issue->setYear(2024);

        return $issue;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function snapshots(DryModeCurrentIssue $handler): array
    {
        $property = new ReflectionProperty(DryModeCurrentIssue::class, 'importedIssueSnapshots');
        $property->setAccessible(true);

        return array_values($property->getValue($handler));
    }
}
