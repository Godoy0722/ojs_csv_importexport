<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/SubmissionDateTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDateTest
 *
 * @brief A queued or scheduled row keeps datePublished empty and uses that cell as dateSubmitted
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\journal\Journal;
use APP\plugins\importexport\csv\classes\processors\SubmissionProcessor;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use APP\publication\Publication;
use APP\submission\Submission;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SubmissionProcessor::class)]
class SubmissionDateTest extends BaseTestCase
{
    public function testQueuedDateIsStoredAsDateSubmitted(): void
    {
        $submission = $this->importQueued([
            'datePublished' => null,
            'dateSubmitted' => '2024-06-01',
        ]);

        $this->assertSame('2024-06-01', $submission->getData('dateSubmitted'));
        $this->assertSame(Submission::STATUS_QUEUED, (int) $submission->getData('status'));
    }

    public function testPublishedDateIsAlsoTheSubmissionDate(): void
    {
        $submission = $this->importQueued([
            'datePublished' => '2024-10-15',
            'dateSubmitted' => null,
            'resolvedStatus' => Submission::STATUS_PUBLISHED,
        ]);

        $this->assertSame('2024-10-15', $submission->getData('dateSubmitted'));
    }

    private function importQueued(array $values): Submission
    {
        $captured = null;
        $repository = $this->mockSubmissionRepository();
        $repository->shouldReceive('add')->once()->andReturnUsing(function ($submission) use (&$captured) {
            $captured = $submission;
            return 5;
        });
        $repository->shouldReceive('get')->with(5)->andReturnUsing(function () use (&$captured) {
            return $captured;
        });

        $journal = new Journal();
        $journal->setId(1);

        SubmissionProcessor::process((object) array_merge([
            'locale' => 'en',
            'articleAbstract' => '',
            'resolvedStatus' => Submission::STATUS_QUEUED,
        ], $values), new Publication(), $journal);

        return $captured;
    }
}
