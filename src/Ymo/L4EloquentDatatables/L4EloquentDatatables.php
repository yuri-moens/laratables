<?php namespace Ymo\L4EloquentDatatables;

/**
 * This package will create valid JSON output from Eloquent models
 * to work with jQuery Datatables.
 *
 * @author	Yuri Moens <yuri.moens@gmail.com>
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;

class L4EloquentDatatables {

	/**
	 * The query.
	 * 
	 * @var \Illuminate\Database\Eloquent\Builder
	 */
	private $query;

	/**
	 * The Eloquent model.
	 * 
	 * @var \Illuminate\Database\Eloquent\Model
	 */
	private $model;
	
	/**
	 * Array of all relationships that should be retrieved.
	 * 
	 * @var array
	 */
	private $relationships;
	
	/**
	 * Array of all columns that should be retrieved.
	 * 
	 * @var array
	 */
	private $columns;

	/**
	 * Array to store HTML to be wrapped around columns.
	 * 
	 * @var array
	 */
	private $htmlColumns = [];

	/**
	 * Array with manually added columns.
	 * 
	 * @var array
	 */
	private $addedColumns = [];

	/**
	 * Array with date columns.
	 * 
	 * @var array
	 */
	private $dateColumns = [];

	/**
	 * The number of records.
	 * 
	 * @var int
	 */
	private $countAll;

	/**
	 * The number of filtered records.
	 * 
	 * @var int
	 */
	private $countFiltered;

	/**
	 * Creates an L4EloquentDatatables object with the given model.
	 * 
	 * @param string	$model	The Eloquent model name
	 */
	public function __construct($model)
	{
		$this->model = $this->getModel($model);
	}

	/**
	 * Creates an Eloquent Datatables object with the given model.
	 * 
	 * @param string	$model	The Eloquent model name
	 * @return \Ymo\L4EloquentDatatables\L4EloquentDatatables	The datatables object
	 */
	public static function model($model)
	{
		return new L4EloquentDatatables($model);
	}

	/**
	 * Sets the relationship names of the model that should be used.
	 * 
	 * @param  array	$relationships	Array with all the relationship names
	 * @return \Ymo\L4EloquentDatatables\L4EloquentDatatables	The datatables object
	 */
	public function with($relationships)
	{
		$this->relationships = is_array($relationships) ? $relationships : [ $relationships ];

		return $this;
	}

	/**
	 * Sets the columns of the models that should be returned. Columns of
	 * relationship models should be prefixed with the relationship name
	 * and a dot.
	 * 
	 * @param  array	$columns	Array with all the column names
	 * @return \Ymo\L4EloquentDatatables\L4EloquentDatatables	The datatables object
	 */
	public function columns($columns)
	{
		$this->columns = is_array($columns) ? $columns : [ $columns ];

		return $this;
	}

	/**
	 * Wraps HTML code around a column.
	 * 
	 * @param  string $column      The column to wrap
	 * @param  string $htmlBefore The HTML code to put before the column
	 * @param  string $htmlAfter  The HTML code to put after the column
	 * @return  \Ymo\L4EloquentDatatables\L4EloquentDatatables	The datatables object
	 */
	public function wrapHtml($column, $htmlBefore, $htmlAfter)
	{
		if (in_array($column, $this->columns, true))
			$this->htmlColumns[$column] = [ $htmlBefore, $htmlAfter ];
		else
			throw new \Exception("Could not wrap HTML around column: column not found.");
	
		return $this;
	}

	/**
	 * Manually add a column with content.
	 * 
	 * @param string $column  The column name
	 * @param string $content The content of column fields
	 * @return  \Ymo\L4EloquentDatatables\L4EloquentDatatables	The datatables object
	 */
	public function addColumn($column, $content)
	{
		$this->addedColumns[$column] = $content;

		return $this;
	}

	/**
	 * Format the column by calling formatLocalized on the Carbon result.
	 * This only works when the result is a Carbon object.
	 * Check the Carbon documentation for formatting.
	 * 
	 * @param  string $column The column name
	 * @param  string $format The format to be used by formatLocalized
	 * @return  \Ymo\L4EloquentDatatables\L4EloquentDatatables	The datatables object
	 */
	public function formatDate($column, $format = '%G-%m-%d')
	{
		if (in_array($column, $this->columns, true))
			$this->dateColumns[$column] = $format;
		else
			throw new \Exception("Cannot call a function on this column: column not found.");

		return $this;		
	}

	/**
	 * Parses all the data and calls output which returns a JSON response.
	 * 
	 * @return \Illuminate\Support\Response 	The JSON response
	 */
	public function make()
	{
		if (is_null($this->columns))
			throw new \Exception("Columns have not been set. Set columns with the columns() function.");

		$this->query = $this->query();
		$this->countAll = $this->getCount($this->query);

		$this->query = $this->filter($this->query);
		$this->countFiltered = $this->getCount($this->query);

		$this->query = $this->page($this->query);
		$this->query = $this->sort($this->query);

		return $this->output();
	}

	/**
	 * Returns an object of the given model.
	 * 
	 * @param  string $model Name of the model
	 * @return string        Object of the model
	 */
	private function getModel($model)
	{
		$class = '\\' . $model;

		return new $class;
	}

	/**
	 * Returns the amount of records when executing the given query.
	 * 
	 * @param  \Illuminate\Database\Eloquent\Builder $query The query
	 * @return int        The amount of records
	 */
	private function getCount($query)
	{
		return $query->get()->count();
	}

	/**
	 * Creates a query to retrieve all the records of the model with its
	 * relationships.
	 * 
	 * @return \Illuminate\Database\Eloquent\Builder The query
	 */
	private function query()
	{
		if (is_null($this->relationships))
			return $this->model->newQuery();
		else
			return $this->model->with($this->relationships); 
	}

	/**
	 * Filters the query based on the string passed to use from the Datatables.
	 * It searches all the columns to find a full or partial case-insensitive match.
	 * 
	 * @param  \Illuminate\Database\Eloquent\Builder $query The query
	 * @return \Illuminate\Database\Eloquent\Builder        The filtered query.
	 */
	private function filter($query)
	{
		if (Input::get('sSearch', '') != '') {
			$i = 0;
			$search = '%' . strtolower(Input::get('sSearch')) . '%';
			$firstWhere = true;

			foreach ($this->columns as $column) {
				if (Input::get('bSearchable_' . $i++) == 'true') {
					if (count(explode('.', $column)) > 1) {
						$relationship = explode('.', $column)[0];
						$field = explode('.', $column)[1];
						
						if ($firstWhere) {
							$query->whereHas($relationship, function($q) use ($field, $search) {
								$q->where($field, 'LIKE', $search);
							});
							$firstWhere = false;
						}
						else {
							$query->orWhereHas($relationship, function($q) use ($field, $search) {
								$q->where($field, 'LIKE', $search);
							});
						}
					}
					else {
						if ($firstWhere) {
							$query->where($column, 'LIKE', $search);
							$firstWhere = false;
						}
						else
							$query->orWhere($column, 'LIKE', $search);
					}
				}
			}
		}

		return $query;
	}

	/**
	 * Limits the query to only return results based on the requested page.
	 * 
	 * @param  \Illuminate\Database\Eloquent\Builder $query The original query
	 * @return \Illuminate\Database\Eloquent\Builder        The limited query
	 */
	private function page($query)
	{
		if (!is_null(Input::get('iDisplayStart')) && Input::get('iDisplayLength') != -1)
			return $query->skip(Input::get('iDisplayStart'))->take(Input::get('iDisplayLength', 10));
		else
			return $query;
	}

	/**
	 * Sorts the results of the query based on the requested order.
	 * 
	 * @param  \Illuminate\Database\Eloquent\Builder $query The original query
	 * @return \Illuminate\Database\Eloquent\Builder        The sorted query
	 */
	private function sort($query)
	{
		if (!is_null(Input::get('iSortCol_0')))
			for ($i = 0, $cols = intval(Input::get('iSortingCols')); $i < $cols; $i++)
				if (Input::get('bSortable_'.intval(Input::get('iSortCol_' . $i))) == 'true')
					if (isset($this->columns[intval(Input::get('iSortCol_' . $i))]))
						return $query->orderBy($this->columns[intval(Input::get('iSortCol_' . $i))],
							Input::get('sSortDir_' . $i));

		return $query;
	}

	/**
	 * Executes the query and parses it so Datatables can handle it.
	 * 
	 * @param  \Illuminate\Database\Eloquent\Builder $query The query
	 * @return array        The requested records
	 */
	private function execute($query)
	{
		$results = $query->get($this->columns);

		$array = [];

		foreach ($results as $record) {
			$row = [];

			foreach ($this->columns as $column) {
				$output = '';

				if (count(explode('.', $column)) > 1) {
					$relationship = explode('.', $column)[0];
					$field = explode('.', $column)[1];
					
					$output = (string)$record->$relationship->$field;

					if (array_key_exists($column, $this->dateColumns)) {
						$format = $this->dateColumns[$column];

						$output = $record->$relationship->$field->formatLocalized($format);
					}
				}
				else {
					$output = (string)$record->$column;

					if (array_key_exists($column, $this->dateColumns)) {
						$format = $this->dateColumns[$column];

						$output = $record->$column->formatLocalized($format);
					}
				}

				if (array_key_exists($column, $this->htmlColumns)) {
					$html = $this->htmlColumns[$column];

					for ($i = 0; $i < count($html); $i++)
						$html[$i] = $this->parseHtml($html[$i], $record);

					$output = $html[0] . $output . $html[1];
				}

				$row[] = $output;
			}

			foreach ($this->addedColumns as $column)
				$row[] = $this->parseHtml($column, $record);

			$array[] = $row;
		}

		return $array;
	}

	/**
	 * Creates a JSON response of the parsed data.
	 * 
	 * @return \Illuminate\Support\Response	The JSON response
	 */
	private function output()
	{
		$output = [
			'sEcho' => intval(Input::get('sEcho')),
			'iTotalRecords' => $this->countAll,
			'iTotalDisplayRecords' => $this->countFiltered,
			'aaData' => $this->execute($this->query),
		];

		return Response::json($output);
	}

	/**
	 * Checks the HTML code for any parameters and parses them.
	 * 
	 * @param  string $html   The string with HTML code
	 * @param  \Illuminate\Database\Eloquent\Model $record The record to get data form
	 * @return string         The string with parsed HTML
	 */
	private function parseHtml($html, $record)
	{
		if ($start = strpos($html, '{')) {
			$start++;
			$len = strpos($html, '}') - $start;

			$param = substr($html, $start, $len);

			foreach (explode('->', $param) as $p)
				$record = $record->$p;

			$len += 2;
			$html = substr_replace($html, $record, --$start, $len++);
		}

		return $html;
	}

}