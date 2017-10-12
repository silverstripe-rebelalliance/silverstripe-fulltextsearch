<?php

class SolrSearchQueryWriter_In extends AbstractSearchQueryWriter
{
    /**
     * @param SearchCriterion $searchCriterion
     * @return string
     */
    public function generateQueryString(SearchCriterion $searchCriterion)
    {
        $qs = $this->getComparisonPolarity($searchCriterion->getComparison());
        $qs .= $this->getInComparisonString($searchCriterion);

        return $qs;
    }

    /**
     * Is this a positive (+) or negative (-) Solr comparison.
     *
     * @param string $comparison
     * @return string
     */
    protected function getComparisonPolarity($comparison)
    {
        switch ($comparison) {
            case SearchCriterion::NOT_IN:
                return '-';
            default:
                return '+';
        }
    }

    /**
     * @param SearchCriterion $searchCriterion
     * @return string
     */
    protected function getInComparisonString(SearchCriterion $searchCriterion)
    {
        $conditions = array();

        if (!is_array($searchCriterion->getValue())) {
            throw new InvalidArgumentException('Invalid value type for Criterion IN');
        }

        foreach ($searchCriterion->getValue() as $value) {
            $condition = $searchCriterion->getTarget();
            $condition .= $this->getComparisonConjunction();

            if (is_string($value)) {
                // String values need to be wrapped in quotes and escaped.
                $condition .= $searchCriterion->getQuoteValue($value);
            } else {
                $condition .= $value;
            }

            $conditions[] = $condition;
        }

        return '(' . implode(' ', $conditions) . ')';
    }

    /**
     * Decide how we are comparing our left and right values.
     *
     * @return string
     */
    protected function getComparisonConjunction()
    {
        return ':';
    }
}
