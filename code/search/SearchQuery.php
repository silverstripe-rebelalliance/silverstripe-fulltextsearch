<?php

/**
 * Represents a search query
 *
 * API very much still in flux.
 */
class SearchQuery extends ViewableData
{
    public static $missing = null;
    public static $present = null;

    public static $default_page_size = 10;

    /** These are public, but only for index & variant access - API users should not manually access these */

    public $search = array();

    public $classes = array();

    public $require = array();
    public $exclude = array();

    /**
     * @var SearchCriteria[]
     */
    public $criteria = array();

    protected $start = 0;
    protected $limit = -1;

    /** These are the API functions */

    public function __construct()
    {
        if (self::$missing === null) {
            self::$missing = new stdClass();
        }
        if (self::$present === null) {
            self::$present = new stdClass();
        }
    }

    /**
     * @param  String $text Search terms. Exact format (grouping, boolean expressions, etc.) depends on the search implementation.
     * @param  array $fields Limits the search to specific fields (using composite field names)
     * @param  array  $boost  Map of composite field names to float values. The higher the value,
     * the more important the field gets for relevancy.
     */
    public function search($text, $fields = null, $boost = array())
    {
        $this->search[] = array('text' => $text, 'fields' => $fields ? (array)$fields : null, 'boost' => $boost, 'fuzzy' => false);
    }

    /**
     * Similar to {@link search()}, but uses stemming and other similarity algorithms
     * to find the searched terms. For example, a term "fishing" would also likely find results
     * containing "fish" or "fisher". Depends on search implementation.
     *
     * @param  String $text See {@link search()}
     * @param  array $fields See {@link search()}
     * @param  array $boost See {@link search()}
     */
    public function fuzzysearch($text, $fields = null, $boost = array())
    {
        $this->search[] = array('text' => $text, 'fields' => $fields ? (array)$fields : null, 'boost' => $boost, 'fuzzy' => true);
    }

    public function inClass($class, $includeSubclasses = true)
    {
        $this->classes[] = array('class' => $class, 'includeSubclasses' => $includeSubclasses);
    }

    /**
     * Similar to {@link search()}, but typically used to further narrow down
     * based on other facets which don't influence the field relevancy.
     *
     * @param  String $field Composite name of the field
     * @param  Mixed $values Scalar value, array of values, or an instance of SearchQuery_Range
     */
    public function filter($field, $values)
    {
        $requires = isset($this->require[$field]) ? $this->require[$field] : array();
        $values = is_array($values) ? $values : array($values);
        $this->require[$field] = array_merge($requires, $values);
    }

    /**
     * Excludes results which match these criteria, inverse of {@link filter()}.
     *
     * @param  String $field
     * @param  mixed $values
     */
    public function exclude($field, $values)
    {
        $excludes = isset($this->exclude[$field]) ? $this->exclude[$field] : array();
        $values = is_array($values) ? $values : array($values);
        $this->exclude[$field] = array_merge($excludes, $values);
    }

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
     * @param string|SearchCriteriaInterface $target
     * @param mixed $value
     * @param string|null $comparison
     * @return SearchCriteria
     */
    public function filterBy($target, $value = null, $comparison = null)
    {
        if (!$target instanceof SearchCriteria) {
            $target = new SearchCriteria($target, $value, $comparison);
        }

        $this->criteria[] = $target;

        return $target;
    }

    public function start($start)
    {
        $this->start = $start;
    }

    public function limit($limit)
    {
        $this->limit = $limit;
    }

    public function page($page)
    {
        $this->start = $page * self::$default_page_size;
        $this->limit = self::$default_page_size;
    }

    public function isfiltered()
    {
        return $this->search || $this->classes || $this->require || $this->exclude;
    }

    public function __toString()
    {
        return "Search Query\n";
    }
}

/**
 * Create one of these and pass as one of the values in filter or exclude to filter or exclude by a (possibly
 * open ended) range
 */
class SearchQuery_Range
{
    public $start = null;
    public $end = null;

    public function __construct($start = null, $end = null)
    {
        $this->start = $start;
        $this->end = $end;
    }

    public function start($start)
    {
        $this->start = $start;
    }

    public function end($end)
    {
        $this->end = $end;
    }

    public function isfiltered()
    {
        return $this->start !== null || $this->end !== null;
    }
}
