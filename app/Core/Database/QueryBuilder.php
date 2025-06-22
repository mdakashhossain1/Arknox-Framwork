<?php

namespace App\Core\Database;

/**
 * Query Builder
 * 
 * Fluent query builder for database operations
 * Similar to Laravel's query builder
 */
class QueryBuilder
{
    private $connection;
    private $table;
    private $select = ['*'];
    private $joins = [];
    private $wheres = [];
    private $groups = [];
    private $havings = [];
    private $orders = [];
    private $limit = null;
    private $offset = null;
    private $bindings = [];
    
    public function __construct(Connection $connection, $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }
    
    /**
     * Set the columns to select
     */
    public function select($columns = ['*'])
    {
        $this->select = is_array($columns) ? $columns : func_get_args();
        return $this;
    }
    
    /**
     * Add a select column
     */
    public function addSelect($column)
    {
        if (!in_array($column, $this->select)) {
            $this->select[] = $column;
        }
        return $this;
    }
    
    /**
     * Add a join clause
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'INNER')
    {
        if ($operator === null) {
            // Assume $first is the full join condition
            $this->joins[] = "{$type} JOIN {$table} ON {$first}";
        } else {
            $this->joins[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        }
        return $this;
    }
    
    /**
     * Add a left join clause
     */
    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }
    
    /**
     * Add a right join clause
     */
    public function rightJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }
    
    /**
     * Add a where clause
     */
    public function where($column, $operator = null, $value = null, $boolean = 'AND')
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val, $boolean);
            }
            return $this;
        }
        
        if ($operator === null) {
            $operator = '=';
            $value = $column;
        }
        
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $placeholder = $this->addBinding($value);
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $placeholder,
            'boolean' => $boolean
        ];
        
        return $this;
    }
    
    /**
     * Add an or where clause
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'OR');
    }
    
    /**
     * Add a where in clause
     */
    public function whereIn($column, $values, $boolean = 'AND', $not = false)
    {
        $placeholders = [];
        foreach ($values as $value) {
            $placeholders[] = $this->addBinding($value);
        }
        
        $type = $not ? 'NOT IN' : 'IN';
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $placeholders,
            'boolean' => $boolean,
            'not' => $not
        ];
        
        return $this;
    }
    
    /**
     * Add a where not in clause
     */
    public function whereNotIn($column, $values, $boolean = 'AND')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }
    
    /**
     * Add a where null clause
     */
    public function whereNull($column, $boolean = 'AND', $not = false)
    {
        $type = $not ? 'NOT NULL' : 'NULL';
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
            'not' => $not
        ];
        
        return $this;
    }
    
    /**
     * Add a where not null clause
     */
    public function whereNotNull($column, $boolean = 'AND')
    {
        return $this->whereNull($column, $boolean, true);
    }
    
    /**
     * Add a where between clause
     */
    public function whereBetween($column, $values, $boolean = 'AND', $not = false)
    {
        $min = $this->addBinding($values[0]);
        $max = $this->addBinding($values[1]);
        
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => $boolean,
            'not' => $not
        ];
        
        return $this;
    }
    
    /**
     * Add a where not between clause
     */
    public function whereNotBetween($column, $values, $boolean = 'AND')
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }
    
    /**
     * Add a where like clause
     */
    public function whereLike($column, $value, $boolean = 'AND')
    {
        return $this->where($column, 'LIKE', $value, $boolean);
    }
    
    /**
     * Add a group by clause
     */
    public function groupBy($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }
    
    /**
     * Add a having clause
     */
    public function having($column, $operator = null, $value = null, $boolean = 'AND')
    {
        if ($operator === null) {
            $operator = '=';
            $value = $column;
        }
        
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $placeholder = $this->addBinding($value);
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $placeholder,
            'boolean' => $boolean
        ];
        
        return $this;
    }
    
    /**
     * Add an order by clause
     */
    public function orderBy($column, $direction = 'ASC')
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        return $this;
    }
    
    /**
     * Add an order by desc clause
     */
    public function orderByDesc($column)
    {
        return $this->orderBy($column, 'DESC');
    }
    
    /**
     * Set the limit
     */
    public function limit($value)
    {
        $this->limit = $value;
        return $this;
    }
    
    /**
     * Set the offset
     */
    public function offset($value)
    {
        $this->offset = $value;
        return $this;
    }
    
    /**
     * Set limit and offset for pagination
     */
    public function forPage($page, $perPage = 15)
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * Execute the query and get all results
     */
    public function get()
    {
        $sql = $this->toSql();
        return $this->connection->select($sql, $this->getBindings());
    }

    /**
     * Execute the query and get first result
     */
    public function first()
    {
        $sql = $this->limit(1)->toSql();
        return $this->connection->selectOne($sql, $this->getBindings());
    }

    /**
     * Find a record by ID
     */
    public function find($id)
    {
        return $this->where('id', $id)->first();
    }

    /**
     * Get a single column value
     */
    public function value($column)
    {
        $sql = $this->select([$column])->limit(1)->toSql();
        return $this->connection->scalar($sql, $this->getBindings());
    }

    /**
     * Get count of records
     */
    public function count($column = '*')
    {
        $sql = $this->select(["COUNT({$column}) as count"])->toSql();
        $result = $this->connection->selectOne($sql, $this->getBindings());
        return (int) $result['count'];
    }

    /**
     * Get max value
     */
    public function max($column)
    {
        $sql = $this->select(["MAX({$column}) as max"])->toSql();
        $result = $this->connection->selectOne($sql, $this->getBindings());
        return $result['max'];
    }

    /**
     * Get min value
     */
    public function min($column)
    {
        $sql = $this->select(["MIN({$column}) as min"])->toSql();
        $result = $this->connection->selectOne($sql, $this->getBindings());
        return $result['min'];
    }

    /**
     * Get average value
     */
    public function avg($column)
    {
        $sql = $this->select(["AVG({$column}) as avg"])->toSql();
        $result = $this->connection->selectOne($sql, $this->getBindings());
        return $result['avg'];
    }

    /**
     * Get sum value
     */
    public function sum($column)
    {
        $sql = $this->select(["SUM({$column}) as sum"])->toSql();
        $result = $this->connection->selectOne($sql, $this->getBindings());
        return $result['sum'];
    }

    /**
     * Check if records exist
     */
    public function exists()
    {
        return $this->count() > 0;
    }

    /**
     * Insert a record
     */
    public function insert(array $values)
    {
        if (empty($values)) {
            return false;
        }

        // Handle array of arrays (multiple inserts)
        if (is_array(reset($values))) {
            return $this->insertMultiple($values);
        }

        $columns = array_keys($values);
        $placeholders = array_fill(0, count($values), '?');

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        return $this->connection->insert($sql, array_values($values));
    }

    /**
     * Insert multiple records
     */
    public function insertMultiple(array $values)
    {
        if (empty($values)) {
            return false;
        }

        $columns = array_keys(reset($values));
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = array_fill(0, count($values), $placeholders);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES " . implode(', ', $allPlaceholders);

        $bindings = [];
        foreach ($values as $row) {
            $bindings = array_merge($bindings, array_values($row));
        }

        return $this->connection->statement($sql, $bindings);
    }

    /**
     * Update records
     */
    public function update(array $values)
    {
        if (empty($values)) {
            return 0;
        }

        $sets = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWheres();
            $bindings = array_merge($bindings, $this->getBindings());
        }

        return $this->connection->update($sql, $bindings);
    }

    /**
     * Delete records
     */
    public function delete()
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWheres();
        }

        return $this->connection->delete($sql, $this->getBindings());
    }

    /**
     * Increment a column value
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        $updates = array_merge($extra, [$column => "RAW:{$column} + {$amount}"]);
        return $this->update($updates);
    }

    /**
     * Decrement a column value
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        $updates = array_merge($extra, [$column => "RAW:{$column} - {$amount}"]);
        return $this->update($updates);
    }

    /**
     * Build the SQL query
     */
    public function toSql()
    {
        $sql = 'SELECT ' . implode(', ', $this->select) . ' FROM ' . $this->table;

        // Add joins
        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        // Add where clauses
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWheres();
        }

        // Add group by
        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        // Add having
        if (!empty($this->havings)) {
            $sql .= ' HAVING ' . $this->buildHavings();
        }

        // Add order by
        if (!empty($this->orders)) {
            $orderClauses = [];
            foreach ($this->orders as $order) {
                $orderClauses[] = $order['column'] . ' ' . $order['direction'];
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        // Add limit
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        // Add offset
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * Build where clauses
     */
    private function buildWheres()
    {
        $clauses = [];

        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : $where['boolean'] . ' ';

            switch ($where['type']) {
                case 'basic':
                    $clauses[] = $boolean . $where['column'] . ' ' . $where['operator'] . ' ' . $where['value'];
                    break;

                case 'in':
                    $operator = $where['not'] ? 'NOT IN' : 'IN';
                    $clauses[] = $boolean . $where['column'] . ' ' . $operator . ' (' . implode(', ', $where['values']) . ')';
                    break;

                case 'null':
                    $operator = $where['not'] ? 'IS NOT NULL' : 'IS NULL';
                    $clauses[] = $boolean . $where['column'] . ' ' . $operator;
                    break;

                case 'between':
                    $operator = $where['not'] ? 'NOT BETWEEN' : 'BETWEEN';
                    $clauses[] = $boolean . $where['column'] . ' ' . $operator . ' ' . $where['min'] . ' AND ' . $where['max'];
                    break;
            }
        }

        return implode(' ', $clauses);
    }

    /**
     * Build having clauses
     */
    private function buildHavings()
    {
        $clauses = [];

        foreach ($this->havings as $i => $having) {
            $boolean = $i === 0 ? '' : $having['boolean'] . ' ';
            $clauses[] = $boolean . $having['column'] . ' ' . $having['operator'] . ' ' . $having['value'];
        }

        return implode(' ', $clauses);
    }

    /**
     * Add a binding and return placeholder
     */
    private function addBinding($value)
    {
        $this->bindings[] = $value;
        return '?';
    }

    /**
     * Get all bindings
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * Clear all bindings
     */
    public function clearBindings()
    {
        $this->bindings = [];
        return $this;
    }

    /**
     * Clone the query builder
     */
    public function clone()
    {
        return clone $this;
    }

    /**
     * Get the table name
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Set the table name
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Get the connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Debug the query
     */
    public function dd()
    {
        echo "SQL: " . $this->toSql() . "\n";
        echo "Bindings: " . json_encode($this->getBindings()) . "\n";
        die();
    }

    /**
     * Dump the query
     */
    public function dump()
    {
        echo "SQL: " . $this->toSql() . "\n";
        echo "Bindings: " . json_encode($this->getBindings()) . "\n";
        return $this;
    }
}
