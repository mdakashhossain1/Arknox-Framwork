<?php

namespace App\Core\Database;

use App\Core\Database;

/**
 * Database Migration System
 * 
 * Laravel-style database migrations with schema builder
 */
class Migration
{
    protected $db;
    protected $schema;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->schema = new Schema($this->db);
    }

    /**
     * Run migrations
     */
    public function up()
    {
        // Override in migration classes
    }

    /**
     * Rollback migrations
     */
    public function down()
    {
        // Override in migration classes
    }

    /**
     * Get schema builder
     */
    protected function schema()
    {
        return $this->schema;
    }
}

/**
 * Schema Builder
 * 
 * Fluent interface for building database schemas
 */
class Schema
{
    protected $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new table
     */
    public function create($table, \Closure $callback)
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        
        $sql = $blueprint->toSql();
        return $this->db->statement($sql);
    }

    /**
     * Modify an existing table
     */
    public function table($table, \Closure $callback)
    {
        $blueprint = new Blueprint($table, 'alter');
        $callback($blueprint);
        
        $statements = $blueprint->toSqlStatements();
        
        foreach ($statements as $sql) {
            $this->db->statement($sql);
        }
        
        return true;
    }

    /**
     * Drop a table
     */
    public function drop($table)
    {
        return $this->db->statement("DROP TABLE IF EXISTS `{$table}`");
    }

    /**
     * Drop a table if it exists
     */
    public function dropIfExists($table)
    {
        return $this->drop($table);
    }

    /**
     * Rename a table
     */
    public function rename($from, $to)
    {
        return $this->db->statement("RENAME TABLE `{$from}` TO `{$to}`");
    }

    /**
     * Check if table exists
     */
    public function hasTable($table)
    {
        $result = $this->db->select("SHOW TABLES LIKE ?", [$table]);
        return !empty($result);
    }

    /**
     * Check if column exists
     */
    public function hasColumn($table, $column)
    {
        $result = $this->db->select("SHOW COLUMNS FROM `{$table}` LIKE ?", [$column]);
        return !empty($result);
    }

    /**
     * Get column listing
     */
    public function getColumnListing($table)
    {
        $columns = $this->db->select("SHOW COLUMNS FROM `{$table}`");
        return array_column($columns, 'Field');
    }
}

/**
 * Blueprint Class
 * 
 * Defines table structure for migrations
 */
class Blueprint
{
    protected $table;
    public $columns = [];
    public $commands = [];
    protected $engine = 'InnoDB';
    protected $charset = 'utf8mb4';
    protected $collation = 'utf8mb4_unicode_ci';
    protected $action = 'create';

    public function __construct($table, $action = 'create')
    {
        $this->table = $table;
        $this->action = $action;
    }

    /**
     * Get the table name
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Add an auto-incrementing ID column
     */
    public function id($column = 'id')
    {
        return $this->bigIncrements($column);
    }

    /**
     * Add a big incrementing integer column
     */
    public function bigIncrements($column)
    {
        return $this->addColumn('bigint', $column, [
            'auto_increment' => true,
            'primary' => true,
            'unsigned' => true
        ]);
    }

    /**
     * Add an incrementing integer column
     */
    public function increments($column)
    {
        return $this->addColumn('int', $column, [
            'auto_increment' => true,
            'primary' => true,
            'unsigned' => true
        ]);
    }

    /**
     * Add a string column
     */
    public function string($column, $length = 255)
    {
        return $this->addColumn('varchar', $column, ['length' => $length]);
    }

    /**
     * Add a text column
     */
    public function text($column)
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Add a long text column
     */
    public function longText($column)
    {
        return $this->addColumn('longtext', $column);
    }

    /**
     * Add an integer column
     */
    public function integer($column)
    {
        return $this->addColumn('int', $column);
    }

    /**
     * Add a big integer column
     */
    public function bigInteger($column)
    {
        return $this->addColumn('bigint', $column);
    }

    /**
     * Add a boolean column
     */
    public function boolean($column)
    {
        return $this->addColumn('tinyint', $column, ['length' => 1]);
    }

    /**
     * Add a decimal column
     */
    public function decimal($column, $precision = 8, $scale = 2)
    {
        return $this->addColumn('decimal', $column, [
            'precision' => $precision,
            'scale' => $scale
        ]);
    }

    /**
     * Add a float column
     */
    public function float($column, $precision = 8, $scale = 2)
    {
        return $this->addColumn('float', $column, [
            'precision' => $precision,
            'scale' => $scale
        ]);
    }

    /**
     * Add a date column
     */
    public function date($column)
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Add a datetime column
     */
    public function dateTime($column)
    {
        return $this->addColumn('datetime', $column);
    }

