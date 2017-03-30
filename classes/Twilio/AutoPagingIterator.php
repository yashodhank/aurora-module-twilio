<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

class Services_Twilio_AutoPagingIterator
    implements Iterator
{
    protected $generator;
    protected $args;
    protected $items;

    private $_args;

    public function __construct($generator, $page, $size, $filters) {
        $this->generator = $generator;
        $this->page = $page;
        $this->size = $size;
        $this->filters = $filters;
        $this->items = array();

        // Save a backup for rewind()
        $this->_args = array(
            'page' => $page,
            'size' => $size,
            'filters' => $filters,
        );
    }

    public function current()
    {
        return current($this->items);
    }

    public function key()
    {
        return key($this->items);
    }

    /*
     * Return the next item in the list, making another HTTP call to the next
     * page of resources if necessary.
     */
    public function next()
    {
        try {
            $this->loadIfNecessary();
            return next($this->items);
        }
        catch (Services_Twilio_RestException $e) {
            // 20006 is an out of range paging error, everything else is valid
            if ($e->getCode() != 20006) {
                throw $e;
            }
        }
    }

    /*
     * Restore everything to the way it was before we began paging. This gets
     * called at the beginning of any foreach() loop
     */
    public function rewind()
    {
        foreach ($this->_args as $arg => $val) {
            $this->$arg = $val;
        }
        $this->items = array();
        $this->next_page_uri = null;
    }

    public function count()
    {
        throw new BadMethodCallException('Not allowed');
    }

    public function valid()
    {
        try {
            $this->loadIfNecessary();
            return key($this->items) !== null;
        }
        catch (Services_Twilio_RestException $e) {
            // 20006 is an out of range paging error, everything else is valid
            if ($e->getCode() != 20006) {
                throw $e;
            }
        }
        return false;
    }

    /*
     * Fill $this->items with a new page from the API, if necessary.
     */
    protected function loadIfNecessary()
    {
        if (// Empty because it's the first time or last page was empty
            empty($this->items)
            // null key when the items list is iterated over completely
            || key($this->items) === null
        ) {
            $page = call_user_func_array($this->generator, array(
                $this->page,
                $this->size,
                $this->filters,
                $this->next_page_uri,
            ));
            $this->next_page_uri = $page->next_page_uri;
            $this->items = $page->getItems();
            $this->page = $this->page + 1;
        }
    }
}
