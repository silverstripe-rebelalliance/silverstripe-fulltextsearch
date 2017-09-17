<?php

class RangeSearchCriterion extends AbstractSearchCriterion
{
    /**
     * @param string $target
     * @param mixed $value
     * @param string|null $comparison
     */
    public function __construct($target, $value, $comparison = null)
    {
        if ($comparison === null) {
            $comparison = AbstractSearchCriterion::GREATER_EQUAL;
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
        $ps .= $this->getOpenComparisonContainer();
        $ps .= $this->getLeftComparison();
        $ps .= $this->getComparisonConjunction();
        $ps .= $this->getRightComparison();
        $ps .= $this->getCloseComparisonContainer();
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
            case AbstractSearchCriterion::ISNULL:
                return '-';
            default:
                return '+';
        }
    }

    /**
     * Select the value that we want as our left comparison value.
     *
     * @return mixed|string
     * @throws InvalidArgumentException
     */
    protected function getLeftComparison()
    {
        switch ($this->getComparison()) {
            case AbstractSearchCriterion::GREATER_EQUAL:
            case AbstractSearchCriterion::GREATER_THAN:
                return $this->getValue();
            case AbstractSearchCriterion::ISNULL:
            case AbstractSearchCriterion::ISNOTNULL:
            case AbstractSearchCriterion::LESS_EQUAL:
            case AbstractSearchCriterion::LESS_THAN:
                return '*';
            default:
                throw new InvalidArgumentException('Invalid comparison for RangeCriterion');
        }
    }

    /**
     * Select the value that we want as our right comparison value.
     *
     * @return mixed|string
     * @throws InvalidArgumentException
     */
    protected function getRightComparison()
    {
        switch ($this->getComparison()) {
            case AbstractSearchCriterion::GREATER_EQUAL:
            case AbstractSearchCriterion::GREATER_THAN:
            case AbstractSearchCriterion::ISNULL:
            case AbstractSearchCriterion::ISNOTNULL:
                return '*';
            case AbstractSearchCriterion::LESS_EQUAL:
            case AbstractSearchCriterion::LESS_THAN:
                return $this->getValue();
            default:
                throw new InvalidArgumentException('Invalid comparison for RangeCriterion');
        }
    }

    /**
     * Decide how we are comparing our left and right values.
     *
     * @return string
     */
    protected function getComparisonConjunction()
    {
        return ' TO ';
    }

    /**
     * Does our comparison need a container? EG: "[* TO *]"? If so, return the opening container brace.
     *
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getOpenComparisonContainer()
    {
        switch ($this->getComparison()) {
            case AbstractSearchCriterion::GREATER_EQUAL:
            case AbstractSearchCriterion::LESS_EQUAL:
            case AbstractSearchCriterion::ISNULL:
            case AbstractSearchCriterion::ISNOTNULL:
                return '[';
            case AbstractSearchCriterion::GREATER_THAN:
            case AbstractSearchCriterion::LESS_THAN:
                return '{';
            default:
                throw new InvalidArgumentException('Invalid comparison for RangeCriterion');
        }
    }

    /**
     * Does our comparison need a container? EG: "[* TO *]"? If so, return the closing container brace.
     *
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getCloseComparisonContainer()
    {
        switch ($this->getComparison()) {
            case AbstractSearchCriterion::GREATER_EQUAL:
            case AbstractSearchCriterion::LESS_EQUAL:
            case AbstractSearchCriterion::ISNULL:
            case AbstractSearchCriterion::ISNOTNULL:
                return ']';
            case AbstractSearchCriterion::GREATER_THAN:
            case AbstractSearchCriterion::LESS_THAN:
                return '}';
            default:
                throw new InvalidArgumentException('Invalid comparison for RangeCriterion');
        }
    }
}
