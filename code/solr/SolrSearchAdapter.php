<?php

class SolrSearchAdapter implements SearchAdapterInterface
{
    /**
     * @param SearchCriterion $criterion
     * @return string
     */
    public function generateQueryString(SearchCriterion $criterion)
    {
        $writer = $this->getSearchQueryWriter($criterion);

        return $writer->generateQueryString($criterion);
    }

    /**
     * @param string $conjunction
     * @return string
     * @throws InvalidArgumentException
     */
    public function getConjunctionFor($conjunction)
    {
        switch ($conjunction) {
            case SearchCriteria::UND:
                return ' AND ';
            case SearchCriteria::ODER:
                return ' OR ';
            default:
                throw new InvalidArgumentException(
                    sprintf('Invalid conjunction supplied to SolrSearchAdapter: "%s".', $conjunction)
                );
        }
    }

    /**
     * @return string
     */
    public function getOpenComparisonContainer()
    {
        return '+(';
    }

    /**
     * @return string
     */
    public function getCloseComparisonContainer()
    {
        return ')';
    }

    /**
     * @param SearchCriterion $searchCriterion
     * @return AbstractSearchQueryWriter
     * @throws InvalidArgumentException
     */
    protected function getSearchQueryWriter(SearchCriterion $searchCriterion)
    {
        if ($searchCriterion->getSearchQueryWriter() instanceof AbstractSearchQueryWriter) {
            // The user has defined their own SearchQueryWriter, so we should just return it.
            return $searchCriterion->getSearchQueryWriter();
        }

        switch ($searchCriterion->getComparison()) {
            case SearchCriterion::EQUAL:
            case SearchCriterion::NOT_EQUAL:
                return new SolrSearchQueryWriter_Basic();
            case SearchCriterion::IN:
            case SearchCriterion::NOT_IN:
                return new SolrSearchQueryWriter_In();
            case SearchCriterion::GREATER_EQUAL:
            case SearchCriterion::GREATER_THAN:
            case SearchCriterion::LESS_EQUAL:
            case SearchCriterion::LESS_THAN:
            case SearchCriterion::ISNULL:
            case SearchCriterion::ISNOTNULL:
                return new SolrSearchQueryWriter_Range();
            case SearchCriterion::CUSTOM:
                // CUSTOM requires a SearchQueryWriter be provided.
                if (!$searchCriterion->getSearchQueryWriter() instanceof AbstractSearchQueryWriter) {
                    throw new InvalidArgumentException('SearchQueryWriter undefined or unsupported in SearchCriterion');
                }

                return $searchCriterion->getSearchQueryWriter();
            default:
                throw new InvalidArgumentException('Unsupported comparison type in SolrSearchAdapter');
        }
    }
}
