## Introduction

This is a module aimed at adding support for standalone fulltext search engines to SilverStripe.

It contains several layers:

 * A fulltext API, ignoring the actual provision of fulltext searching
 * A connector API, providing common code to allow connecting a fulltext searching engine to the fulltext API, and
 * Some connectors for common fulltext searching engines.

## Reasoning

There are several fulltext search engines that work in a similar manner. They build indexes of denormalized data that
is then searched through using some custom query syntax.

Traditionally, fulltext search connectors for SilverStripe have attempted to hide this design, instead presenting
fulltext searching as an extension of the object model. However the disconnect between the fulltext search engine's
design and the object model meant that searching was inefficient. The abstraction would also often break and it was
hard to then figure out what was going on.

This module instead provides the ability to define those indexes and queries in PHP. The indexes are defined as a mapping
between the SilverStripe object model and the connector-specific fulltext engine index model. This module then interrogates model metadata 
to build the specific index definition. 

It also hooks into SilverStripe framework in order to update the indexes when the models change and connectors then convert those index and query definitions 
into fulltext engine specific code.

The intent of this module is not to make changing fulltext search engines seamless. Where possible this module provides
common interfaces to fulltext engine functionality, abstracting out common behaviour. However, each connector also
offers its own extensions, and there is some behaviour (such as getting the fulltext search engines installed, configured
and running) that each connector deals with itself, in a way best suited to that search engine's design.

## Basic usage

Basic usage is a four step process:

1). Define an index in SilverStripe (Note: The specific connector index instance - that's what defines which engine gets used)

	// File: mysite/code/MyIndex.php:
	<?php
	class MyIndex extends SolrIndex {
		function init() {
			$this->addClass('Page');
			$this->addFulltextField('Title');
			$this->addFulltextField('Content');
		}
	}

You can also skip listing all searchable fields, and have the index
figure it out automatically via `addAllFulltextFields()`.

2). Add something to the index (Note: You can also just update an existing document in the CMS. but adding _existing_ objects to the index is connector specific)

	$page = new Page(array('Content' => 'Help me. My house is on fire. This is less than optimal.'));
	$page->write();

Note: There's usually a connector-specific "reindex" task for this.

3). Build a query

	$query = new SearchQuery();
	$query->search('My house is on fire');

4). Apply that query to an index

	$results = singleton('MyIndex')->search($query);

Note that for most connectors, changes won't be searchable until _after_ the request that triggered the change.

The return value of a `search()` call is an object which contains a few properties:

 * `Matches`: ArrayList of the current "page" of search results.
 * `Suggestion`: (optional) Any suggested spelling corrections in the original query notation
 * `SuggestionNice`: (optional) Any suggested spelling corrections for display (without query notation)
 * `SuggestionQueryString` (optional) Link to repeat the search with suggested spelling corrections

## Controllers and Templates

In order to render search results, you need to return them from a controller.
You can also drive this through a form response through standard SilverStripe forms.
In this case we simply assume there's a GET parameter named `q` with a search term present.

	class Page_Controller extends ContentController {
		private static $allowed_actions = array('search');
		public function search($request) {
			$query = new SearchQuery();
			$query->search($request->getVar('q'));
			return $this->renderWith('array(
				'SearchResult' => singleton('MyIndex')->search($query);
			);
		}
	}

In your template (e.g. `Page_results.ss`) you can access the results and loop through them.
They're stored in the `$Matches` property of the search return object.

	<% if SearchResult.Matches %>
		<h2>Results for &quot;{$Query}&quot;</h2>
		<p>Displaying Page $SearchResult.Matches.CurrentPage of $SearchResult.Matches.TotalPages</p>
		<ol>
			<% loop SearchResult.Matches %>
				<li>
					<h3><a href="$Link">$Title</a></h3>
					<p><% if Abstract %>$Abstract.XML<% else %>$Content.ContextSummary<% end_if %></p>
				</li>
			<% end_loop %>
		</ol>
	<% else %>
		<p>Sorry, your search query did not return any results.</p>
	<% end_if %>
	
