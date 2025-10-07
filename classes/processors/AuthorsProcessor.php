<?php

/**
 * @file plugins/importexport/csv/classes/processors/AuthorsProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the authors data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\publication\Publication;

class AuthorsProcessor
{
	public static function process(
        object $data,
        string $contactEmail,
        int $submissionId,
        Publication $publication,
        int $userGroupId,
        ?Publication $basePublication = null
    )
    {
        if (empty($data->authors) && !is_null($basePublication)) {
            self::cloneAuthorsFromBasePublication($basePublication, $publication, $submissionId);
            return;
        }

		$authorsString = array_map('trim', explode(';', $data->authors));

        foreach ($authorsString as $index => $authorString) {
            /**
             * Examine the author string. The pattern is: "GivenName,FamilyName,email@email.com,affiliation".
             *
             * If the article has more than one author, it must separate the authors by a semicolon (;). Example:
             * "<AUTHOR_1_INFORMATION>;<AUTHOR_2_INFORMATION>".
             *
             * Fields familyName, email, and affiliation are optional and can be left as empty fields. E.g.:
             * "GivenName,,,".
             *
             * By default, if an author doesn't have an email, the primary contact email will be used in its place.
             */
			$givenName = $familyName = $emailAddress = $affiliation = null;
			$authorParts = array_map('trim', explode(',', $authorString));
			$givenName = $authorParts[0] ?? '';
			$familyName = $authorParts[1] ?? '';
			$emailAddress = $authorParts[2] ?? '';
			$affiliation = $authorParts[3] ?? '';

			if (empty($emailAddress)) {
				$emailAddress = $contactEmail;
			}

            $author = Repo::author()->newDataObject();

            $author->setSubmissionId($submissionId);
            $author->setUserGroupId($userGroupId);
            $author->setGivenName($givenName, $data->locale);
            $author->setFamilyName($familyName, $data->locale);
            $author->setEmail($emailAddress);
            $author->setData('publicationId', $publication->getId());

            if ($affiliation) {
                $affiliationEntity = Repo::affiliation()->newDataObject();
                $affiliationEntity->setName((string) $affiliation, $data->locale);

                $author->addAffiliation($affiliationEntity);
            }

            $authorId = Repo::author()->add($author);

			if ($index === 0) {
                Repo::author()->edit($author, ['primaryContact' => true]);
                PublicationProcessor::updatePrimaryContactId($publication, $authorId);
			}
		}
	}

    private static function cloneAuthorsFromBasePublication(
        Publication $basePublication,
        Publication $newPublication,
        int $submissionId
    ): void
    {
        /** @var LazyCollection */
        $authors = $basePublication->getData('authors');
        $authors->all();
        if (empty($authors)) {
            return;
        }

        foreach ($authors as $author) {
            $newAuthor = clone $author;
            $newAuthor->setData('id', null);
            $newAuthor->setData('publicationId', $newPublication->getId());
            $newAuthor->setSubmissionId($submissionId);
            $newAuthorId = Repo::author()->add($newAuthor);

            if ($author->getId() === $basePublication->getData('primaryContactId')) {
                PublicationProcessor::updatePrimaryContactId($newPublication, $newAuthorId);
            }
        }
    }
}
