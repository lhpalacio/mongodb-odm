<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests;

use function count;

class QueryLogger implements \Countable
{
    private $queries = [];

    /**
     * Log a query.
     *
     * @param array $query
     */
    public function __invoke(array $query)
    {
        $this->queries[] = $query;
    }

    /**
     * Clears the logged queries.
     */
    public function clear()
    {
        $this->queries = [];
    }

    /**
     * Returns the number of logged queries.
     *
     * @see php.net/countable.count
     * @return int
     */
    public function count()
    {
        return count($this->queries);
    }

    /**
     * Returns the logged queries.
     *
     * @return array
     */
    public function getAll()
    {
        return $this->queries;
    }
}
