<?php

namespace Thelia\Model;

use Thelia\Model\om\BaseCategoryQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'category' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Thelia.Model
 */
class CategoryQuery extends BaseCategoryQuery
{

    /**
     *
     *  count how many direct children have a category
     *
     * @param int $parent category parent id
     * @return int
     */
    public static function countChild($parent)
    {
        return self::create()->filterByParent($parent)->count();
    }

    /**
     *
     * find all category children for a given category. an array of \Thelia\Model\Category is return
     *
     * @param $categoryId the category id
     * @param int $depth max depth you want to search
     * @param int $currentPos don't change this param, it is used for recursion
     * @return \Thelia\Model\Category[]
     */
    public static function findAllChild($categoryId, $depth = 0, $currentPos = 0)
    {
        $result = array();
        $currentPos++;

        if($depth == $currentPos && $depth != 0) return;

        $categories = self::create()
            ->filterByParent($categoryId)
            ->find();

        foreach ($categories as $category) {
            array_push($result, $category);
            $result = array_merge($result, (array) self::findAllChild($category->getId(), $depth, $currentPos));
        }

        return $result;
    }
}
