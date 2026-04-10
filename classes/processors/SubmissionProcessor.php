<?php

/**
 * @file plugins/importexport/csv/classes/processors/SubmissionProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the submission data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\journal\Journal;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\plugins\importexport\csv\shared\processors\SubmissionProcessor as SharedSubmissionProcessor;
class SubmissionProcessor extends SharedSubmissionProcessor
{
    public static function process(object $data, Publication $publication, Journal $journal): Submission
    {
        $normalizedAbstract = PublicationProcessor::normalizeAbstractToHtml($data->articleAbstract);
        return parent::processCommons($data->locale, $publication, $journal, $normalizedAbstract, $data->datePublished);
    }
}
