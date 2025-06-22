<?php

namespace App\Console\Commands;

/**
 * Make Model Command
 * 
 * Creates a new model class
 */
class MakeModelCommand extends BaseCommand
{
    public function execute($arguments)
    {
        if (empty($arguments)) {
            $this->error("Model name is required.");
            $this->info("Usage: php console make:model <ModelName>");
            return;
        }
        
        $modelName = $arguments[0];
        $modelPath = "app/Models/{$modelName}.php";
        
        // Check if model already exists
        if (file_exists($modelPath)) {
            $this->error("Model {$modelName} already exists!");
            return;
        }
        
        // Create model content
        $content = $this->getModelTemplate($modelName);
        
        // Write model file
        if (file_put_contents($modelPath, $content)) {
            $this->success("Model {$modelName} created successfully!");
            $this->info("Location: {$modelPath}");
        } else {
            $this->error("Failed to create model {$modelName}");
        }
    }
    
    private function getModelTemplate($modelName)
    {
        $tableName = strtolower($modelName) . 's'; // Simple pluralization
        
        return "<?php

namespace App\Models;

use App\Core\Model;

/**
 * {$modelName} Model
 * 
 * Generated model class
 */
class {$modelName} extends Model
{
    protected \$table = '{$tableName}';
    protected \$primaryKey = 'id';
    
    /**
     * Get all records
     */
    public function getAll()
    {
        return \$this->db->fetchAll(\"SELECT * FROM {\$this->table} ORDER BY {\$this->primaryKey} DESC\");
    }
    
    /**
     * Get record by ID
     */
    public function getById(\$id)
    {
        return \$this->db->fetchRow(\"SELECT * FROM {\$this->table} WHERE {\$this->primaryKey} = ?\", [\$id]);
    }
    
    /**
     * Create new record
     */
    public function create(\$data)
    {
        \$fields = array_keys(\$data);
        \$placeholders = array_fill(0, count(\$fields), '?');
        
        \$sql = \"INSERT INTO {\$this->table} (\" . implode(', ', \$fields) . \") VALUES (\" . implode(', ', \$placeholders) . \")\";
        
        if (\$this->db->execute(\$sql, array_values(\$data))) {
            return \$this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Update record
     */
    public function update(\$id, \$data)
    {
        \$fields = array_keys(\$data);
        \$setClause = implode(' = ?, ', \$fields) . ' = ?';
        
        \$sql = \"UPDATE {\$this->table} SET {\$setClause} WHERE {\$this->primaryKey} = ?\";
        \$values = array_merge(array_values(\$data), [\$id]);
        
        return \$this->db->execute(\$sql, \$values);
    }
    
    /**
     * Delete record
     */
    public function delete(\$id)
    {
        return \$this->db->execute(\"DELETE FROM {\$this->table} WHERE {\$this->primaryKey} = ?\", [\$id]);
    }
    
    /**
     * Find records by condition
     */
    public function findWhere(\$column, \$value)
    {
        return \$this->db->fetchAll(\"SELECT * FROM {\$this->table} WHERE {\$column} = ?\", [\$value]);
    }
    
    /**
     * Count total records
     */
    public function count()
    {
        return \$this->db->fetchColumn(\"SELECT COUNT(*) FROM {\$this->table}\");
    }
}";
    }
}
