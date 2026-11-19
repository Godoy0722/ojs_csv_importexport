<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/PublicationStatusTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationStatusTest
 *
 * @brief Publication status and date follow the row, including a later version
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use APP\publication\Publication;
use APP\submission\Submission;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PublicationProcessor::class)]
class PublicationStatusTest extends BaseTestCase
{
    public function testInitialQueuedPublicationStoresNoDate(): void
    {
        $this->mockPublicationRepository();

        $publication = PublicationProcessor::createInitialPublication((object) [
            'version' => 1,
            'locale' => 'en',
            'articleTitle' => 'Queued article',
            'datePublished' => '2024-01-15',
            'resolvedStatus' => Submission::STATUS_QUEUED,
        ]);

        $this->assertSame(Submission::STATUS_QUEUED, (int) $publication->getData('status'));
        $this->assertEmpty($publication->getData('datePublished'));
    }

    public function testScheduledVersionDoesNotInheritTheBaseDate(): void
    {
        $repository = $this->mockPublicationRepository();
        $repository->dao->shouldReceive('update')->andReturn(true);
        $repository->shouldReceive('get')->andReturn(null);

        $base = new Publication();
        $base->setId(10);
        $base->setData('datePublished', '2020-05-01');
        $base->setData('status', Submission::STATUS_PUBLISHED);

        $version = new Publication();
        $version->setId(11);
        $version->setData('datePublished', '2020-05-01');

        $updated = PublicationProcessor::processVersionedPublication($version, (object) [
            'version' => 2,
            'locale' => 'en',
            'datePublished' => '2024-01-15',
            'resolvedStatus' => Submission::STATUS_SCHEDULED,
            'articleTitle' => '',
            'articleSubtitle' => '',
            'articleAbstract' => '',
            'coverage' => '',
            'copyrightHolder' => '',
            'copyrightYear' => '',
            'licenseUrl' => '',
            'doi' => '',
        ], $base, '/tmp');

        $this->assertSame(Submission::STATUS_SCHEDULED, (int) $updated->getData('status'));
        $this->assertNull($updated->getData('datePublished'));
    }

    public function testPublishedVersionKeepsItsOwnDate(): void
    {
        $repository = $this->mockPublicationRepository();
        $repository->dao->shouldReceive('update')->andReturn(true);
        $repository->shouldReceive('get')->andReturn(null);

        $base = new Publication();
        $base->setId(10);
        $base->setData('datePublished', '2020-05-01');

        $version = new Publication();
        $version->setId(12);

        $updated = PublicationProcessor::processVersionedPublication($version, (object) [
            'version' => 2,
            'locale' => 'en',
            'datePublished' => '2024-03-01',
            'resolvedStatus' => Submission::STATUS_PUBLISHED,
            'articleTitle' => 'Revised',
            'articleSubtitle' => '',
            'articleAbstract' => '',
            'coverage' => '',
            'copyrightHolder' => '',
            'copyrightYear' => '',
            'licenseUrl' => '',
            'doi' => '',
        ], $base, '/tmp');

        $this->assertSame(Submission::STATUS_PUBLISHED, (int) $updated->getData('status'));
        $this->assertSame('2024-03-01', $updated->getData('datePublished'));
    }
}
