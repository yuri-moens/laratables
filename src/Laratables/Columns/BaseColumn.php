<?php

namespace Ymo\Laratables\Columns;

/**
 * This file is part of Laratables,
 * a helper for generating Datatables 1.10+ usable JSON from Eloquent models.
 *
 * @license MIT
 * @package Ymo\Laratables
 */

abstract class BaseColumn
{
    /**
     * The HTML wrapped around the content.
     *
     * @var array
     */
    public $html = [ "", "" ];

    /**
     * The column name.
     *
     * @var string
     */
    public $name;

    /**
     * Flag to indicate if the column is orderable.
     *
     * @var bool
     */
    public $orderable;

    /**
     * Flag to indicate if the column is searchable.
     *
     * @var bool
     */
    public $searchable;

    /**
     * Create a column.
     *
     * @param $name
     * @param $orderable
     * @param $searchable
     */
    public function __construct($name, $orderable = false, $searchable = false)
    {
        $this->name = $name;
        $this->orderable = $orderable;
        $this->searchable = $searchable;
    }

    /**
     * Parse the given record.
     *
     * @param $record
     * @return mixed
     */
    public abstract function parse($record);

    /**
     * Wrap HTML code around the field content.
     *
     * @param $html
     */
    public function wrapHtml($html)
    {
        if (is_string($html)) {
            $html = func_get_args();
        }

        if (count($html) == 2) {
            $this->addHtmlPrefix($html[0]);
            $this->addHtmlSuffix($html[1]);
        } elseif (count($html) == 1) {
            $this->addHtmlSuffix($html[0]);
        }
    }

    /**
     * Add HTML code before the field content.
     *
     * @param $html
     */
    public function addHtmlPrefix($html)
    {
        $this->html[0] .= $html;
    }

    /**
     * Add HTML code after the field content.
     *
     * @param $html
     */
    public function addHtmlSuffix($html)
    {
        $this->html[1] .= $html;
    }
}
