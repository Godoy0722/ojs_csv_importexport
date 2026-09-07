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

namespace APP\plugins\importexport\csv\classes\processors;

use APP\author\Author;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\importexport\csv\classes\handlers\OrcidHandler;
use APP\publication\Publication;
use PKP\user\User;

class AuthorsProcessor
{
	public static function process(
        object $data,
        string $contactEmail,
        int $submissionId,
        Publication $publication,
        int $userGroupId,
        ?Publication $basePublication = null,
        ?User $usernameUser = null
    ) {
        if (empty($data->authors) && !is_null($basePublication)) {
            static::cloneAuthorsFromBasePublication($basePublication, $publication, $submissionId);
            return;
        }

		$authorsString = array_map('trim', explode(';', $data->authors));

        foreach ($authorsString as $index => $authorString) {
			$givenName = $familyName = $emailAddress = $orcid = $affiliation = null;
			$authorParts = array_map('trim', explode(',', $authorString));
			$givenName = $authorParts[0] ?? '';
            $familyName = $authorParts[1] ?? '';
            $emailAddress = $authorParts[2] ?? '';
            $orcid = $authorParts[3] ?? '';
            $affiliation = $authorParts[4] ?? '';

			if (empty($emailAddress)) {
				$emailAddress = $contactEmail;
			}

            if ($usernameUser && static::csvAuthorMatchesUser($givenName, $familyName, $emailAddress, $usernameUser, $data->locale)) {
                continue;
            }

            $author = Repo::author()->newDataObject();

            $author->setSubmissionId($submissionId);
            $author->setUserGroupId($userGroupId);
            $author->setGivenName($givenName, $data->locale);
            $author->setFamilyName($familyName, $data->locale);
            $author->setEmail($emailAddress);
            $author->setAffiliation($affiliation, $data->locale);
            $author->setData('publicationId', $publication->getId());

            $normalizedOrcid = OrcidHandler::normalize($orcid);
            if (!empty($normalizedOrcid)) {
                $author->setOrcid($normalizedOrcid);
            }

            $authorId = Repo::author()->add($author);

			if ($index === 0 && is_null($usernameUser)) {
                Repo::author()->edit($author, ['primaryContact' => true]);
                PublicationProcessor::updatePrimaryContactId($publication, $authorId);
			}
		}
	}

    public static function addAuthorFromUser(
        User $user,
        \APP\submission\Submission $submission,
        Publication $publication,
        Journal $journal,
        int $userGroupId
    ): int {
        $locale = $journal->getPrimaryLocale();

        $author = Repo::author()->newDataObject();
        $author->setSubmissionId($submission->getId());
        $author->setUserGroupId($userGroupId);
        $author->setEmail($user->getEmail());
        $author->setGivenName($user->getGivenName($locale) ?: $user->getGivenName($user->getDefaultLocale()), $locale);
        $author->setFamilyName($user->getFamilyName($locale) ?: $user->getFamilyName($user->getDefaultLocale()), $locale);
        $author->setData('publicationId', $publication->getId());

        $authorId = Repo::author()->add($author);

        Repo::author()->edit($author, ['primaryContact' => true]);
        PublicationProcessor::updatePrimaryContactId($publication, $authorId);

        return $authorId;
    }

    public static function updateUsernameAuthorLocale(User $user, Publication $publication, string $locale): void
    {
        $existingAuthors = $publication->getData('authors') ?: [];
        $userEmail = $user->getEmail();

        foreach ($existingAuthors as $author) {
            if (strcasecmp($author->getEmail(), $userEmail) === 0) {
                $givenName = $user->getGivenName($locale) ?: $user->getGivenName($user->getDefaultLocale());
                if ($givenName) {
                    $author->setGivenName($givenName, $locale);
                }

                $familyName = $user->getFamilyName($locale) ?: $user->getFamilyName($user->getDefaultLocale());
                if ($familyName) {
                    $author->setFamilyName($familyName, $locale);
                }

                Repo::author()->dao->update($author);
                return;
            }
        }
    }

    private static function csvAuthorMatchesUser(
        string $csvGivenName,
        string $csvFamilyName,
        string $csvEmail,
        User $user,
        string $locale
    ): bool {
        if (strcasecmp($csvEmail, $user->getEmail()) !== 0) {
            return false;
        }

        $userGivenName = $user->getGivenName($locale) ?: $user->getGivenName($user->getDefaultLocale()) ?: '';
        if (strcasecmp($csvGivenName, $userGivenName) !== 0) {
            return false;
        }

        $userFamilyName = $user->getFamilyName($locale) ?: $user->getFamilyName($user->getDefaultLocale()) ?: '';
        if (strcasecmp($csvFamilyName, $userFamilyName) !== 0) {
            return false;
        }

        return true;
    }

    /**
     * Clone authors from base publication to new versioned publication
     */
    private static function cloneAuthorsFromBasePublication(
        Publication $basePublication,
        Publication $newPublication,
        int $submissionId
    ): void
    {
        $authors = $basePublication->getData('authors') ?: [];
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

    /**
     * Process authors for multi-locale import (adds locale data to existing authors)
     */
    public static function processMultiLocale(
        object $data,
        string $contactEmail,
        int $submissionId,
        Publication $publication,
        int $userGroupId
    ): void {
        if (empty($data->authors)) {
            return;
        }

        $authorsString = array_map('trim', explode(';', $data->authors));
        $existingAuthors = $publication->getData('authors');

        foreach ($authorsString as $index => $authorString) {
            $givenName = $familyName = $emailAddress = $affiliation = null;
            $authorParts = array_map('trim', explode(',', $authorString));
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

                Repo::author()->dao->update($existingAuthor);
            } else {
                $author = static::addNewAuthor(
                    $submissionId,
                    $userGroupId,
                    $publication->getId(),
                    $givenName,
                    $familyName,
                    $emailAddress,
                    $affiliation,
                    $data
                );

                Repo::author()->add($author);
            }
        }
    }

    private static function addNewAuthor(
        int $submissionId,
        int $userGroupId,
        int $publicationId,
        string $givenName,
        string $familyName,
        string $emailAddress,
        ?string $affiliation,
        object $data
    ): Author {
        $author = Repo::author()->newDataObject();
        $author->setSubmissionId($submissionId);
        $author->setUserGroupId($userGroupId);
        $author->setGivenName($givenName, $data->locale);
        $author->setFamilyName($familyName, $data->locale);
        $author->setEmail($emailAddress);
        $author->setData('publicationId', $publicationId);

        if ($affiliation) {
            $author->setAffiliation($affiliation, $data->locale);
        }

        return $author;
    }
}
