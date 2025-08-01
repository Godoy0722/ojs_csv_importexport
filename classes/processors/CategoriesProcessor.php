<?php

/**
 * @file plugins/importexport/csv/classes/processors/CategoriesProcessor.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
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
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;

class CategoriesProcessor
{
    public static function process(string $categories, string $locale, int $journalId, int $publicationId)
    {
        if (empty(trim($categories))) {
            return;
        }

        $categoriesArray = explode(';', $categories);
        $publicationCategories = [];

        foreach ($categoriesArray as $categoryPath) {
            $categoryPath = trim($categoryPath);

            if (empty($categoryPath)) {
                continue;
            }

            $lowerCategoryPath = mb_strtolower($categoryPath);
            $category = CachedEntities::getCachedCategory($lowerCategoryPath, $journalId);

            if (!is_null($category)) {
                $categoryId = $category->getId();
                $publicationCategories[] = $categoryId;
                continue;
            }

            $category = Repo::category()->newDataObject();

            $category->setContextId($journalId);
            $category->setTitle($categoryPath, $locale);
            $category->setParentId(null);
            $category->setSequence(REALLY_BIG_NUMBER);
            $category->setPath($lowerCategoryPath);

            $categoryId = Repo::category()->add($category);
            $publicationCategories[] = $categoryId;
        }

        if (!empty($publicationCategories)) {
            Repo::publication()->assignCategoriesToPublication($publicationId, $publicationCategories);
        }

	}
}