    /**
     * Add a timestamp column
     */
    public function timestamp($column)
    {
        return $this->addColumn('timestamp', $column);
    }

    /**
     * Add created_at and updated_at timestamp columns
     */
    public function timestamps()
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
        return $this;
    }

    /**
     * Add a soft delete timestamp column
     */
    public function softDeletes()
    {
        return $this->timestamp('deleted_at')->nullable();
    }

    /**
     * Add a JSON column
     */
    public function json($column)
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Add an enum column
     */
    public function enum($column, array $allowed)
    {
        return $this->addColumn('enum', $column, ['allowed' => $allowed]);
    }

    /**
     * Add a foreign key column
     */
    public function foreignId($column)
    {
        return $this->bigInteger($column)->unsigned();
    }

    /**
     * Add a foreign key constraint
     */
    public function foreign($column)
    {
        return new ForeignKeyDefinition($this, $column);
    }

    /**
     * Add an index
     */
    public function index($columns, $name = null)
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?: $this->createIndexName('index', $columns);
        
        $this->commands[] = [
            'type' => 'index',
            'name' => $name,
            'columns' => $columns
        ];
        
        return $this;
    }

    /**
     * Add a unique index
     */
    public function unique($columns, $name = null)
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?: $this->createIndexName('unique', $columns);
        
        $this->commands[] = [
            'type' => 'unique',
            'name' => $name,
            'columns' => $columns
        ];
        
        return $this;
    }

    /**
     * Add a primary key
     */
    public function primary($columns, $name = null)
    {
        $columns = is_array($columns) ? $columns : [$columns];

        $this->commands[] = [
            'type' => 'primary',
            'columns' => $columns,
            'name' => $name
        ];

        return $this;
    }

    /**
     * Drop a column
     */
    public function dropColumn($columns)
    {
        $columns = is_array($columns) ? $columns : [$columns];
        
        $this->commands[] = [
            'type' => 'dropColumn',
            'columns' => $columns
        ];
        
        return $this;
    }

    /**
     * Add a column
     */
    protected function addColumn($type, $name, array $parameters = [])
    {
        $column = new ColumnDefinition($type, $name, $parameters);
        $this->columns[] = $column;
        return $column;
    }

    /**
     * Create index name
     */
    protected function createIndexName($type, array $columns)
    {
        $index = strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);
        return str_replace(['-', '.'], '_', $index);
    }

    /**
     * Convert blueprint to SQL
     */
    public function toSql()
    {
        if ($this->action === 'create') {
            return $this->toCreateSql();
        }
        
        return implode(";\n", $this->toSqlStatements()) . ';';
    }

    /**
     * Convert to CREATE TABLE SQL
     */
    protected function toCreateSql()
    {
        $sql = "CREATE TABLE `{$this->table}` (\n";
        
        $columnDefinitions = [];
        foreach ($this->columns as $column) {
            $columnDefinitions[] = '  ' . $column->toSql();
        }
        
        $sql .= implode(",\n", $columnDefinitions);
        
        // Add commands (indexes, etc.)
        foreach ($this->commands as $command) {
            $sql .= ",\n  " . $this->commandToSql($command);
        }
        
        $sql .= "\n) ENGINE={$this->engine} DEFAULT CHARSET={$this->charset} COLLATE={$this->collation}";
        
        return $sql;
    }

    /**
     * Convert to ALTER TABLE SQL statements
     */
    public function toSqlStatements()
    {
        $statements = [];
        
        // Add columns
        foreach ($this->columns as $column) {
            $statements[] = "ALTER TABLE `{$this->table}` ADD COLUMN " . $column->toSql();
        }
        
        // Execute commands
        foreach ($this->commands as $command) {
            if ($command['type'] === 'dropColumn') {
                foreach ($command['columns'] as $column) {
                    $statements[] = "ALTER TABLE `{$this->table}` DROP COLUMN `{$column}`";
                }
            } else {
                $statements[] = "ALTER TABLE `{$this->table}` ADD " . $this->commandToSql($command);
            }
        }
        
        return $statements;
    }

    /**
     * Convert command to SQL
     */
    protected function commandToSql($command)
    {
        switch ($command['type']) {
            case 'primary':
                return "PRIMARY KEY (`" . implode('`, `', $command['columns']) . "`)";
            case 'unique':
                return "UNIQUE KEY `{$command['name']}` (`" . implode('`, `', $command['columns']) . "`)";
            case 'index':
                return "KEY `{$command['name']}` (`" . implode('`, `', $command['columns']) . "`)";
            case 'foreign':
                return "CONSTRAINT `{$command['name']}` FOREIGN KEY (`{$command['column']}`) REFERENCES `{$command['references']['table']}`(`{$command['references']['column']}`)";
            default:
                return '';
        }
    }
}

