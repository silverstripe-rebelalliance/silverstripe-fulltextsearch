<?php

class SearchCriteria implements SearchCriteriaInterface
{
    /**
     * "AND" is a protected PHP7 keyword.
     *
     * @param string
     */
    const UND = ' AND ';

    /**
     * "OR" is a protected PHP7 keyword.
     *
     * @param string
     */
    const ODER = ' OR ';

    /**
     * A collection of SearchCriterion and SearchCriteria.
     *
     * @var SearchCriteriaInterface[]
     */
    protected $clauses = array();

    /**
     * The conjunctions used between Criteria (AND/OR).
     *
     * @var string[]
     */
    protected $conjunctions = array();

    /**
     * You can pass through a string value, Criteria object, or Criterion object for $target.
     *
     * String value might be "SiteTree_Title" or whatever field in your index that you're trying to target.
     *
     * If you require complex filtering then you can build your Criteria object first with multiple layers/levels of
     * Criteria, and then pass it in here when you're ready.
     *
     * If you have your own Criterion object that you've created that you want to use, you can also pass that in here.
     *
     * @param string|AbstractSearchCriterion $target
     * @param mixed $value
     * @param string|null $comparison
     */
    public function __construct($target, $value = null, $comparison = null)
    {
        $this->addClause($this->getCriterionForCondition($target, $value, $comparison));
    }

    /**
     * Static create method provided so that you can perform method chaining.
     *
     * @param $target
     * @param null $value
     * @param null $comparison
     * @return SearchCriteria
     */
    public static function create($target, $value = null, $comparison = null) {
        return new SearchCriteria($target, $value, $comparison);
    }

    /**
     * @param string $ps Current prepared statement.
     * @return void;
     */
    public function appendPreparedStatementTo(&$ps)
    {
        $ps .= $this->getOpenComparisonContainer();

        foreach ($this->getClauses() as $key => $clause) {
            $clause->appendPreparedStatementTo($ps);

            // There's always one less conjunction then there are clauses.
            if ($this->getConjunction($key) !== null) {
                $ps .= $this->getConjunction($key);
            }
        }

        $ps .= $this->getCloseComparisonContainer();
    }

    /**
     * @param string|SearchCriteriaInterface $target
     * @param mixed $value
     * @param string|null $comparison
     * @return $this
     */
    public function addAnd($target, $value = null, $comparison = null)
    {
        $this->addConjunction(SearchCriteria::UND);
        $this->addClause(AbstractSearchCriterion::factory($target, $value, $comparison));

        return $this;
    }

    /**
     * @param string|SearchCriteriaInterface $target
     * @param mixed $value
     * @param string|null $comparison
     * @return $this
     */
    public function addOr($target, $value = null, $comparison = null)
    {
        $this->addConjunction(SearchCriteria::ODER);
        $this->addClause(AbstractSearchCriterion::factory($target, $value, $comparison));

        return $this;
    }

    /**
     * @param string|SearchCriteriaInterface $target
     * @param mixed $value
     * @param string $comparison
     * @return SearchCriteriaInterface
     */
    protected function getCriterionForCondition($target, $value, $comparison)
    {
        if ($target instanceof SearchCriteriaInterface) {
            return $target;
        }

        return AbstractSearchCriterion::factory($target, $value, $comparison);
    }

    /**
     * @return string
     */
    protected function getOpenComparisonContainer()
    {
        if (count($this->getClauses()) > 1) {
            return '+(';
        }

        return '';
    }

    /**
     * @return string
     */
    protected function getCloseComparisonContainer()
    {
        if (count($this->getClauses()) > 1) {
            return ')';
        }

        return '';
    }

    /**
     * @return SearchCriteriaInterface[]
     */
    protected function getClauses()
    {
        return $this->clauses;
    }

    /**
     * @param SearchCriteriaInterface $criterion
     */
    protected function addClause($criterion)
    {
        $this->clauses[] = $criterion;
    }

    /**
     * @return string[]
     */
    protected function getConjunctions()
    {
        return $this->conjunctions;
    }

    /**
     * @param int $key
     * @return string|null
     */
    protected function getConjunction($key)
    {
        if (!array_key_exists($key, $this->getConjunctions())) {
            return null;
        }

        return $this->getConjunctions()[$key];
    }

    /**
     * @param string $conjunction
     */
    protected function addConjunction($conjunction)
    {
        $this->conjunctions[] = $conjunction;
    }
}
