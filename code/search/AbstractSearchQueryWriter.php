<?php

abstract class AbstractSearchQueryWriter
{
    /**
     * @param SearchCriterion $searchCriterion
     * @return string
     */
    abstract public function generateQueryString(SearchCriterion $searchCriterion);
}