/**
 * Column Definition Class
 */
class ColumnDefinition
{
    protected $type;
    protected $name;
    protected $parameters;
    protected $nullable = false;
    protected $default = null;
    protected $comment = null;

    public function __construct($type, $name, array $parameters = [])
    {
        $this->type = $type;
        $this->name = $name;
        $this->parameters = $parameters;
    }

    /**
     * Make column nullable
     */
    public function nullable($value = true)
    {
        $this->nullable = $value;
        return $this;
    }

    /**
     * Set default value
     */
    public function default($value)
    {
        $this->default = $value;
        return $this;
    }

    /**
     * Set column comment
     */
    public function comment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Make column unsigned
     */
    public function unsigned()
    {
        $this->parameters['unsigned'] = true;
        return $this;
    }

    /**
     * Convert to SQL
     */
    public function toSql()
    {
        $sql = "`{$this->name}` " . $this->getTypeDefinition();

        if (!$this->nullable) {
            $sql .= ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }

        if ($this->default !== null) {
            if (is_string($this->default)) {
                $sql .= " DEFAULT '{$this->default}'";
            } else {
                $sql .= " DEFAULT {$this->default}";
            }
        }

        if (isset($this->parameters['auto_increment']) && $this->parameters['auto_increment']) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($this->comment) {
            $sql .= " COMMENT '{$this->comment}'";
        }

        return $sql;
    }

    /**
     * Get type definition
     */
    protected function getTypeDefinition()
    {
        switch ($this->type) {
            case 'varchar':
                $length = $this->parameters['length'] ?? 255;
                return "VARCHAR({$length})";
            case 'int':
                $length = $this->parameters['length'] ?? 11;
                $unsigned = isset($this->parameters['unsigned']) ? ' UNSIGNED' : '';
                return "INT({$length}){$unsigned}";
            case 'bigint':
                $length = $this->parameters['length'] ?? 20;
                $unsigned = isset($this->parameters['unsigned']) ? ' UNSIGNED' : '';
                return "BIGINT({$length}){$unsigned}";
            case 'tinyint':
                $length = $this->parameters['length'] ?? 4;
                return "TINYINT({$length})";
            case 'decimal':
                $precision = $this->parameters['precision'] ?? 8;
                $scale = $this->parameters['scale'] ?? 2;
                return "DECIMAL({$precision},{$scale})";
            case 'float':
                $precision = $this->parameters['precision'] ?? 8;
                $scale = $this->parameters['scale'] ?? 2;
                return "FLOAT({$precision},{$scale})";
            case 'enum':
                $allowed = $this->parameters['allowed'] ?? [];
                $values = "'" . implode("','", $allowed) . "'";
                return "ENUM({$values})";
            case 'text':
                return 'TEXT';
            case 'longtext':
                return 'LONGTEXT';
            case 'date':
                return 'DATE';
            case 'datetime':
                return 'DATETIME';
            case 'timestamp':
                return 'TIMESTAMP';
            case 'json':
                return 'JSON';
            default:
                return strtoupper($this->type);
        }
    }
}

/**
 * Foreign Key Definition Class
 */
class ForeignKeyDefinition
{
    protected $blueprint;
    protected $column;
    protected $references;
    protected $onDelete;
    protected $onUpdate;

    public function __construct(Blueprint $blueprint, $column)
    {
        $this->blueprint = $blueprint;
        $this->column = $column;
    }

    /**
     * Set the referenced table and column
     */
    public function references($column)
    {
        $this->references = $column;
        return $this;
    }

    /**
     * Set the referenced table
     */
    public function on($table)
    {
        $this->blueprint->commands[] = [
            'type' => 'foreign',
            'name' => $this->createConstraintName(),
            'column' => $this->column,
            'references' => [
                'table' => $table,
                'column' => $this->references
            ],
            'onDelete' => $this->onDelete,
            'onUpdate' => $this->onUpdate
        ];

        return $this;
    }

    /**
     * Set ON DELETE action
     */
    public function onDelete($action)
    {
        $this->onDelete = $action;
        return $this;
    }

    /**
     * Set ON UPDATE action
     */
    public function onUpdate($action)
    {
        $this->onUpdate = $action;
        return $this;
    }

    /**
     * Set CASCADE on delete
     */
    public function cascadeOnDelete()
    {
        return $this->onDelete('CASCADE');
    }

    /**
     * Set NULL on delete
     */
    public function nullOnDelete()
    {
        return $this->onDelete('SET NULL');
    }

    /**
     * Create constraint name
     */
    protected function createConstraintName()
    {
        return $this->blueprint->getTable() . '_' . $this->column . '_foreign';
    }
}
