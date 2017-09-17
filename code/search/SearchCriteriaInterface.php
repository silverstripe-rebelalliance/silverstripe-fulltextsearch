<?php

/**
 * Interface SearchCriteriaInterface
 *
 * SearchCriteria and SearchCriterion objects must implement this interface.
 */
interface SearchCriteriaInterface
{
    /**
     * The method used in all SearchCriterion to generate and append their filter query statements.
     *
     * This is also used in SearchCriteria to loop through it's collected SearchCriterion and append the above. This
     * allows us to have SearchCriteria and SearchCriterion in the same collections (allowing us to have complex nested
     * filtering).
     *
     * @param $ps
     * @return mixed
     */
    public function appendPreparedStatementTo(&$ps);
}
