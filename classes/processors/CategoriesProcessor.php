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
use PKP\category\Category;

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
                $category = $category = self::createNewCategory($journalId, $categoryPath, $locale);

                $categoryId = Repo::category()->add($category);
                CachedEntities::$categories[$lowerCategoryPath] = Repo::category()->get($categoryId);
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

    /**
     * Process categories for multi-locale import
     * This handles adding locale-specific data to existing categories
     */
    public static function processMultiLocale(string $categories, string $locale, int $journalId, int $publicationId): void
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

            if (!is_null($category)) {
                // Category exists, update with new locale title if different
                $existingTitle = $category->getLocalizedData('title', $locale);
                if (empty($existingTitle) || $existingTitle !== $categoryPath) {
                    $category->setTitle($categoryPath, $locale);
                    Repo::category()->dao->update($category);
                }

                continue;
            }

            // Category doesn't exist, create it (should follow the same logic as process())
            $category = self::createNewCategory($journalId, $categoryPath, $locale);
            $categoryId = Repo::category()->add($category);

            // Assign to publication if not already assigned
            $existingCategoryIds = Repo::category()->getCollector()
                ->filterByPublicationIds([$publicationId])
                ->getIds()
                ->toArray();

            if (!in_array($categoryId, $existingCategoryIds)) {
                CachedDaos::getCategoryDao()->insertPublicationAssignment($categoryId, $publicationId);
            }
        }
    }

    private static function createNewCategory(int $journalId, string $categoryPath, string $locale): Category
    {
        $category = Repo::category()->newDataObject();
        $category->setContextId($journalId);
        $category->setTitle($categoryPath, $locale);
        $category->setParentId(null);
        $category->setSequence(REALLY_BIG_NUMBER);
        $category->setPath(mb_strtolower($categoryPath));

        return $category;
    }
}
