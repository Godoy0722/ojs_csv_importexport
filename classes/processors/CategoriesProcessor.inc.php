<?php

/**
 * @file plugins/importexport/csv/classes/processors/CategoriesProcessor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoriesProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the categories data into the database.
 */

namespace PKP\Plugins\ImportExport\CSV\Classes\Processors;

use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedDaos;
use PKP\Plugins\ImportExport\CSV\Classes\CachedAttributes\CachedEntities;

class CategoriesProcessor
{
    /**
	 * Processes data for Submission categories. If there's no category with the name provided, a new one will be created.
	 *
	 * @param string $categories
	 * @param string $locale
	 * @param int $journalId
	 * @param int $publicationId
	 *
	 * @return void
	 */
	public static function process($categories, $locale, $journalId, $publicationId)
    {
        $categoriesArray = explode(';', $categories);

        foreach ($categoriesArray as $categoryPath) {
            $lowerCategoryPath = mb_strtolower(trim($categoryPath));
            $category = CachedEntities::getCachedCategory($lowerCategoryPath, $journalId);
            $categoryDao = CachedDaos::getCategoryDao();

            if (is_null($category)) {
				/** @var \Category $category */
                $category = $categoryDao->newDataObject();
                $category->setContextId($journalId);
                $category->setTitle($categoryPath, $locale);
				$category->setData('locale', $locale);
                $category->setParentId(null);
                $category->setSequence(REALLY_BIG_NUMBER);
                $category->setPath($lowerCategoryPath);

                $categoryDao->insertObject($category);
            }

            $categoryDao->insertPublicationAssignment($category->getId(), $publicationId);
        }
	}

	/**
     * Process categories for a versioned publication
     * Clears existing categories and adds new ones from CSV data or clones from base publication
	 *
	 * @param string $categories
	 * @param string $locale
	 * @param int $journalId
	 * @param int $publicationId
	 * @param ?\Publication $basePublication
	 *
	 * @return void
     */
    public static function processForVersion($categories, $locale, $journalId, $publicationId,  $basePublication = null)
    {
		$categoryDao = CachedDaos::getCategoryDao();
		$categoryDao->deletePublicationAssignments($publicationId);

        if (empty(trim($categories)) && !is_null($basePublication)) {
			/** @var \Category[] */
            $basePublicationCategoriesArray = $categoryDao->getByPublicationId($basePublication->getId())->toArray();

			if (!empty($basePublicationCategoriesArray)) {
				foreach($basePublicationCategoriesArray as $category) {
					$categoryDao->insertPublicationAssignment($category->getId(), $publicationId);
				}

				return;
			}
        }

        self::process($categories, $locale, $journalId, $publicationId);
    }
}
