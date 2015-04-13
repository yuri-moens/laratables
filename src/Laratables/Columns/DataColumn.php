<?php

namespace Ymo\Laratables\Columns;

/**
 * This file is part of Laratables,
 * a helper for generating Datatables 1.10+ usable JSON from Eloquent models.
 *
 * @license MIT
 * @package Ymo\Laratables
 */

class DataColumn extends BaseColumn
{
    /**
     * Create a column.
     *
     * @param $name
     * @param $orderable
     * @param $searchable
     */
    public function __construct($name, $orderable = false, $searchable = false)
    {
        parent::__construct($name, $orderable, $searchable);
    }

    /**
     * Parse the given record.
     *
     * @param $record
     * @return mixed
     */
    public function parse($record)
    {
        $output = "";

        if (count(explode('.', $this->name)) > 1) { // column in relationship model
            $relationship = explode('.', $this->name)[0];
            $column = explode('.', $this->name)[1];

            if ($record->$relationship != null) {
                $output = (string)$record->$relationship->$column;
            }
        } else { // column in base model
            $column = $this->name;
            $output = (string)$record->$column;
        }

        $output = $this->html[0] . e($output) . $this->html[1]; // sanitize and wrap HTML

        return $output;
    }
}
