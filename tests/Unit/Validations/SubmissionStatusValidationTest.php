<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Validations/SubmissionStatusValidationTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionStatusValidationTest
 *
 * @brief Status words, publication dates, private files, and the exact column count
 */

namespace APP\plugins\importexport\csv\tests\Unit\Validations;

use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredIssueHeaders;
use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use APP\plugins\importexport\csv\shared\validations\InvalidRowValidations as SharedInvalidRowValidations;
use APP\submission\Submission;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvalidRowValidations::class)]
class SubmissionStatusValidationTest extends BaseTestCase
{
    public function testEmptyAndPublishedWordsResolveToPublished(): void
    {
        $this->assertSame(Submission::STATUS_PUBLISHED, InvalidRowValidations::resolveSubmissionStatus(null));
        $this->assertSame(Submission::STATUS_PUBLISHED, InvalidRowValidations::resolveSubmissionStatus(''));
        $this->assertSame(Submission::STATUS_PUBLISHED, InvalidRowValidations::resolveSubmissionStatus('published'));
        $this->assertSame(Submission::STATUS_PUBLISHED, InvalidRowValidations::resolveSubmissionStatus('Published'));
        $this->assertSame(Submission::STATUS_PUBLISHED, InvalidRowValidations::resolveSubmissionStatus('PUBLISHED'));
    }

    public function testQueuedAndScheduledResolveInAnyCase(): void
    {
        $this->assertSame(Submission::STATUS_QUEUED, InvalidRowValidations::resolveSubmissionStatus('queued'));
        $this->assertSame(Submission::STATUS_QUEUED, InvalidRowValidations::resolveSubmissionStatus('Queued'));
        $this->assertSame(Submission::STATUS_SCHEDULED, InvalidRowValidations::resolveSubmissionStatus('scheduled'));
        $this->assertSame(Submission::STATUS_SCHEDULED, InvalidRowValidations::resolveSubmissionStatus('SCHEDULED'));
    }

    public function testUnknownStatusWordsAreRejected(): void
    {
        foreach (['1', '3', '5', 'declined', 'schedule', 'published article'] as $value) {
            try {
                InvalidRowValidations::resolveSubmissionStatus($value);
                $this->fail("Expected rejection for {$value}");
            } catch (RowValidationException $exception) {
                $this->assertSame(__('plugins.importexport.csv.invalidSubmissionStatus'), $exception->getMessage());
            }
        }
    }

    public function testPublishedDateIsKeptAndABadDateIsRejected(): void
    {
        $this->assertSame(
            '2024-01-15',
            InvalidRowValidations::normalizePublicationDate(Submission::STATUS_PUBLISHED, '2024-01-15')
        );

        try {
            InvalidRowValidations::normalizePublicationDate(Submission::STATUS_PUBLISHED, 'not-a-date');
            $this->fail('Expected an invalid date');
        } catch (RowValidationException $exception) {
            $this->assertSame(
                __('plugins.importexport.csv.invalidDateFormat', ['fieldName' => 'datePublished']),
                $exception->getMessage()
            );
        }
    }

    public function testEmptyPublishedDateFailsTheRequiredFieldCheck(): void
    {
        $row = (object) [
            'journalPath' => 'liv',
            'locale' => 'en',
            'articleTitle' => 'Title',
            'authors' => 'Sarah,Johnson,sarah.j@stats.edu',
            'datePublished' => '',
            'resolvedStatus' => Submission::STATUS_PUBLISHED,
            'version' => '',
            'versionIdentifier' => '',
        ];

        try {
            SharedInvalidRowValidations::validateRowHasAllRequiredFieldsCommons(
                $row,
                fn ($candidate) => RequiredIssueHeaders::missingRequiredFields($candidate, [])
            );
            $this->fail('Expected a required-field failure');
        } catch (RowValidationException $exception) {
            $this->assertSame(
                __('plugins.importexport.csv.verifyRequiredFieldsForThisRow', ['fields' => 'datePublished']),
                $exception->getMessage()
            );
        }
    }

