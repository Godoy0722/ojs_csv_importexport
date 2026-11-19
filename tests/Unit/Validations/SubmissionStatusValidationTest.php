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
 * @brief The OJS required-field and column-count checks for article rows (the status and date helpers are tested in shared)
 */

namespace APP\plugins\importexport\csv\tests\Unit\Validations;

use APP\plugins\importexport\csv\classes\validations\RequiredIssueHeaders;
use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use APP\plugins\importexport\csv\shared\validations\InvalidRowValidations as SharedInvalidRowValidations;
use APP\submission\Submission;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RequiredIssueHeaders::class)]
class SubmissionStatusValidationTest extends BaseTestCase
{
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

}
