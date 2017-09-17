<?php

abstract class AbstractSearchCriterion implements SearchCriteriaInterface
{
    /**
     * field:value
     *
     * @var string
     */
    const EQUAL = 'EQUAL';

    /**
     * -field:value
     *
     * @var string
     */
    const NOT_EQUAL = 'NOT_EQUAL';

    /**
     * field:[value TO *]
     *
     * @var string
     */
    const GREATER_EQUAL = 'GREATER_EQUAL';

    /**
     * field:{value TO *}
     *
     * @var string
     */
    const GREATER_THAN = 'GREATER_THAN';

    /**
     * field:[* TO value]
     *
     * @var string
     */
    const LESS_EQUAL = 'LESS_EQUAL';

    /**
     * field:{* TO value}
     *
     * @var string
     */
    const LESS_THAN = 'LESS_THAN';

    /**
     * (field:value1 field:value2 field:value3)
     *
     * @var string
     */
    const IN = 'IN';

    /**
     * -(field:value1 field:value2 field:value3)
     *
     * @var string
     */
    const NOT_IN = 'NOT_IN';

    /**
     * field:[* TO *]
     *
     * @var string
     */
    const ISNULL = 'ISNULL';

    /**
     * -field:[* TO *]
     *
     * @var string
     */
    const ISNOTNULL = 'ISNOTNULL';

    /**
     * @var string
     */
    protected $comparison;

    /**
     * The table and field that this Criterion is applied to.
     *
     * @var string
     */
    protected $target;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @param string $target
     * @param mixed $value
     * @param string|null $comparison
     */
    public function __construct($target, $value = null, $comparison = null)
    {
        if ($comparison === null) {
            $comparison = AbstractSearchCriterion::EQUAL;
        }

        $this->setTarget($target);
        $this->setValue($value);
        $this->setComparison($comparison);
    }

    /**
     * Static create method provided so that you can implement method chaining.
     *
     * @return AbstractSearchCriterion
     */
    public static function create() {
        $args = func_get_args();

        $class = get_called_class();
        if($class == 'Object') $class = array_shift($args);

        return Injector::inst()->createWithArgs($class, $args);
    }

    /**
     * @param string $target
     * @param mixed $value
     * @param string|null $comparison
     * @return AbstractSearchCriterion
     */
    public static function factory($target, $value = null, $comparison = null)
    {
        // If any SearchCriterion object was passed in as the target, just return it as it is. We assume that this
        // SearchCriterion object has already had all of it's required values set, and is ready to be used.
        if ($target instanceof AbstractSearchCriterion) {
            return $target;
        }

        // Our default comparison is `EQUAL`.
        if ($comparison === null) {
            $comparison = AbstractSearchCriterion::EQUAL;
        }

        switch ($comparison) {
            case AbstractSearchCriterion::EQUAL:
            case AbstractSearchCriterion::NOT_EQUAL:
                return new BasicSearchCriterion($target, $value, $comparison);
            case AbstractSearchCriterion::IN:
            case AbstractSearchCriterion::NOT_IN:
                return new InSearchCriterion($target, $value, $comparison);
            case AbstractSearchCriterion::GREATER_EQUAL:
            case AbstractSearchCriterion::GREATER_THAN:
            case AbstractSearchCriterion::LESS_EQUAL:
            case AbstractSearchCriterion::LESS_THAN:
            case AbstractSearchCriterion::ISNULL:
            case AbstractSearchCriterion::ISNOTNULL:
                return new RangeSearchCriterion($target, $value, $comparison);
            default:
                throw new InvalidArgumentException('Unsupported comparison type in AbstractCriterion factory');
        }
    }

    /**
     * Is this a positive (+) or negative (-) Solr comparison.
     *
     * @return mixed
     */
    abstract protected function getComparisonPolarity();

    /**
     * @return string
     */
    protected function getComparison()
    {
        return $this->comparison;
    }

    /**
     * @param $comparison
     * @return $this
     */
    protected function setComparison($comparison)
    {
        $this->comparison = $comparison;

        return $this;
    }

    /**
     * @return string
     */
    protected function getTarget()
    {
        return $this->target;
    }

    /**
     * @param $target
     * @return $this
     */
    protected function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getValue()
    {
        return $this->value;
    }

    /**
     * @param $value
     * @return $this
     */
    protected function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * String values should be passed into our filter string with quotation marks and escaping.
     *
     * @param string $value
     * @return string
     */
    protected function getQuoteValue($value)
    {
        if (is_string($value)) {
            return '"' . addslashes($value) . '"';
        }

        return $value;
    }
}
