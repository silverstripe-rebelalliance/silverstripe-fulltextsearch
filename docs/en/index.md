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

Using the `filterBy` method of `SearchQuery` will allow you to have more granular control over what happens with your
filter.

`Criterion`: A single filter requirement. EG: 'Product_Title' == 'My Product'.
`Criteria`: A collection of `Criterion`.

Defined in `AbstractCriterion` are all of the basic comparisons currently supported. EG: `EQUAL` and `NOT_EQUAL` for
direct comparison, `IN` and `NOT_IN` for array value comparison, etc.

To begin with, let's replicate the example from 'Basic Filtering' by using the `filterBy` method. The following example
will add two separate `SearchCriteria` objects to our `criteria` array. Each `SearchCriteria` object has a single
`Criterion` in it's collection:

    // Example 1:
    // Filter by products with stock that are in either of these 3 categories.
    $searchQuery->filterBy('Product_CategoryID', array(21, 24, 25), AbstractCriterion::IN);
    $searchQuery->filterBy('Product_StockLevel', 0, AbstractCriterion::GREATER_THAN);

Method chaining is also available on the `filterBy` method. When using method chaining on `filterBy`, the
`SearchCriteria` object that was created is returned to you for you to chain more requirements to. The following example
will create a single `SearchCriteria` object in our `criteria` array, but that single object will have two `Criterion`:

    // Example 2:
    // Filter by products with stock that are in either of these 3 categories.
    $searchQuery->filterBy('Product_CategoryID', array(21, 24, 25), AbstractCriterion::IN)
	            ->addAnd('Product_StockLevel', 0, AbstractCriterion::GREATER_THAN);

We can gain more control (and depth) over our `Criteria` by explicitly creating these objects first, and then passing
them through to `filterBy`.
This is the same example, but it could allow us to slowly build up our `Criteria` over time through the script, and then
pass it to `filterBy` later on.

    // Example 3:
    // Filter by products in either of these 3 categories.
    $criteria = new SearchCriteria('Product_CategoryID', array(21, 24, 25), AbstractCriterion::IN);
    ... other stuff
    // Exclude any products with no stock.
    $criteria->addAnd('Product_StockLevel', 0, AbstractCriterion::GREATER_THAN);
    ... other stuff
    // Add criteria to Search Query.
    $searchQuery->filterBy($criteria);

Method chaining is available from the `create` static method on `SearchCriteria`:

    // Example 4:
    // Filter by products with stock that are in either of these 3 categories.
    $criteria = SearchCriteria::create('Product_CategoryID', array(21, 24, 25), AbstractCriterion::IN)
	    ->addAnd('Product_StockLevel', 0, AbstractCriterion::GREATER_THAN);
	... other stuff
    // Add criteria to Search Query.
    $searchQuery->filterBy($criteria);

Any of the above examples will result in a filter query that requires a product to have stock, and to be in one of the
3 category IDs that we supplied. Something like:

    if ($this && $that) {}

Let's look into how we can control the groupings so that we could (for example) include all products in the 'Lego'
category (let's give it the ID of `1`), regardless of whether or not that product has stock (because Lego rules). The
outcome I'm looking for is something like:

    if (($this && $that) || $theOther) {}

Lets assume that you are using example 3 or 4 and already have a `Criteria` object for our "stock & in category" filter.
We'll call this `$criteriaOne`. We then need to create a second separate Criteria for the Lego category:

    // Filter by products in the Lego category.
    // EQUAL is the default comparison.
    $criteriaTwo = SearchCriteria::create('Product_CategoryID', 1);

We then need to combine `$criteriaOne` and `$criteriaTwo` into a third "grouped" `Criteria` object.

    $criteriaGrouped = SearchCriteria::create($criteriaOne)
        ->addOr($criteriaTwo);
    $query->filterBy($criteriaGrouped);
              
These filters can become as complex as you like. Any time you want a group of criteria, you just need to construct that
separately first, and then group them appropriately.

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
