<?php

namespace Ymo\Laratables;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Ymo\Laratables\Columns\DataColumn;
use Ymo\Laratables\Columns\RawColumn;

/**
 * This file is part of Laratables,
 * a helper for generating Datatables 1.10+ usable JSON from Eloquent models.
 *
 * @license MIT
 * @package Ymo\Laratables
 */

class Laratables
{
    /**
     * The columns to be returned.
     *
     * @var array
     */
    protected $columns;

    /**
     * The configuration repository.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * The Eloquent model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * The query builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $query;

    /**
     * The HTTP request.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Create a Laratables request.
     *
     * @param \Illuminate\Contracts\Config\Repository $config
     * @param \Illuminate\Http\Request $request
     */
    public function __construct(Repository $config, Request $request)
    {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * Set the model.
     *
     * @param $model
     * @return $this
     */
    public function model($model)
    {
        if (is_string($model)) {
            $model = '\\' . $model;

            $this->model = new $model;
        } else {
            $this->model = $model;
        }

        return $this;
    }

    /**
     * Set relationships to eager load.
     *
     * @param  $relationships
     * @return $this
     */
    public function with($relationships)
    {
        if (is_string($relationships)) {
            $relationships = func_get_args();
        }

        $this->query()->with($relationships);

        return $this;
    }

    /**
     * Set the columns to be returned.
     *
     * @param $columns
     * @return $this
     */
    public function columns($columns)
    {
        if (is_string($columns)) {
            $columns = func_get_args();
        }

        $i = 0;

        foreach ($columns as $column) {
            $orderable = ($this->request->get("columns")[$i]["orderable"] === "true");
            $searchable = ($this->request->get("columns")[$i]["searchable"] === "true");
            $this->columns[] = new DataColumn($column, $orderable, $searchable);
            $i++;
        }

        return $this;
    }

    /**
     * Add a column with raw content.
     *
     * @param $name
     * @param $content
     * @return $this
     */
    public function addRawColumn($name, $content)
    {
        $this->columns[] = new RawColumn($name, $content);

        return $this;
    }

    /**
     * Wrap HTML around the content of the column fields.
     *
     * @param $columnName
     * @param $html
     * @return $this
     */
    public function wrapHtml($columnName, $html)
    {
        foreach ($this->columns as $column) {
            if ($column->name === $columnName) {
                $column->wrapHtml($html);
            }
        }

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param $column
     * @param null $operator
     * @param null $value
     * @param string $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->query()->where($column, $operator, $value, $boolean);

        return $this;
    }

    /**
     * Add a relationship count condition to the query with where clauses.
     *
     * @param  string $relation
     * @param callable|\Ymo\Laratables\Closure $callback
     * @param  string $operator
     * @param  int $count
     * @param string $boolean
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function whereHas($relation, Closure $callback, $operator = '>=', $count = 1, $boolean = 'and')
    {
        $this->query()->has($relation, $operator, $count, $boolean, $callback);

        return $this;
    }

    /**
     * Get the Eloquent models.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function get()
    {
        $models = $this->query()->get();
        $this->query = null;
        return $models;
    }

    /**
     * Get the amount of models.
     *
     * @return int
     */
    public function count()
    {
        return $this->query()->get()->count();
    }

    /**
     * Create JSON output.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function make()
    {
        $recordsTotal = $this->count();

        $this->filter();
        $recordsFiltered = $this->count();

        $this->paginate();
        $this->sort();

        $output = [
            'draw' => intval($this->request->get("draw")),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $this->parse(),
        ];

        return \Response::json($output);
    }

    /**
     * Filter the query.
     */
    protected function filter()
    {
        if ($this->request->get('search')['value'] === "") {
            return;
        }

        $search = '%' . $this->request->get('search')['value'] . '%';
        $boolean = 'and';

        foreach ($this->columns as $column) {
            if ($column->searchable) {
                if (count(explode('.', $column->name)) > 1) {
                    $relationship = explode('.', $column->name)[0];
                    $relationshipColumn = explode('.', $column->name)[1];

                    $this->whereHas($relationship, function ($q) use ($relationshipColumn, $search) {
                        $q->where($relationshipColumn, 'LIKE', $search);
                    }, $boolean);
                } else {
                    $this->where($column->name, 'LIKE', $search, $boolean);
                }
            }

            $boolean = 'or';
        }
    }

    /**
     * Limits the query to only return results based on the requested page.
     */
    protected function paginate()
    {
        if (! is_null($this->request->get('start'))) {
            $this->query()
                ->skip($this->request->get('start'))
                ->take($this->request->get('length', $this->config->get('laratables.defaultLength')));
        }
    }

    /**
     * Parse the records.
     */
    protected function parse()
    {
        $records = $this->get();

        $results = [];

        foreach ($records as $record) {
            $row = [];

            foreach ($this->columns as $column) {
                $row[] = $column->parse($record);
            }

            for ($i = 0; $i < count($row); $i++) {
                $row[$i] = $this->parseHtml($row[$i], $record);
            }

            $results[] = $row;
        }

        return $results;
    }

    /**
     * Parse variables in HTML.
     *
     * @param $field
     * @param \Illuminate\Database\Eloquent\Model $record
     * @return string
     */
    protected function parseHtml($field, Model $record)
    {
        do {
            if ($start = strpos($field, '{')) {
                $start++;
                $len = strpos($field, '}') - $start;

                $params = substr($field, $start, $len);
                $result = $record;

                foreach (explode('->', $params) as $param) {
                    if ($result != null) {
                        $result = $result->$param;
                    }
                }

                $len += 2;
                $field = substr_replace($field, $result, --$start, $len);
            }
        } while (strpos($field, '{') !== false);

        return $field;
    }

    /**
     * Add an order by clause to the query.
     */
    protected function sort()
    {
        $column = $this->columns[$this->request->get("order")[0]["column"]];
        $direction = $this->request->get("order")[0]["dir"];

        if ($column->orderable) {
            $this->query()->orderBy($column->name, $direction);
        }
    }

    /**
     * Get a query builder instance.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function query()
    {
        if ($this->query == null) {
            $this->query = $this->model->newQuery();
        }

        return $this->query;
    }
}
