<?php

namespace Ymo\Laratables;

/**
 * This file is part of Laratables,
 * a helper for generating Datatables 1.10+ usable JSON from Eloquent models.
 *
 * @license MIT
 * @package Ymo\Laratables
 */

use Illuminate\Support\Facades\Facade;

class LaratablesFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laratables';
    }
}