<?php

namespace App\Models;

use App\Core\Model;

/**
 * Post Model
 * 
 * Generated model class
 */
class Post extends Model
{
    protected $table = 'posts';
    protected $primaryKey = 'id';
    
    /**
     * Get all records
     */
    public function getAll()
    {
        return $this->db->fetchAll("SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC");
    }
    
    /**
     * Get record by ID
     */
    public function getById($id)
    {
        return $this->db->fetchRow("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?", [$id]);
    }
    
    /**
     * Create new record
     */
    public function create($data)
    {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        if ($this->db->execute($sql, array_values($data))) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Update record
     */
    public function update($id, $data)
    {
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = ?";
        $values = array_merge(array_values($data), [$id]);
        
        return $this->db->execute($sql, $values);
    }
    
    /**
     * Delete record
     */
    public function delete($id)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?", [$id]);
    }
    
    /**
     * Find records by condition
     */
    public function findWhere($column, $value)
    {
        return $this->db->fetchAll("SELECT * FROM {$this->table} WHERE {$column} = ?", [$value]);
    }
    
    /**
     * Count total records
     */
    public function count()
    {
        return $this->db->fetchColumn("SELECT COUNT(*) FROM {$this->table}");
    }
}