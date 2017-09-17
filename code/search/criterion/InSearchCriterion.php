<?php

class InSearchCriterion extends AbstractSearchCriterion
{
    /**
     * @param string $target
     * @param mixed $value
     * @param string|null $comparison
     */
    public function __construct($target, $value, $comparison = null)
    {
        if ($comparison === null) {
            $comparison = AbstractSearchCriterion::IN;
        }

        parent::__construct($target, $value, $comparison);
    }

    /**
     * @return void
     */
    public function appendPreparedStatementTo(&$ps)
    {
        $ps .= $this->getComparisonPolarity();
        $ps .= $this->getInComparisonString();
    }

    /**
     * Is this a positive (+) or negative (-) Solr comparison.
     *
     * @return string
     */
    protected function getComparisonPolarity()
    {
        switch ($this->getComparison()) {
            case AbstractSearchCriterion::NOT_IN:
                return '-';
            default:
                return '+';
        }
    }

    /**
     * @return string
     */
    protected function getInComparisonString()
    {
        if (!is_array($this->getValue())) {
            throw new InvalidArgumentException('Invalid value type for Criterion IN');
        }

        $conditions = array();

        foreach ($this->getValue() as $value) {
            $condition = $this->getTarget();
            $condition .= $this->getComparisonConjunction();

            if (is_string($value)) {
                // String values need to be wrapped in quotes and escaped.
                $condition .= $this->getQuoteValue($value);
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
