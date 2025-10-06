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

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\publication\Publication;

class CategoriesProcessor
{
    public static function process(string $categories, string $locale, int $journalId, int $publicationId)
    {
        if (empty(trim($categories))) {
            return;
        }

        $categoriesArray = explode(';', $categories);

        foreach ($categoriesArray as $categoryPath) {
            $categoryPath = trim($categoryPath);

            if (empty($categoryPath)) {
                continue;
            }

            $lowerCategoryPath = mb_strtolower($categoryPath);
            $category = CachedEntities::getCachedCategory($lowerCategoryPath, $journalId);

            if (is_null($category)) {
                $category = Repo::category()->newDataObject();

                $category->setContextId($journalId);
                $category->setTitle($categoryPath, $locale);
                $category->setData('locale', $locale);
                $category->setParentId(null);
                $category->setSequence(REALLY_BIG_NUMBER);
                $category->setPath($lowerCategoryPath);

                $categoryId = Repo::category()->add($category);
                $category = Repo::category()->get($categoryId);
                CachedEntities::$categories[$lowerCategoryPath] = $category;
            }

            CachedDaos::getCategoryDao()->insertPublicationAssignment($category->getId(), $publicationId);
        }
	}

    /**
     * Process categories for a versioned publication
     * Clears existing categories and adds new ones from CSV data or clones from base publication
     */
    public static function processForVersion(
        string $categories,
        string $locale,
        int $journalId,
        int $publicationId,
        ?Publication $basePublication = null
    ): void
    {
        $categoryDao = CachedDaos::getCategoryDao();
        $categoryDao->deletePublicationAssignments($publicationId);

        if (empty(trim($categories)) && !is_null($basePublication)) {
            $categoryIds = Repo::category()->getCollector()
                ->filterByPublicationIds([$basePublication->getId()])
                ->getIds()
                ->toArray();

            if (!empty($categoryIds)) {
                foreach ($categoryIds as $categoryId) {
                    $categoryDao->insertPublicationAssignment($categoryId, $publicationId);
                }

                return;
            }
        }

        self::process($categories, $locale, $journalId, $publicationId);
    }
}
