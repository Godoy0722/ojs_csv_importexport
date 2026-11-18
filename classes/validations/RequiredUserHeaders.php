<?php

/**
 * @file plugins/importexport/csv/classes/validations/RequiredUserHeaders.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredUserHeaders
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate headers in the user CSV files
 */

namespace APP\plugins\importexport\csv\classes\validations;

class RequiredUserHeaders
{
    static $userHeaders = [
        'journalPath',
        'firstname',
        'lastname',
        'email',
        'affiliation',
        'country',
        'username',
        'tempPassword',
        'roles',
        'reviewInterests',
        'subscriptionType',
        'startDate',
        'endDate',
        'orcid'
    ];

    static $userRequiredHeaders = [
        'journalPath',
        'firstname',
        'email',
        'roles',
    ];

    static $subscriptionHeaders = [
        'subscriptionType',
        'startDate',
        'endDate'
    ];

    public static function validateRowHasAllFields(array $row): bool
    {
        return count($row) === count(self::$userHeaders);
    }

    /**
     * @return string[] Required column names that are empty on this row
     */
    public static function missingRequiredFields(object $row): array
    {
        $missing = [];
        foreach (self::$userRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                $missing[] = $requiredHeader;
            }
        }

        return $missing;
    }

    public static function validateRowHasAllRequiredFields(object $row): bool
    {
        return self::missingRequiredFields($row) === [];
    }

    /**
     * @return string[] Subscription columns still empty when the group is only partly filled
     */
    public static function missingSubscriptionFields(object $row): array
    {
        $filled = [
            'subscriptionType' => !empty($row->subscriptionType),
            'startDate' => !empty($row->startDate),
            'endDate' => !empty($row->endDate),
        ];

        if (!in_array(true, $filled, true)) {
            return [];
        }

        $missing = [];
        foreach ($filled as $field => $isFilled) {
            if (!$isFilled) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    public static function validateSubscriptionFields(object $row): bool
    {
        return self::missingSubscriptionFields($row) === [];
    }
}
