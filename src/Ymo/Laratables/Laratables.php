<?php

namespace Ymo\Laratables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;

class Laratables {

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
     * Array of all where clauses with their optional relationships.
     *
     * @var array
     */
    private $whereClauses = [];

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
     * Set the model.
     *
     * @param $model
     * @return $this
     */
    public function model($model)
    {
        $model = '\\' . $model;

        $this->model = new $model;

        return $this;
    }

    /**
     * Sets the relationship names of the model that should be used.
     *
     * @param  array $relationships
     * @return $this
     */
    public function with(array $relationships)
    {
        $this->relationships = $relationships;

        return $this;
    }

    /**
     * Sets the columns of the models that should be returned.
     *
     * @param  array $columns
     * @return $this
     */
    public function columns(array $columns)
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Wraps HTML code around a column.
     *
     * @param  string $column
     * @param  string $htmlBefore
     * @param  string $htmlAfter
     * @throws \Exception
     * @return $this
     */
    public function wrapHtml($column, $htmlBefore, $htmlAfter)
    {
        if (in_array($column, $this->columns, true)) {
            $this->htmlColumns[$column] = [ $htmlBefore, $htmlAfter ];
        } else {
            throw new \Exception("Could not wrap HTML around column: column not found.");
        }

        return $this;
    }

    /**
     * Manually add a column with content.
     *
     * @param string $column
     * @param string $content
     * @return  $this
     */
    public function addColumn($column, $content)
    {
        $this->addedColumns[$column] = $content;

        return $this;
    }

    /**
     * Add a where clause to the model or a relationship.
     *
     * @param array $where
     * @param string $relationship
     * @throws \Exception
     * @return  $this
     */
    public function addWhere($where, $relationship = null)
    {
        if (is_null($relationship)) {
            $this->whereClauses[] = $where;
        } else {
            if (in_array($relationship, $this->relationships, true)) {
                $this->whereClauses[] = [ $where, $relationship ];
            } else {
                throw new \Exception("Could not add where clause: relationship  not found.");
            }
        }

        return $this;
    }

    /**
     * Format the column by calling formatLocalized on the Carbon result.
     *
     * @param  string $column
     * @param  string $format
     * @throws \Exception
     * @return  $this
     */
    public function formatDate($column, $format = '%G-%m-%d')
    {
        if (in_array($column, $this->columns, true)) {
            $this->dateColumns[$column] = $format;
        } else {
            throw new \Exception("Cannot call a function on this column: column not found.");
        }

        return $this;
    }

    /**
     * Parses all the data and calls output which returns a JSON response.
     *
     * @throws \Exception
     * @return \Illuminate\Support\Response
     */
    public function make()
    {
        if (is_null($this->columns)) {
            throw new \Exception("Columns have not been set. Set columns with the columns() function.");
        }

        $this->query = $this->query();
        $this->countAll = $this->getCount($this->query);

        $this->query = $this->filter($this->query);
        $this->countFiltered = $this->getCount($this->query);

        $this->query = $this->page($this->query);
        $this->query = $this->sort($this->query);

        return $this->output();
    }

    /**
     * Get the amount of records when executing the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return int
     */
    private function getCount(Builder $query)
    {
        return $query->get()->count();
    }

