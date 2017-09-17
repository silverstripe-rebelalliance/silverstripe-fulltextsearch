<?php

class BasicSearchCriterion extends AbstractSearchCriterion
{
    /**
     * @param string $target
     * @param mixed $value
     * @param string|null $comparison
     */
    public function __construct($target, $value, $comparison = null)
    {
        if ($comparison === null) {
            $comparison = AbstractSearchCriterion::EQUAL;
        }

        parent::__construct($target, $value, $comparison);
    }

    /**
     * @var string $ps
     * @return void
     */
    public function appendPreparedStatementTo(&$ps)
    {
        $ps .= $this->getComparisonPolarity();
        $ps .= '(';
        $ps .= $this->getTarget();
        $ps .= $this->getComparisonConjunction();
        $ps .= $this->getQuoteValue($this->getValue());
        $ps .= ')';
    }

    /**
     * Is this a positive (+) or negative (-) Solr comparison.
     *
     * @return string
     */
    protected function getComparisonPolarity()
    {
        switch ($this->getComparison()) {
            case AbstractSearchCriterion::NOT_EQUAL:
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