    public function testQueuedAndScheduledRowsStoreNoPublicationDate(): void
    {
        $this->assertNull(InvalidRowValidations::normalizePublicationDate(Submission::STATUS_QUEUED, ''));
        $this->assertNull(InvalidRowValidations::normalizePublicationDate(Submission::STATUS_QUEUED, '2024-01-15'));
        $this->assertNull(InvalidRowValidations::normalizePublicationDate(Submission::STATUS_SCHEDULED, ''));
        $this->assertNull(InvalidRowValidations::normalizePublicationDate(Submission::STATUS_SCHEDULED, 'not-a-date'));
    }

    public function testUnpublishedPublicationCellBecomesTheSubmissionDate(): void
    {
        $this->assertNull(InvalidRowValidations::submissionDateFromPublicationCell(''));
        $this->assertNull(InvalidRowValidations::submissionDateFromPublicationCell(null));
        $this->assertSame('2024-01-15', InvalidRowValidations::submissionDateFromPublicationCell('2024-01-15'));
        $this->assertSame('2024-01-15', InvalidRowValidations::submissionDateFromPublicationCell('15/01/2024'));

        try {
            InvalidRowValidations::submissionDateFromPublicationCell('not-a-date');
            $this->fail('Expected an invalid submission date');
        } catch (RowValidationException $exception) {
            $this->assertSame(
                __('plugins.importexport.csv.invalidDateFormat', ['fieldName' => 'datePublished']),
                $exception->getMessage()
            );
        }
    }

    public function testColumnCountMatchesTheHeaderList(): void
    {
        $expected = count(RequiredIssueHeaders::$issueHeaders);
        SharedInvalidRowValidations::validateRowContainAllFields(array_fill(0, $expected, ''), $expected);

        try {
            SharedInvalidRowValidations::validateRowContainAllFields(array_fill(0, $expected - 1, ''), $expected);
            $this->fail('Expected a short row');
        } catch (RowValidationException $exception) {
            $this->assertSame(
                __('plugins.importexport.csv.rowDoesntContainAllFields', [
                    'count' => $expected - 1,
                    'expected' => $expected,
                ]),
                $exception->getMessage()
            );
            $this->assertStringNotContainsString('dryModeCurrentIssue', $exception->getMessage());
        }

        try {
            SharedInvalidRowValidations::validateRowContainAllFields(array_fill(0, $expected + 1, ''), $expected);
            $this->fail('Expected a long row');
        } catch (RowValidationException $exception) {
            $this->assertSame(
                __('plugins.importexport.csv.rowContainsTooManyFields', [
                    'count' => $expected + 1,
                    'expected' => $expected,
                ]),
                $exception->getMessage()
            );
        }
    }

    public function testSubmissionFilesCheck(): void
    {
        $sourceDir = sys_get_temp_dir() . '/csv-status-' . uniqid();
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/a.pdf', 'a');
        file_put_contents($sourceDir . '/b.pdf', 'b');

        try {
            SharedInvalidRowValidations::validateSubmissionFiles('a.pdf', $sourceDir);
            SharedInvalidRowValidations::validateSubmissionFiles('a.pdf;b.pdf', $sourceDir);

            try {
                SharedInvalidRowValidations::validateSubmissionFiles('a.pdf;missing.pdf', $sourceDir);
                $this->fail('Expected a missing file');
            } catch (RowValidationException $exception) {
                $this->assertSame(
                    __('plugins.importexport.csv.invalidSubmissionFile', ['filename' => 'missing.pdf']),
                    $exception->getMessage()
                );
            }

            try {
                SharedInvalidRowValidations::validateSubmissionFiles('../secret.pdf', $sourceDir);
                $this->fail('Expected a path escape');
            } catch (RowValidationException $exception) {
                $this->assertSame(
                    __('plugins.importexport.csv.filePathEscapesSourceDir', ['filename' => '../secret.pdf']),
                    $exception->getMessage()
                );
            }
        } finally {
            @unlink($sourceDir . '/a.pdf');
            @unlink($sourceDir . '/b.pdf');
            @rmdir($sourceDir);
        }
    }
}
