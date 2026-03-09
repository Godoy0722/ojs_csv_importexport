<?php

/**
 * @file plugins/importexport/csv/classes/processors/AuthorsProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the authors data into the database.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;
use PKP\Plugins\ImportExport\CSV\Classes\Handlers\OrcidHandler;

class AuthorsProcessor
{
    /**
	 * Process data for Submission authors
	 *
	 * @param object $data
	 * @param string $contactEmail
	 * @param int $submissionId
	 * @param \Publication $publication
	 * @param int $userGroupId
	 * @param ?\Publication $basePublication
	 *
	 * @return void
	 */
	public static function process($data, $contactEmail, $submissionId, $publication, $userGroupId, $basePublication = null)
    {
		if (empty($data->authors) && !is_null($basePublication)) {
            self::cloneAuthorsFromBasePublication($basePublication, $publication, $submissionId);
            return;
        }

		$authorDao = CachedDaos::getAuthorDao();
		$authorsString = self::splitRespectingQuotes($data->authors, ';');

        foreach ($authorsString as $index => $authorString) {
            /**
             * Examine the author string. The pattern is: "GivenName,FamilyName,email@email.com,orcid,affiliation".
             *
             * If the article has more than one author, it must separate the authors by a semicolon (;). Example:
             * "<AUTHOR_1_INFORMATION>;<AUTHOR_2_INFORMATION>".
             *
             * Fields familyName, email, orcid, and affiliation are optional and can be left as empty fields. E.g.:
             * "GivenName,,,,".
             *
             * Affiliations containing commas or semicolons must be wrapped in double quotes. E.g.:
             * "GivenName,FamilyName,email@email.com,,"Dept of Medicine, University of Example, City, Country"".
             *
             * By default, if an author doesn't have an email, the primary contact email will be used in its place.
             */
			$givenName = $familyName = $emailAddress = $orcid = $affiliation = null;
			$authorParts = self::splitRespectingQuotes($authorString, ',', true);
            $givenName = $authorParts[0] ?? '';
            $familyName = $authorParts[1] ?? '';
            $emailAddress = $authorParts[2] ?? '';
            $orcid = $authorParts[3] ?? '';
            $affiliation = $authorParts[4] ?? '';

			if (empty($emailAddress)) {
				$emailAddress = $contactEmail;
			}

			/** @var \Author $author */
			$author = $authorDao->newDataObject();
			$author->setSubmissionId($submissionId);
			$author->setUserGroupId($userGroupId);
			$author->setGivenName($givenName, $data->locale);
			$author->setFamilyName($familyName, $data->locale);
			$author->setEmail($emailAddress);
            $author->setAffiliation($affiliation, $data->locale);
			$author->setData('publicationId', $publication->getId());

			import('plugins.importexport.csv.classes.handlers.OrcidHandler');
			$normalizedOrcid = OrcidHandler::normalize($orcid);
            if (!empty($normalizedOrcid)) {
                $author->setOrcid($normalizedOrcid);
            }

			$authorDao->insertObject($author);

			if (!$index) {
				$author->setPrimaryContact(true);
				$authorDao->updateObject($author);

                PublicationProcessor::updatePrimaryContactId($publication, $author->getId());
			}
		}
	}

	/**
     * Clone authors from base publication to new versioned publication
	 *
	 * @param \Publication $basePublication
	 * @param \Publication $newPublication
	 * @param int $submissionId
	 *
	 * @return void
     */
    private static function cloneAuthorsFromBasePublication($basePublication, $newPublication, $submissionId)
    {
        $authors = $basePublication->getData('authors');
        if (empty($authors)) {
            return;
        }

		$authorDao = CachedDaos::getAuthorDao();
        foreach ($authors as $author) {
            $newAuthor = clone $author;
            $newAuthor->setData('id', null);
            $newAuthor->setData('publicationId', $newPublication->getId());
            $newAuthor->setSubmissionId($submissionId);

            $newAuthorId = $authorDao->insertObject($newAuthor);

            if ($author->getId() === $basePublication->getData('primaryContactId')) {
                PublicationProcessor::updatePrimaryContactId($newPublication, $newAuthorId);
            }
        }
    }

	/**
	 * Split a string by a delimiter while respecting double-quoted regions.
	 * Unlike str_getcsv, this handles quotes that appear mid-field (e.g., after
	 * preceding unquoted content), which is needed for the authors format where
	 * affiliations are quoted within a comma/semicolon-delimited author entry.
	 *
	 * @param string $input
	 * @param string $delimiter
	 *
	 * @return string[]
	 */
	private static function splitRespectingQuotes($input, $delimiter, $stripQuotes = false)
	{
		$parts = [];
		$current = '';
		$inQuotes = false;
		$len = strlen($input);

		for ($i = 0; $i < $len; $i++) {
			if ($input[$i] === '"') {
				$inQuotes = !$inQuotes;
				if (!$stripQuotes) {
					$current .= $input[$i];
				}
				continue;
			}

			if (!$inQuotes && $input[$i] === $delimiter) {
				$parts[] = trim($current);
				$current = '';
				continue;
			}

			$current .= $input[$i];
		}

		$parts[] = trim($current);

		return $parts;
	}

	/**
     * Process authors for multi-locale import (adds locale data to existing authors)
	 *
	 * @param object $data
	 * @param string $contactEmail
	 * @param int $submissionId
	 * @param \Publication $publication
	 * @param int $userGrouppId
	 *
	 * @return void
     */
    public static function processMultiLocale($data, $contactEmail, $submissionId, $publication, $userGroupId)
	{
        if (empty($data->authors)) {
            return; // No new author data to add
        }

        $authorsString = self::splitRespectingQuotes($data->authors, ';');
        /** @var Author[] */
        $existingAuthors = $publication->getData('authors');

        foreach ($authorsString as $index => $authorString) {
            $givenName = $familyName = $emailAddress = $affiliation = null;
            $authorParts = self::splitRespectingQuotes($authorString, ',', true);
            $givenName = $authorParts[0] ?? '';
            $familyName = $authorParts[1] ?? '';
            $emailAddress = $authorParts[2] ?? '';
            $affiliation = $authorParts[3] ?? '';

            if (empty($emailAddress)) {
                $emailAddress = $contactEmail;
            }

            $existingAuthor = null;
            if (!empty($existingAuthors)) {
                foreach ($existingAuthors as $author) {
                    if ($author->getEmail() === $emailAddress) {
                        $existingAuthor = $author;
                        break;
                    }
                }
            }

            if ($existingAuthor) {
                $existingAuthor->setGivenName($givenName, $data->locale);
                $existingAuthor->setFamilyName($familyName, $data->locale);

                if ($affiliation) {
                    $existingAuthor->setAffiliation($affiliation, $data->locale);
                }

				CachedDaos::getAuthorDao()->updateObject($existingAuthor);

                continue;
            }

			$authorDao = CachedDaos::getAuthorDao();

			/** @var \Author $author */
            $author = $authorDao->newDataObject();
            $author->setSubmissionId($submissionId);
            $author->setUserGroupId($userGroupId);
            $author->setGivenName($givenName, $data->locale);
            $author->setFamilyName($familyName, $data->locale);
            $author->setEmail($emailAddress);
            $author->setData('publicationId', $publication->getId());

            if ($affiliation) {
                $author->setAffiliation($affiliation, $data->locale);
            }

			$authorDao->insertObject($author);
        }
    }
}
