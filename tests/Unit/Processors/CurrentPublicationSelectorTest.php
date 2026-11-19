<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/CurrentPublicationSelectorTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CurrentPublicationSelectorTest
 *
 * @brief Current publication and submission status for multi-version articles
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\CurrentPublicationSelector;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use APP\publication\Publication;
use APP\submission\Submission;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CurrentPublicationSelector::class)]
class CurrentPublicationSelectorTest extends BaseTestCase
{
    public function testSingleVersionIsLeftAlone(): void
    {
        $mock = $this->mockSubmissionRepository();
        $mock->shouldReceive('edit')->never();
        $entry = $this->entry(1, 10, Submission::STATUS_QUEUED);

        CurrentPublicationSelector::apply([
            1 => ['en' => $entry],
        ]);

        $this->assertSame(Submission::STATUS_QUEUED, (int) $entry['publication']->getData('status'));
    }

    public function testPublishedLaterVersionBecomesCurrent(): void
    {
        $saved = $this->captureEdit();

        CurrentPublicationSelector::apply([
            1 => ['en' => $this->entry(1, 10, Submission::STATUS_SCHEDULED)],
            2 => ['en' => $this->entry(2, 20, Submission::STATUS_PUBLISHED)],
        ]);

        $this->assertSame(Submission::STATUS_PUBLISHED, $saved->params['status']);
        $this->assertSame(20, $saved->params['currentPublicationId']);
    }

    public function testPublishedEarlierVersionStaysCurrent(): void
    {
        $saved = $this->captureEdit();

        CurrentPublicationSelector::apply([
            1 => ['en' => $this->entry(1, 10, Submission::STATUS_PUBLISHED)],
            2 => ['en' => $this->entry(2, 20, Submission::STATUS_QUEUED)],
        ]);

        $this->assertSame(Submission::STATUS_PUBLISHED, $saved->params['status']);
        $this->assertSame(10, $saved->params['currentPublicationId']);
    }

    public function testTwoScheduledVersionsSelectTheHigherOne(): void
    {
        $saved = $this->captureEdit();

        CurrentPublicationSelector::apply([
            1 => ['en' => $this->entry(1, 10, Submission::STATUS_SCHEDULED)],
            2 => ['en' => $this->entry(2, 20, Submission::STATUS_SCHEDULED)],
        ]);

        $this->assertSame(Submission::STATUS_SCHEDULED, $saved->params['status']);
        $this->assertSame(20, $saved->params['currentPublicationId']);
    }

    public function testTwoQueuedVersionsStayQueuedOnTheHigherOne(): void
    {
        $saved = $this->captureEdit();

        CurrentPublicationSelector::apply([
            1 => ['en' => $this->entry(1, 10, Submission::STATUS_QUEUED)],
            2 => ['en' => $this->entry(2, 20, Submission::STATUS_QUEUED)],
        ]);

        $this->assertSame(Submission::STATUS_QUEUED, $saved->params['status']);
        $this->assertSame(20, $saved->params['currentPublicationId']);
    }

    private function captureEdit(): \stdClass
    {
        $saved = new \stdClass();
        $saved->params = [];
        $mock = $this->mockSubmissionRepository();
        $mock->shouldReceive('edit')->once()->andReturnUsing(function ($submission, $params) use ($saved) {
            $saved->params = $params;
        });

        return $saved;
    }

    /**
     * @return array{publication: Publication, submission: Submission}
     */
    private function entry(int $version, int $publicationId, int $status): array
    {
        $publication = new Publication();
        $publication->setId($publicationId);
        $publication->setData('version', $version);
        $publication->setData('status', $status);

        $submission = new Submission();
        $submission->setId(1);

        return [
            'publication' => $publication,
            'submission' => $submission,
        ];
    }
}
