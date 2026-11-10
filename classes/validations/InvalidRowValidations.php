<?php

/**
 * @file plugins/importexport/csv/classes/validations/InvalidRowValidations.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvalidRowValidations
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate all necessary requirements for a CSV row to be valid
 */

namespace APP\plugins\importexport\csv\classes\validations;

use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\validations\InvalidRowValidations as SharedInvalidRowValidations;
use APP\submission\Submission;
use APP\subscription\SubscriptionType;

class InvalidRowValidations extends SharedInvalidRowValidations
{
    /**
     * Words only. An empty cell is published. Numbers and any other word are rejected.
     *
     * @throws RowValidationException
     */
    public static function resolveSubmissionStatus(?string $status): int
    {
        $status = strtolower(trim((string) $status));

        return match ($status) {
            '', 'published' => Submission::STATUS_PUBLISHED,
            'queued' => Submission::STATUS_QUEUED,
            'scheduled' => Submission::STATUS_SCHEDULED,
            default => throw new RowValidationException(__('plugins.importexport.csv.invalidSubmissionStatus')),
        };
    }

    /**
     * A published row keeps a valid publication date. A queued or scheduled row
     * stores no publication date. A filled cell on those rows is kept separately
     * as the submission date.
     *
     * @throws RowValidationException
     */
    public static function normalizePublicationDate(int $status, ?string $date): ?string
    {
        if ($status !== Submission::STATUS_PUBLISHED) {
            return null;
        }

        return static::validateDateFormat($date ?? '', 'datePublished', true);
    }

    /**
     * The datePublished cell of a queued or scheduled row, used only as dateSubmitted.
     * The publication date stays empty. An empty cell returns null.
     *
     * @throws RowValidationException
     */
    public static function submissionDateFromPublicationCell(?string $date): ?string
    {
        return static::validateDateFormat($date, 'datePublished', false);
    }

    /**
     * Validates if the subscription dates are valid.
     *
     * @throws RowValidationException
     */
    public static function validateSubscriptionDates(string $startDate, string $endDate, ?string $dateFormat = 'Y-m-d'): void
    {
        $startDateObj = \DateTime::createFromFormat($dateFormat, $startDate);
        if (!$startDateObj) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidStartDate', ['date' => $startDate]));
        }

        $endDateObj = \DateTime::createFromFormat($dateFormat, $endDate);
        if (!$endDateObj) {
            throw new RowValidationException(__('plugins.importexport.csv.invalidEndDate', ['date' => $endDate]));
        }

        if ($endDateObj <= $startDateObj) {
            throw new RowValidationException(__('plugins.importexport.csv.endDateBeforeStartDate'));
        }
    }

    /**
     * Validates if the subscription type is valid.
     *
     * @throws RowValidationException
     */
    public static function validateSubscriptionType(?SubscriptionType $subscriptionType, int $subscriptionTypeId): void
    {
        if (!$subscriptionType) {
            throw new RowValidationException(__('plugins.importexport.csv.subscriptionTypeDoesntExist', ['subscriptionTypeId' => $subscriptionTypeId]));
        }
    }

    /**
     * Validates that subscription fields are all provided when any one is present.
     *
     * @throws RowValidationException
     */
    public static function validateSubscriptionFields(object $data): void
    {
        if (!RequiredUserHeaders::validateSubscriptionFields($data)) {
            throw new RowValidationException(__('plugins.importexport.csv.missingSubscriptionFields', ['email' => $data->email]));
        }
    }
}
