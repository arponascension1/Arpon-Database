<?php

namespace Arpon\Database\Query\Grammars;

use Arpon\Database\Grammar as BaseGrammar;
use Arpon\Database\Query\Builder;
use Arpon\Database\Query\JoinClause;

class Grammar extends BaseGrammar
{
    /**
     * The grammar specific operators.
     *
     * @var array
     */
    protected $operators = [];

    /**
     * The components that make up a select clause.
     *
     * @var array
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'unions',
        'lock',
    ];

    /**
     * Compile a select query into SQL.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        // If the query does not have any "select" clauses, we will set a default here
        // since the SQL must have at least a single column selected from a table.
        // We will use the "select *" syntax to select all available table columns.
        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        return trim($this->concatenate(
            $this->compileComponents($query))
        );
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @return array
     */
    protected function compileComponents(Builder $query)
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            // To compile the query, we'll spin through each component of the query and
            // see if that component exists. If it does we'll just call the compiler
            // function for the component which is responsible for making the SQL.
            if (isset($query->$component) && ! is_null($query->$component)) {
                $method = 'compile'.ucfirst($component);

                $sql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $sql;
    }

    /**
     * Compile an aggregated select clause.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        $column = $this->columnize($aggregate['columns']);

        // If the query has a "distinct" constraint and we're not asking for all columns
        // we need to prepend "distinct" onto the column name so that the query takes
        // it into account when it performs the aggregating operations on the data.
        if ($query->distinct && $column !== '*') {
            $column = 'distinct '.$column;
        }

        return 'select '.$aggregate['function'].'('.$column.') as aggregate';
    }

    /**
     * Compile an exists statement into SQL.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @return string
     */
    public function compileExists(Builder $query)
    {
        $select = $this->compileSelect($query);

        return "select exists({$select}) as {$this->wrap('exists')}";
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $columns
     * @return string|null
     */
    protected function compileColumns(Builder $query, $columns)
    {
        // If the query is actually performing an aggregating select, we will let that
        // compiler handle the building of the select clauses, as it will need some
        // more syntax that is best handled by that function to keep things neat.
        if (! is_null($query->aggregate)) {
            return;
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';

        return $select.$this->columnize($columns);
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  string  $table
     * @return string
     */
    protected function compileFrom(Builder $query, $table)
    {
        return 'from '.$this->wrapTable($table);
    }

    /**
     * Compile the "join" portions of the query.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $joins
     * @return string
     */
    protected function compileJoins(Builder $query, $joins)
    {
        return collect($joins)->map(function ($join) {
            if (is_array($join)) {
                // Handle array-based joins
                $on = $this->wrap($join['first']).' '.$join['operator'].' '.$this->wrap($join['second']);
                return $join['type'].' join '.$this->wrapTable($join['table']).' on '.$on;
            } else {
                // Handle object-based joins (for future compatibility)
                return $join->type.' join '.$this->wrapTable($join->table).$this->compileWheres($join);
            }
        })->implode(' ');
    }

    /**
     * Compile the "where" portions of the query.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $wheres
     * @return string
     */
    public function compileWheres(Builder $query, $wheres = null)
    {
        // Each type of where clauses has its own compiler function which is responsible
        // for actually creating the where clauses SQL. This helps keep the code nice
        // and maintainable since each clause has a very small method that it uses.
        if (is_null($wheres)) {
            $wheres = $query->wheres;
        }

        if (count($wheres) > 0) {
            return $this->concatenateWhereClauses($query, $wheres);
        }

        return '';
    }

    /**
     * Format the where clause statements into one string.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $wheres
     * @return string
     */
    protected function concatenateWhereClauses($query, $wheres)
    {
        $conjunction = $query instanceof JoinClause ? 'on' : 'where';

        return $conjunction.' '.$this->removeLeadingBoolean(
            implode(' ', array_map([$this, 'compileWhere'], $wheres))
        );
    }

    /**
     * Compile a single where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhere(array $where)
    {
        return $where['boolean'].' '.$this->{"where{$where['type']}"}($where);
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNull($where)
    {
        return $this->wrap($where['column']).' is null';
    }

    /**
     * Compile a "where not null" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNotNull($where)
    {
        return $this->wrap($where['column']).' is not null';
    }

    /**
     * Compile a basic where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereBasic($where)
    {
        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']).' '.$where['operator'].' '.$value;
    }

    /**
     * Compile a raw where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereRaw($where)
    {
        return $where['sql'];
    }

    /**
     * Compile a nested where clause.
     *
     * @param array $where
     * @return string
     */
    protected function whereNested($where)
    {
        // The nested where contains a query instance which we can compile into
        // a set of where clauses. The compileWheres method returns a string
        // prefixed with the word "where", so we strip that prefix and wrap
        // the remaining clauses in parentheses.
        $compiled = $this->compileWheres($where['query']);

        // Remove the leading 'where ' that compileWheres returns.
        $compiled = preg_replace('/^where\s+/i', '', $compiled);

        return '('.$compiled.')';
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereIn($where)
    {
        if (! empty($where['values'])) {
            return $this->wrap($where['column']).' in ('.$this->parameterize($where['values']).')';
        }

        return '0 = 1';
    }

    /**
     * Compile a "where not in" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNotIn($where)
    {
        if (! empty($where['values'])) {
            return $this->wrap($where['column']).' not in ('.$this->parameterize($where['values']).')';
        }

        return '1 = 1';
    }

    /**
     * Compile a "where between" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereBetween($where)
    {
        $between = $where['not'] ? 'not between' : 'between';

        $min = $this->parameter(reset($where['values']));
        $max = $this->parameter(end($where['values']));

        return $this->wrap($where['column']).' '.$between.' '.$min.' and '.$max;
    }

    /**
     * Compile a where exists clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereExists($where)
    {
        return 'exists ('.$this->compileSelect($where['query']).')';
    }

    /**
     * Compile a where not exists clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNotExists($where)
    {
        return 'not exists ('.$this->compileSelect($where['query']).')';
    }

    /**
     * Compile a "group by" clause.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $groups
     * @return string
     */
    protected function compileGroups(Builder $query, $groups)
    {
        return 'group by '.$this->columnize($groups);
    }

    /**
     * Compile a "having" clause.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $havings
     * @return string
     */
    protected function compileHavings(Builder $query, $havings)
    {
        $sql = implode(' ', array_map([$this, 'compileHaving'], $havings));

        return 'having '.$this->removeLeadingBoolean($sql);
    }

    /**
     * Compile a single having clause.
     *
     * @param  array  $having
     * @return string
     */
    protected function compileHaving(array $having)
    {
        // If the having clause is "raw", we can just return the clause straight away
        // without doing any more processing on it. Otherwise, we will compile the
        // clause into SQL based on the components that make it up from builder.
        if ($having['type'] === 'Raw') {
            return $having['boolean'].' '.$having['sql'];
        } elseif ($having['type'] === 'between') {
            return $this->compileHavingBetween($having);
        }

        return $this->compileBasicHaving($having);
    }

    /**
     * Compile a basic having clause.
     *
     * @param  array  $having
     * @return string
     */
    protected function compileBasicHaving($having)
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return $having['boolean'].' '.$column.' '.$having['operator'].' '.$parameter;
    }

    /**
     * Compile a "between" having clause.
     *
     * @param  array  $having
     * @return string
     */
    protected function compileHavingBetween($having)
    {
        $between = $having['not'] ? 'not between' : 'between';

        $column = $this->wrap($having['column']);

        $min = $this->parameter(head($having['values']));
        $max = $this->parameter(last($having['values']));

        return $having['boolean'].' '.$column.' '.$between.' '.$min.' and '.$max;
    }

    /**
     * Compile the "order by" portions of the query.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $orders
     * @return string
     */
    protected function compileOrders(Builder $query, $orders)
    {
        if (! empty($orders)) {
            return 'order by '.implode(', ', $this->compileOrdersToArray($query, $orders));
        }

        return '';
    }

    /**
     * Compile the query orders to an array.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $orders
     * @return array
     */
    protected function compileOrdersToArray(Builder $query, $orders)
    {
        return array_map(function ($order) {
            return $order['sql'] ?? $this->wrap($order['column']).' '.$order['direction'];
        }, $orders);
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return 'limit '.(int) $limit;
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return 'offset '.(int) $offset;
    }

    /**
     * Compile the "union" queries attached to the main query.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $unions
     * @return string
     */
    protected function compileUnions(Builder $query, $unions)
    {
        $sql = '';

        foreach ($unions as $union) {
            $sql .= $this->compileUnion($union);
        }

        if (! empty($sql)) {
            return trim($sql);
        }

        return '';
    }

    /**
     * Compile a single union statement.
     *
     * @param  array  $union
     * @return string
     */
    protected function compileUnion(array $union)
    {
        $conjunction = $union['all'] ? ' union all ' : ' union ';

        return $conjunction.$union['query']->toSql();
    }

    /**
     * Compile a "lock" clause.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  bool|string  $value
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->wrapTable($query->from);

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));

        // We need to build a list of parameter place-holders of values that are bound
        // to the query. Each insert should have the exact same number of parameter
        // bindings so we will loop through the record and parameterize them all.
        $parameters = collect($values)->map(function ($record) {
            return '('.$this->parameterize($record).')';
        })->implode(', ');

        return "insert into $table ($columns) values $parameters";
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileUpdate(Builder $query, array $values)
    {
        $table = $this->wrapTable($query->from);

        // Each one of the columns in the update statements needs to be wrapped in the
        // keyword identifiers, also a place-holder needs to be created for each of
        // the values in the list of bindings so we can make the sets statements.
        $columns = collect($values)->map(function ($value, $key) {
            return $this->wrap($key).' = '.$this->parameter($value);
        })->implode(', ');

        // If the query has any "join" clauses, we will setup the joins on the builder
        // and compile them so we can attach them to this update, as update queries
        // can get join statements to attach to other tables when they're needed.
        $joins = '';

        if (isset($query->joins)) {
            $joins = ' '.$this->compileJoins($query, $query->joins);
        }

        // Of course, update queries may also be constrained by where clauses so we'll
        // need to compile the where clauses and attach it to the query so only the
        // intended records are updated by the SQL statements we generate to run.
        $wheres = $this->compileWheres($query);

        return rtrim("update {$table}{$joins} set $columns $wheres");
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        $table = $this->wrapTable($query->from);

        $wheres = is_array($query->wheres) ? $this->compileWheres($query) : '';

        return rtrim("delete from $table $wheres");
    }

    /**
     * Compile a truncate table statement into SQL.
     *
     * @param  \Arpon\Database\Query\Builder  $query
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return ['truncate table '.$this->wrapTable($query->from) => []];
    }

    /**
     * Determine if the grammar supports savepoints.
     *
     * @return bool
     */
    public function supportsSavepoints()
    {
        return true;
    }

    /**
     * Compile the SQL statement to define a savepoint.
     *
     * @param  string  $name
     * @return string
     */
    public function compileSavepoint($name)
    {
        return 'SAVEPOINT '.$name;
    }

    /**
     * Compile the SQL statement to execute a savepoint rollback.
     *
     * @param  string  $name
     * @return string
     */
    public function compileSavepointRollBack($name)
    {
        return 'ROLLBACK TO SAVEPOINT '.$name;
    }

    /**
     * Concatenate an array of segments, removing empties.
     *
     * @param  array  $segments
     * @return string
     */
    protected function concatenate($segments)
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    /**
     * Remove the leading boolean from a statement.
     *
     * @param  string  $value
     * @return string
     */
    protected function removeLeadingBoolean($value)
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    /**
     * Get all of the available clause operators.
     *
     * @return array
     */
    public function getOperators()
    {
        return $this->operators;
    }
}

