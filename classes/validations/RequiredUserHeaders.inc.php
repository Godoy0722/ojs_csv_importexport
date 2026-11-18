<?php

/**
 * @file plugins/importexport/csv/classes/validations/RequiredUserHeaders.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredUserHeaders
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate headers in the user CSV files
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Validations;

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
		'orcid',
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

    /**
     * Validates whether the row contains all headers.
	 *
	 * @param array $row
	 *
	 * @return bool
     */
    public static function validateRowHasAllFields($row)
    {
        return count($row) === count(self::$userHeaders);
    }

    /**
     * Validates whether the row contains all required headers.
	 *
	 * @param object $row
	 *
	 * @return bool
     */
    /**
     * Required column names that are empty on this row.
     *
     * @param object $row
     *
     * @return array
     */
    public static function missingRequiredFields($row)
    {
        $missing = [];
        foreach (self::$userRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                $missing[] = $requiredHeader;
            }
        }

        return $missing;
    }

    /**
     * Validates whether the row contains all required headers.
     *
     * @param object $row
     *
     * @return bool
     */
    public static function validateRowHasAllRequiredFields($row)
    {
        return self::missingRequiredFields($row) === [];
    }

    /**
     * Validates if the row has the necessary subscription fields when a subscription_type_id is provided
     *
     * @param object $row
     * @return bool
     */
    /**
     * Subscription columns still empty when the group is only partly filled.
     *
     * @param object $row
     *
     * @return array
     */
    public static function missingSubscriptionFields($row)
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

    /**
     * Validates if the row has the necessary subscription fields when a subscription_type_id is provided
     *
     * @param object $row
     * @return bool
     */
    public static function validateSubscriptionFields($row)
    {
        return self::missingSubscriptionFields($row) === [];
    }
}