Please check the [pagination guide](http://docs.silverstripe.org/en/3.2/developer_guides/templates/how_tos/pagination/)
in the main SilverStripe documentation to learn how to paginate through search results.

## Automatic Index Updates

Every change, addition or removal of an indexed class instance triggers an index update through a
"processor" object. The update is transparently handled through inspecting every executed database query
and checking which database tables are involved in it.

Index updates usually are executed in the same request which caused the index to become "dirty".
For example, a CMS author might have edited a page, or a user has left a new comment.
In order to minimise delays to those users, the index update is deferred until after
the actual request returns to the user, through PHP's `register_shutdown_function()` functionality.

If the [queuedjobs](https://github.com/silverstripe-australia/silverstripe-queuedjobs) module is installed,
updates are queued up instead of executed in the same request. Queue jobs are usually processed every minute.
Large index updates will be batched into multiple queue jobs to ensure a job can run to completion within
common execution constraints (memory and time limits). You can check the status of jobs in
an administrative interface under `admin/queuedjobs/`.

## Manual Index Updates

Manual updates are connector specific, please check the connector docs for details.

## Searching Specific Fields

By default, the index searches through all indexed fields.
This can be limited by arguments to the `search()` call.

	$query = new SearchQuery();
	$query->search('My house is on fire', array('Page_Title'));
	// No results, since we're searching in title rather than page content
	$results = singleton('MyIndex')->search($query);

## Searching Value Ranges

Most values can be expressed as ranges, most commonly dates or numbers.
To search for a range of values rather than an exact match, 
use the `SearchQuery_Range` class. The range can include bounds on both sides,
or stay open ended by simply leaving the argument blank.

	$query = new SearchQuery();
	$query->search('My house is on fire');
	// Only include documents edited in 2011 or earlier
	$query->filter('Page_LastEdited', new SearchQuery_Range(null, '2011-12-31T23:59:59Z'));
	$results = singleton('MyIndex')->search($query);	

Note: At the moment, the date format is specific to the search implementation.

## Searching Empty or Existing Values

Since there's a type conversion between the SilverStripe database, object properties
and the search index persistence, its often not clear which condition is searched for.
Should it equal an empty string, or only match if the field wasn't indexed at all?
The `SearchQuery` API has the concept of a "missing" and "present" field value for this:

	$query = new SearchQuery();
	$query->search('My house is on fire');
	// Needs a value, although it can be false
	$query->filter('Page_ShowInMenus', SearchQuery::$present);
	$results = singleton('MyIndex')->search($query);	

## Indexing Multiple Classes

An index is a denormalized view of your data, so can hold data from more than one model.
As you can only search one index at a time, all searchable classes need to be included.

	// File: mysite/code/MyIndex.php:
	<?php
	class MyIndex extends SolrIndex {
		function init() {
			$this->addClass('Page');
			$this->addClass('Member');
			$this->addFulltextField('Content'); // only applies to Page class
			$this->addFulltextField('FirstName'); // only applies to Member class
		}
	}

## Using Multiple Indexes

Multiple indexes can be created and searched independently, but if you wish to override an existing
index with another, you can use the `$hide_ancestor` config.

	:::php
	class MyReplacementIndex extends MyIndex {
		private static $hide_ancestor = 'MyIndex';

		public function init() {
			parent::init();
			$this->addClass('File');
			$this->addFulltextField('Title');
		}
	}

You can also filter all indexes globally to a set of pre-defined classes if you wish to 
prevent any unknown indexes from being automatically included.

	:::yaml
	FullTextSearch:
	  indexes:
	    - MyReplacementIndex
	    - CoreSearchIndex


## Indexing Relationships

TODO

## Weighting/Boosting Fields

Results aren't all created equal. Matches in some fields are more important
than others, for example terms in a page title rather than its content
might be considered more relevant to the user.

To account for this, a "weighting" (or "boosting") factor can be applied to each
searched field. The default is 1.0, anything below that will decrease the relevance,
anthing above increases it.

Example:

	$query = new SearchQuery();
	$query->search(
		'My house is on fire', 
		null,
		array(
			'Page_Title' => 1.5,
			'Page_Content' => 1.0
		)
	);
	$results = singleton('MyIndex')->search($query);

## Basic Filtering

Basic filtering is provided through the "filter" and "exclude" methods:

	// Filter by products in either of these 3 categories.
	$this->filter('Product_CategoryID', array(21, 24, 25));
	// Exclude any products with no stock.
	$this->exclude('Product_StockLevel', 0);

All 'filters' are stored in the `$require` property of `SolrIndex`, and all 'exclusions' are stored in the `$exclude`
property of `SolrIndex`. Both properties use the first parameter (the field name, EG: 'Product_CategoryID') as the key.
If you filter by the same field name multiple times, you will end up with one accumulated filter - likewise for
exclusions.

If you want to perform more complex filtering (perhaps where you filter by the same field multiple times with different
criteria, or you need a mixture of complex "and"s and "or"s), then "Filtering by Criteria" is also available (below).

## Filtering by Criteria

### Filtering related Objects

* `SearchCriteriaInterface`: Interface for `SearchCriterion` and `SearchCriteria` classes.
* `SearchCriterion`: An object containing a single field filter (target field, comparison value, comparison type).
* `SearchCriteria`: An object containing a collection of `SearchCriterion` and/or `SearchCriteria` with conjunctions (IE: `AND`, `OR`) between each.
* `SearchQueryWriter`: A class used to generate a query string based on a `SearchCriterion`.
* `SearchAdapterInterface`: An Interface for our SearchAdapters. This adapter will control what `SearchQueryWriter` is used for each `SearchCriteria`.

### General usage

We need 3 things to create a `SearchCriterion`:

* **`Target`**: EG the field in our Search Index that we want to filter against.
* **`Value`**: The value we want to use for comparison.
* **`Comparison`**: The type of comparison (EG: `EQUAL`, `IN`, etc).

All currently supported comparisons can be found as constants in `SearchCriterion`.

### Creating a new `SearchCriterion`

#### Method 1a and 1b

    // `EQUAL` is the default comparison for `SearchCriterion`, so no third param is required.
    $criterion = new SearchCriterion('Product_Title', 'My Product');
    
    // Or use the `create` static method.
    $criterion = SearchCriterion::create('Product_Title', 'My Product');

### Creating a new `SearchCriteria`

`SearchCriteria` has a property called `$clauses` which is a collection of `SearchCriterion` (above) and/or `SearchCriteria` (allowing for infinite nesting of clauses), along with the conjunction used between each clause (IE: `AND`, `OR`). We want to build up our `SearchCriteria` by adding to it's `$clauses` collection.

`SearchCriteria` can either be passed an object that implements `SearchCriteriaInterface`, or it can be passed the `Target`, `Value`, and `Comparison` (like above).

#### Method 1

Instantiate a new `SearchCriteria` by providing an already instantiated `SearchCriterion` object. This `$criterion` will be added as the first item in the `$clauses` collection.

    $criteria = SearchCriteria::create($criterion);

#### Method 2

Instantiate a new `SearchCriteria` objects and define the `Target`, `Value`, and `Comparison`. `SearchCriteria` will create a new `SearchCriterion` object based on the values, and add it to the `$clauses` collection.

    $criteria = SearchCriteria::create('Product_CatID', array(21, 24, 25), AbstractCriterion::IN);

### Adding additional `SearchCriterion` to our `SearchCriteria`

When you want to add more complexity to your `SearchCriteria`, there are two methods available:

* `addAnd`: Add a new `SearchCriterion` or `SearchCriteria` with an `AND` conjunction.
* `addOr`: Add a new `SearchCriterion` or `SearchCriteria` with an `OR` conjunction.

#### Method 1

Use method chaining to create a `SearchCriterion` with two clauses.

    // Filter by products with stock that are in either of these 3 categories.
    $criteria = SearchCriteria::create('Product_CatID', array(21, 24, 25), AbstractCriterion::IN)
        ->addAnd('Product_Stock', 0, AbstractSearchCriterion::GREATER_THAN);

#### Method 2

Systematically add clauses to your already instantiated `SearchCriteria`.

    // Filter by products in either of these 3 categories.
    $criteria = SearchCriteria::create('Product_CatID', array(21, 24, 25), AbstractCriterion::IN);
    
    ... other stuff
    
    // Filter by products with stock.
    $criteria->addAnd('Product_StockLevel', 0, AbstractCriterion::GREATER_THAN);

### Adding multiple levels of filtering to our `SearchCriteria`

`SearchCriteria` also allows you to pass in other `SearchCriteria` objects as you instantiate it and as you use the `addAnd` and `addOr` methods.

    // Filter by products that are in either of these 3 categories with stock.
    $stockCategoryCriteria = SearchCriteria::create('Product_CatID', array(21, 24, 25), AbstractCriterion::IN)
        ->addAnd('Product_Stock', 0, AbstractSearchCriterion::GREATER_THAN);
    
    // Filter by products in Category ID  1 with stock over 5.
    $legoCriteria = SearchCriteria::create('Product_CatID', 1, AbstractCriterion::EQUAL)
        ->addAnd('Product_Stock', 5, AbstractSearchCriterion::GREATER_THAN);
    
    // Combine the two criteria with an `OR` conjunction
    $criteria = SearchCriteria::create($stockCategoryCriteria)
        ->addOr($legoCriteria);

### Adding `SearchCriteria` to our `SearchQuery`

Our `SearchQuery` class now has a property called `$criteria` which holds all of our `SearchCriteria`. You can add new `SearchCriteria` by using `SearchQuery::filterBy()`.

#### Method 1

Pass in an already instantiated `SearchCriteria` object. If you implemented complex filtering (above), you will probably need to follow this method - fully creating your `SearchCriteria` first, and then passing it to the `SearchQuery`.

    $query->filterBy($criteria);

#### Method 2a
Where basic (single level) filtering is ok, the `SearchQuery::filterBy()` method can be used to create your `SearchCriterion` and `SearchCriteria` object.

    $query->filterBy('Product_CatID', array(21, 24, 25), AbstractCriterion::IN);

#### Method 2b
The `filterBy()` method will return the **current** `SearchCriteria`, this allows you to method chain the `addAnd` and `addOr` methods.

    // Filter by products with stock that are in either of these 3 categories.
    $searchQuery->filterBy('Product_CategoryID', array(21, 24, 25), AbstractCriterion::IN)
                ->addAnd('Product_StockLevel', 0, AbstractCriterion::GREATER_THAN);

Each item in the `$criteria` collection are treated with an `AND` conjunction (matching current `filter`/`exclude` functionality).

### Search Query Writers

Provided are 3 different `SearchQueryWriter`s for Solr:

* `SolrSearchQueryWriter_Basic`
* `SolrSearchQueryWriter_In`
* `SolrSearchQueryWriter_Range`

When these Writers are provided a `SearchCriterion`, they will generate the desired query string.

### Search Adapters

Search Adapters need to provide the following information:

* What is the search engine's conjunction strings? (EG: are they "AND" and "OR", or are they "&&" and "||", etc).
* What is the desired comparison container string? (EG: "**+(** query here **)**") for Solr).
* Most importantly - how to generate the query string from a `SearchCriterion`.

The `SolrSearchAdapter` uses `SearchQueryWriter`s (above) to generate query strings from a `SearchCriterion`.

### Customising your `SearchCriterion`/`SearchQueryWriter`

If you find that you do not want your `SearchCriterion` being parsed by one of the default `SearchQueryWriter`s (for whatever reason), you can optionally pass your own `SearchQueryWriter` to your `SearchCriterion` either as the **fourth parameter** when instantiating it, or by calling `setSearchQueryWriter()`.

If this value is set, then the (default Solr) Adapter will always use the provided `SearchQueryWriter`, rather than deciding for itself.

This should allow you to have full control over how your query strings are being generated if the default `SearchQueryWriter`s are not cutting it for you.

## Connectors

### Solr

See Solr.md

### Sphinx

Not written yet

## FAQ

### How do I exclude draft pages from the index?

By default, the `SearchUpdater` class indexes all available "variant states",
so in the case of the `Versioned` extension, both "draft" and "live".
For most cases, you'll want to exclude draft content from your search results.

You can either prevent the draft content from being indexed in the first place,
by adding the following to your `SearchIndex->init()` method:

	$this->excludeVariantState(array('SearchVariantVersioned' => 'Stage'));

Alternatively, you can index draft content, but simply exclude it from searches. 
This can be handy to preview search results on unpublished content, in case a CMS author is logged in.
Before constructing your `SearchQuery`, conditionally switch to the "live" stage:

	if(!Permission::check('CMS_ACCESS_CMSMain')) Versioned::reading_stage('Live');
	$query = new SearchQuery();
	// ...

### How do I write nested/complex filters?

See "Filtering by Criteria".
