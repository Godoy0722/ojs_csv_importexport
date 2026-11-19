<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Validations/IssuePublicationDateIntegrationTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IssuePublicationDateIntegrationTest
 *
 * @brief Integration tests verifying issuePublicationDate flows through IssueProcessor to the Issue object.
 */

namespace APP\plugins\importexport\csv\tests\Unit\Validations;

use APP\issue\Collector as IssueCollector;
use APP\issue\DAO as IssueDAO;
use APP\issue\Issue;
use APP\issue\Repository as IssueRepository;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\processors\IssueProcessor;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\core\Core;

#[CoversClass(IssueProcessor::class)]
class IssuePublicationDateIntegrationTest extends BaseTestCase
{
    private array $issuesCacheBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->issuesCacheBackup = CachedEntities::$issues;
        CachedEntities::$issues = [];
    }

    protected function tearDown(): void
    {
        CachedEntities::$issues = $this->issuesCacheBackup;
        parent::tearDown();
    }

    private function createIssueRepositoryMock(): \Mockery\MockInterface
    {
        $collectorMock = Mockery::mock(IssueCollector::class);
        $collectorMock->shouldReceive('filterByContextIds')->andReturnSelf();
        $collectorMock->shouldReceive('filterByVolumes')->andReturnSelf();
        $collectorMock->shouldReceive('filterByNumbers')->andReturnSelf();
        $collectorMock->shouldReceive('filterByYears')->andReturnSelf();
        $collectorMock->shouldReceive('filterByTitles')->andReturnSelf();
        $collectorMock->shouldReceive('limit')->andReturnSelf();
        $collectorMock->shouldReceive('getMany')->andReturn(new LazyCollection([]));

        $issueDaoMock = Mockery::mock(IssueDAO::class)->makePartial();
        $issueDaoMock->shouldReceive('insert')->andReturn(1);
        $issueDaoMock->shouldReceive('update')->andReturn(true);

        $this->backupContainerInstance(IssueRepository::class);

        $mock = Mockery::mock(IssueRepository::class)->makePartial();
        $mock->shouldReceive('getCollector')->andReturn($collectorMock);
        $mock->shouldReceive('add')->andReturn(1)->byDefault();
        $mock->shouldReceive('get')->andReturnUsing(fn() => new Issue());
        $mock->dao = $issueDaoMock;

        app()->instance(IssueRepository::class, $mock);

        return $mock;
    }

    /**
     * When issuePublicationDate is provided, Issue should get that exact date.
     */
    public function testIssuePublicationDateIsSetOnIssueWhenProvided(): void
    {
        $data = (object)[
            'locale' => 'en',
            'issueTitle' => 'Integration Test Issue',
            'issueVolume' => '99',
            'issueNumber' => '99',
            'issueYear' => '2099',
            'issueDescription' => 'Testing issuePublicationDate flow',
            'issuePublicationDate' => '2024-06-15',
        ];

        $datePublished = null;
        $mock = $this->createIssueRepositoryMock();
        $mock->shouldReceive('newDataObject')->andReturnUsing(function () use (&$datePublished) {
            $issue = Mockery::mock(Issue::class)->makePartial();
            $issue->shouldReceive('setJournalId');
            $issue->shouldReceive('setShowVolume');
            $issue->shouldReceive('setShowNumber');
            $issue->shouldReceive('setShowYear');
            $issue->shouldReceive('setShowTitle');
            $issue->shouldReceive('setPublished');
            $issue->shouldReceive('setDatePublished')->andReturnUsing(function ($date) use (&$datePublished) {
                $datePublished = $date;
            });
            $issue->shouldReceive('setDescription');
            $issue->shouldReceive('setAccessStatus');
            $issue->shouldReceive('setData');
            $issue->shouldReceive('stampModified');
            $issue->shouldReceive('setVolume');
            $issue->shouldReceive('setNumber');
            $issue->shouldReceive('setYear');
            $issue->shouldReceive('setTitle');
            return $issue;
        });

        IssueProcessor::process(1, $data);

        $this->assertEquals('2024-06-15', $datePublished,
            'Issue should be created with the provided issuePublicationDate');
    }

    /**
     * When issuePublicationDate is empty, creation leaves the date unset so it can
     * be filled later from the most recent article datePublished in the issue.
     */
    public function testIssueDateStaysEmptyWhenIssuePublicationDateIsEmpty(): void
    {
        $data = (object)[
            'locale' => 'en',
            'issueTitle' => 'Fallback Test Issue',
            'issueVolume' => '88',
            'issueNumber' => '88',
            'issueYear' => '2088',
            'issueDescription' => 'Testing fallback date',
            'issuePublicationDate' => '',
        ];

        $datePublished = 'sentinel';
        $mock = $this->createIssueRepositoryMock();
        $mock->shouldReceive('newDataObject')->andReturnUsing(function () use (&$datePublished) {
            $issue = Mockery::mock(Issue::class)->makePartial();
            $issue->shouldReceive('setJournalId');
            $issue->shouldReceive('setShowVolume');
            $issue->shouldReceive('setShowNumber');
            $issue->shouldReceive('setShowYear');
            $issue->shouldReceive('setShowTitle');
            $issue->shouldReceive('setPublished');
            $issue->shouldReceive('setDatePublished')->andReturnUsing(function ($date) use (&$datePublished) {
                $datePublished = $date;
            });
            $issue->shouldReceive('setDescription');
            $issue->shouldReceive('setAccessStatus');
            $issue->shouldReceive('setData');
            $issue->shouldReceive('stampModified');
            $issue->shouldReceive('setVolume');
            $issue->shouldReceive('setNumber');
            $issue->shouldReceive('setYear');
            $issue->shouldReceive('setTitle');
            return $issue;
        });

        IssueProcessor::process(1, $data);

        $this->assertEmpty($datePublished,
            'Issue should be created without a date when issuePublicationDate is empty');
    }

    /**
     * Issues imported without issuePublicationDate take the latest article datePublished.
     */
    public function testFillMissingIssueDatesUsesMostRecentArticleDate(): void
    {
        $this->beginDatabaseTransaction();
        try {
            $issueId = 990001;
            DB::table('publications')->insert([
                ['submission_id' => 1, 'issue_id' => $issueId, 'date_published' => '2019-04-01', 'status' => 3, 'seq' => 0],
                ['submission_id' => 1, 'issue_id' => $issueId, 'date_published' => '2021-08-20', 'status' => 3, 'seq' => 0],
                ['submission_id' => 1, 'issue_id' => $issueId, 'date_published' => '2020-01-15', 'status' => 3, 'seq' => 0],
            ]);

            $issue = new Issue();
            $issue->setId($issueId);

            $saved = null;
            $mock = $this->createIssueRepositoryMock();
            $mock->shouldReceive('edit')->once()->andReturnUsing(function ($edited) use (&$saved) {
                $saved = $edited->getDatePublished();
            });

            IssueProcessor::fillMissingIssueDates([
                ['issue' => $issue, 'journalId' => 1],
            ]);

            $this->assertSame('2021-08-20', substr((string) $saved, 0, 10));
        } finally {
            $this->rollbackDatabaseTransaction();
        }
    }

    /**
     * A queued or scheduled article date does not fill the issue date.
     */
    public function testFillMissingIssueDatesIgnoresUnpublishedArticleDates(): void
    {
        $this->beginDatabaseTransaction();
        try {
            $issueId = 990011;
            DB::table('publications')->insert([
                ['submission_id' => 1, 'issue_id' => $issueId, 'date_published' => '2020-01-15', 'status' => 3, 'seq' => 0],
                ['submission_id' => 1, 'issue_id' => $issueId, 'date_published' => '2024-06-01', 'status' => 1, 'seq' => 0],
                ['submission_id' => 1, 'issue_id' => $issueId, 'date_published' => '2024-07-01', 'status' => 5, 'seq' => 0],
            ]);

            $issue = new Issue();
            $issue->setId($issueId);

            $saved = null;
            $mock = $this->createIssueRepositoryMock();
            $mock->shouldReceive('edit')->once()->andReturnUsing(function ($edited) use (&$saved) {
                $saved = $edited->getDatePublished();
            });

            IssueProcessor::fillMissingIssueDates([
                ['issue' => $issue, 'journalId' => 1],
            ]);

            $this->assertSame('2020-01-15', substr((string) $saved, 0, 10));
        } finally {
            $this->rollbackDatabaseTransaction();
        }
    }

    /**
     * A published article publishes a new issue. A queued article leaves it unpublished.
     */
    public function testNewIssuePublishedFlagFollowsTheArticle(): void
    {
        $captured = null;
        $mock = $this->createIssueRepositoryMock();
        $mock->shouldReceive('add')->once()->andReturnUsing(function ($issue) use (&$captured) {
            $captured = $issue->getPublished();
            return 8;
        });

        IssueProcessor::process(1, $this->issueRow(), false);

        $this->assertFalse((bool) $captured);
    }

    /**
     * A later published article publishes an issue that was created unpublished.
     */
    public function testPublishedArticlePublishesAnExistingUnpublishedIssue(): void
    {
        $existing = new Issue();
        $existing->setId(4);
        $existing->setPublished(false);
        $existing->setShowTitle(true);
        CachedEntities::$issues['title:Future'] = $existing;

        $edited = false;
        $mock = $this->createIssueRepositoryMock();
        $mock->shouldReceive('edit')->once()->andReturnUsing(function () use (&$edited) {
            $edited = true;
        });

        $issue = IssueProcessor::process(1, $this->issueRow(), true);

        $this->assertTrue($edited);
        $this->assertTrue((bool) $issue->getPublished());
    }

    /**
     * A queued article does not unpublish an issue that is already public.
     */
    public function testQueuedArticleLeavesAPublishedIssuePublished(): void
    {
        $existing = new Issue();
        $existing->setId(4);
        $existing->setPublished(true);
        $existing->setShowTitle(true);
        CachedEntities::$issues['title:Future'] = $existing;

        $mock = $this->createIssueRepositoryMock();
        $mock->shouldReceive('edit')->never();

        $issue = IssueProcessor::process(1, $this->issueRow(), false);

        $this->assertTrue((bool) $issue->getPublished());
    }

    private function issueRow(): object
    {
        return (object) [
            'locale' => 'en',
            'issueTitle' => 'Future',
            'issueVolume' => '',
            'issueNumber' => '',
            'issueYear' => '',
            'issueDescription' => '',
            'issuePublicationDate' => '',
        ];
    }

    /**
     * An explicit issuePublicationDate is left unchanged.
     */
    public function testFillMissingIssueDatesKeepsExplicitIssueDate(): void
    {
        $issue = new Issue();
        $issue->setId(1);
        $issue->setDatePublished('2018-01-01');

        $mock = $this->createIssueRepositoryMock();
        $mock->shouldReceive('edit')->never();

        IssueProcessor::fillMissingIssueDates([
            ['issue' => $issue, 'journalId' => 1],
        ]);

        $this->assertSame('2018-01-01', $issue->getDatePublished());
    }

    /**
     * When the issue has no article dates, fall back to the import date.
     */
    public function testFillMissingIssueDatesFallsBackToCurrentDateWhenNoArticleDates(): void
    {
        $issue = new Issue();
        $issue->setId(990002);

        $saved = null;
        $mock = $this->createIssueRepositoryMock();
        $mock->shouldReceive('edit')->once()->andReturnUsing(function ($edited) use (&$saved) {
            $saved = $edited->getDatePublished();
        });

        $before = Core::getCurrentDate();
        IssueProcessor::fillMissingIssueDates([
            ['issue' => $issue, 'journalId' => 1],
        ]);
        $after = Core::getCurrentDate();

        $this->assertNotEmpty($saved);
        $this->assertGreaterThanOrEqual($before, $saved);
        $this->assertLessThanOrEqual($after, $saved);
    }

    /**
     * After reordering, the most recent published issue becomes the journal current issue.
     */
    public function testReorderAssignsMostRecentIssueAsCurrent(): void
    {
        $older = new Issue();
        $older->setId(1);
        $older->setYear(2020);
        $older->setVolume(1);
        $older->setNumber('1');
        $older->setDatePublished('2020-01-01');

        $newer = new Issue();
        $newer->setId(2);
        $newer->setYear(2024);
        $newer->setVolume(1);
        $newer->setNumber('1');
        $newer->setDatePublished('2024-06-01');

        $collector = Mockery::mock(IssueCollector::class);
        $collector->shouldReceive('filterByContextIds')->with([5])->andReturnSelf();
        $collector->shouldReceive('filterByPublished')->with(true)->andReturnSelf();
        $collector->shouldReceive('getMany')->andReturn(new LazyCollection([$older, $newer]));

        $dao = Mockery::mock(IssueDAO::class);
        $dao->shouldReceive('moveCustomIssueOrder');
        $dao->shouldReceive('resequenceCustomIssueOrders');

        $this->backupContainerInstance(IssueRepository::class);
        $repo = Mockery::mock(IssueRepository::class)->makePartial();
        $repo->shouldReceive('getCollector')->andReturn($collector);
        $repo->dao = $dao;
        $assigned = null;
        $repo->shouldReceive('updateCurrent')->once()->andReturnUsing(function ($journalId, $issue) use (&$assigned) {
            $assigned = [$journalId, $issue->getId()];
        });
        app()->instance(IssueRepository::class, $repo);

        IssueProcessor::reorderAllIssuesForJournal(5);

        $this->assertSame([5, 2], $assigned);
    }

    /**
     * An issue already published later than the import stays current.
     */
    public function testReorderDoesNotAssignImportedIssueWhenExistingIssueIsMoreRecent(): void
    {
        $existing = new Issue();
        $existing->setId(10);
        $existing->setYear(2024);
        $existing->setVolume(1);
        $existing->setNumber('1');
        $existing->setDatePublished('2026-03-01');

        $imported = new Issue();
        $imported->setId(20);
        $imported->setYear(2025);
        $imported->setVolume(1);
        $imported->setNumber('1');
        $imported->setDatePublished('2024-06-01');

        $collector = Mockery::mock(IssueCollector::class);
        $collector->shouldReceive('filterByContextIds')->with([5])->andReturnSelf();
        $collector->shouldReceive('filterByPublished')->with(true)->andReturnSelf();
        $collector->shouldReceive('getMany')->andReturn(new LazyCollection([$existing, $imported]));

        $dao = Mockery::mock(IssueDAO::class);
        $dao->shouldReceive('moveCustomIssueOrder');
        $dao->shouldReceive('resequenceCustomIssueOrders');

        $this->backupContainerInstance(IssueRepository::class);
        $repo = Mockery::mock(IssueRepository::class)->makePartial();
        $repo->shouldReceive('getCollector')->andReturn($collector);
        $repo->dao = $dao;
        $assigned = false;
        $repo->shouldReceive('updateCurrent')->andReturnUsing(function () use (&$assigned) {
            $assigned = true;
        });
        app()->instance(IssueRepository::class, $repo);

        IssueProcessor::reorderAllIssuesForJournal(5, [20 => true]);

        $this->assertFalse($assigned);
    }

    /**
     * An imported issue published later than existing issues becomes current.
     */
    public function testReorderAssignsImportedIssueWhenItIsMoreRecentThanExisting(): void
    {
        $existing = new Issue();
        $existing->setId(10);
        $existing->setYear(2020);
        $existing->setVolume(1);
        $existing->setNumber('1');
        $existing->setDatePublished('2020-01-01');

        $imported = new Issue();
        $imported->setId(20);
        $imported->setYear(2024);
        $imported->setVolume(1);
        $imported->setNumber('1');
        $imported->setDatePublished('2024-06-01');

        $collector = Mockery::mock(IssueCollector::class);
        $collector->shouldReceive('filterByContextIds')->with([5])->andReturnSelf();
        $collector->shouldReceive('filterByPublished')->with(true)->andReturnSelf();
        $collector->shouldReceive('getMany')->andReturn(new LazyCollection([$existing, $imported]));

        $dao = Mockery::mock(IssueDAO::class);
        $dao->shouldReceive('moveCustomIssueOrder');
        $dao->shouldReceive('resequenceCustomIssueOrders');

        $this->backupContainerInstance(IssueRepository::class);
        $repo = Mockery::mock(IssueRepository::class)->makePartial();
        $repo->shouldReceive('getCollector')->andReturn($collector);
        $repo->dao = $dao;
        $assigned = null;
        $repo->shouldReceive('updateCurrent')->once()->andReturnUsing(function ($journalId, $issue) use (&$assigned) {
            $assigned = [$journalId, $issue->getId()];
        });
        app()->instance(IssueRepository::class, $repo);

        IssueProcessor::reorderAllIssuesForJournal(5, [20 => true]);

        $this->assertSame([5, 20], $assigned);
    }

    public function testSelectCurrentIssueUsesImportedIssueWhenItSortsFirst(): void
    {
        $existing = $this->makeIssue(10, 2020, '2020-01-01');
        $imported = $this->makeIssue(20, 2024, '2024-06-01');

        $selected = IssueProcessor::selectCurrentIssue([$existing, $imported], [20 => true], $existing);

        $this->assertSame(20, $selected?->getId());
    }

    public function testSelectCurrentIssueKeepsJournalCurrentWhenImportedIssueIsOlderByDate(): void
    {
        $existing = $this->makeIssue(10, 2024, '2026-03-01');
        $imported = $this->makeIssue(20, 2025, '2024-06-01');

        $selected = IssueProcessor::selectCurrentIssue([$existing, $imported], [20 => true], $existing);

        $this->assertSame(10, $selected?->getId());
    }

    public function testSelectCurrentIssueUsesJournalIssueWhenItSortsAheadOfTheCsv(): void
    {
        $journalIssue = $this->makeIssue(10, 2026, '2026-01-01');
        $imported = $this->makeIssue(20, 2024, '2024-06-01');
        $current = $this->makeIssue(3, 2019, '2019-01-01');

        $selected = IssueProcessor::selectCurrentIssue(
            [$journalIssue, $imported, $current],
            [20 => true],
            $current
        );

        $this->assertSame(10, $selected?->getId());
    }

    public function testSelectCurrentIssueKeepsCurrentWhenTheImportAddsNoIssues(): void
    {
        $current = $this->makeIssue(3, 2019, '2019-01-01');

        $selected = IssueProcessor::selectCurrentIssue([], [], $current);

        $this->assertSame(3, $selected?->getId());
    }

    public function testDescribeIssueIncludesTheFieldsTheIssueHas(): void
    {
        $issue = $this->makeIssue(1, 2024, '2024-06-01');
        $issue->setVolume(12);
        $issue->setNumber('3');
        $issue->setTitle('Special section', 'en');
        $issue->setData('locale', 'en');

        $label = IssueProcessor::describeIssue($issue);

        $this->assertStringContainsString('12', $label);
        $this->assertStringContainsString('3', $label);
        $this->assertStringContainsString('2024', $label);
        $this->assertStringContainsString('Special section', $label);
    }

    private function makeIssue(int $id, int $year, string $date): Issue
    {
        $issue = new Issue();
        $issue->setId($id);
        $issue->setYear($year);
        $issue->setVolume(1);
        $issue->setNumber('1');
        $issue->setDatePublished($date);

        return $issue;
    }
}