    /**
     * Creates a query to retrieve all the records of the model with its
     * relationships.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function query()
    {
        $query = is_null($this->relationships) ? $this->model->newQuery() : $this->model->with($this->relationships);

        foreach ($this->whereClauses as $whereClause) {
            if (count($whereClause) == 3) { // where on main model
                $query->where($whereClause[0], $whereClause[1], $whereClause[2]);
            } else { // where on relationship model
                $where = $whereClause[0];
                $relationship = $whereClause[1];

                $query->whereHas($relationship, function ($q) use (&$where) {
                    $q->where($where[0], $where[1], $where[2]);
                });
            }
        }

        return $query;
    }

    /**
     * Filters the query based on the string passed to use from the Datatables.
     * It searches all the columns to find a full or partial case-insensitive match.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function filter(Builder $query)
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
                            $query->whereHas($relationship, function ($q) use ($field, $search) {
                                $q->where($field, 'LIKE', $search);
                            });
                            $firstWhere = false;
                        } else {
                            $query->orWhereHas($relationship, function ($q) use ($field, $search) {
                                $q->where($field, 'LIKE', $search);
                            });
                        }
                    } else {
                        if ($firstWhere) {
                            $query->where($column, 'LIKE', $search);
                            $firstWhere = false;
                        } else {
                            $query->orWhere($column, 'LIKE', $search);
                        }
                    }
                }
            }
        }

        return $query;
    }

    /**
     * Limits the query to only return results based on the requested page.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function page(Builder $query)
    {
        if (!is_null(Input::get('iDisplayStart')) && Input::get('iDisplayLength') != -1) {
            return $query->skip(Input::get('iDisplayStart'))->take(Input::get('iDisplayLength', 10));
        } else {
            return $query;
        }
    }

    /**
     * Sorts the results of the query based on the requested order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function sort(Builder $query)
    {
        if (!is_null(Input::get('iSortCol_0'))) {
            for ($i = 0, $cols = intval(Input::get('iSortingCols')); $i < $cols; $i++) {
                if (Input::get('bSortable_'.intval(Input::get('iSortCol_' . $i))) == 'true') {
                    if (isset($this->columns[intval(Input::get('iSortCol_' . $i))])) {
                        return $query->orderBy($this->columns[intval(Input::get('iSortCol_' . $i))],
                            Input::get('sSortDir_' . $i));
                    }
                }
            }
        }

        return $query;
    }

    /**
     * Executes the query and parses it so Datatables can handle it.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return array
     */
    private function execute(Builder $query)
    {
        $results = $query->get($this->columns);

        $array = [];

        foreach ($results as $record) {
            $row = [];

            foreach ($this->columns as $column) {
                if (count(explode('.', $column)) > 1) {
                    $relationship = explode('.', $column)[0];
                    $field = explode('.', $column)[1];

                    if (is_null($record->$relationship)) {
                        $output = '';
                    } else {
                        $output = (string)$record->$relationship->$field;
                    }

                    if (array_key_exists($column, $this->dateColumns)) {
                        $format = $this->dateColumns[$column];

                        $output = $record->$relationship->$field->formatLocalized($format);
                    }
                } else {
                    $output = (string)$record->$column;

                    if (array_key_exists($column, $this->dateColumns)) {
                        $format = $this->dateColumns[$column];

                        $output = $record->$column->formatLocalized($format);
                    }
                }

                $output = e($output);

                if (array_key_exists($column, $this->htmlColumns)) {
                    $html = $this->htmlColumns[$column];

                    for ($i = 0; $i < count($html); $i++) {
                        $html[$i] = $this->parseHtml($html[$i], $record);
                    }

                    $output = $html[0] . $output . $html[1];
                }

                $row[] = $output;
            }

            foreach ($this->addedColumns as $column) {
                $row[] = $this->parseHtml($column, $record);
            }

            $array[] = $row;
        }

        return $array;
    }

    /**
     * Creates a JSON response of the parsed data.
     *
     * @return \Illuminate\Support\Response
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
     * @param  string $html
     * @param  \Illuminate\Database\Eloquent\Model $record
     * @return string
     */
    private function parseHtml($html, Model $record)
    {
        do {
            if ($start = strpos($html, '{')) {
                $start++;
                $len = strpos($html, '}') - $start;

                $param = substr($html, $start, $len);
                $result = $record;

                foreach (explode('->', $param) as $p) {
                    if (!is_null($result)) {
                        $result = $result->$p;
                    }
                }

                $len += 2;
                $html = substr_replace($html, $result, --$start, $len++);
            }
        } while (strpos($html, '{') !== false);

        return $html;
    }

}