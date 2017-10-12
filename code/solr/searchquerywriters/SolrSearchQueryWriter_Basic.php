<?php

class SolrSearchQueryWriter_Basic extends AbstractSearchQueryWriter
{
    /**
     * @var SearchCriterion $searchCriterion
     * @return string
     */
    public function generateQueryString(SearchCriterion $searchCriterion)
    {
        $qs = $this->getComparisonPolarity($searchCriterion->getComparison());
        $qs .= '(';
        $qs .= $searchCriterion->getTarget();
        $qs .= $this->getComparisonConjunction();
        $qs .= $searchCriterion->getQuoteValue($searchCriterion->getValue());
        $qs .= ')';

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
            case SearchCriterion::NOT_EQUAL:
                return '-';
            default:
                return '+';
        }
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
