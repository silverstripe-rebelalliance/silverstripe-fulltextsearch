<?php

interface SearchAdapterInterface
{
    /**
     * @param string $conjunction
     * @return string
     */
    public function getConjunctionFor($conjunction);

    /**
     * @return string
     */
    public function getOpenComparisonContainer();

    /**
     * @return string
     */
    public function getCloseComparisonContainer();

    /**
     * @param SearchCriterion $criterion
     * @return string
     */
    public function generateQueryString(SearchCriterion $criterion);
}
