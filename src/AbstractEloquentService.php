<?php

namespace pribolshoy\laravelrepository;

use pribolshoy\repository\services\AbstractCachebleService;

/**
 * Class AbstractEloquentService
 *
 * @package pribolshoy\laravelrepository
 */
abstract class AbstractEloquentService extends AbstractCachebleService
{
    use EloquentServiceTrait;
}

