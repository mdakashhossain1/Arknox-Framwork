<?php

namespace App\Core\Database;

/**
 * Base Model Class
 * 
 * Enhanced model with query builder support
 * Similar to Laravel's Eloquent model
 */
abstract class Model
{
    protected $table;
    protected $primaryKey = 'id';
    protected $connection = null;
    protected $fillable = [];
    protected $guarded = ['*'];
    protected $hidden = [];
    protected $casts = [];
    protected $timestamps = true;
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $softDeletes = false;
    protected $dates = ['created_at', 'updated_at'];
    protected $appends = [];
    protected $visible = [];
    protected $with = [];

    // Instance properties
    protected $attributes = [];
    protected $original = [];
    protected $relations = [];
    protected $exists = false;
    protected $wasRecentlyCreated = false;

    // Timestamp column names
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const DELETED_AT = 'deleted_at';
    
    public function __construct()
    {
        if (!$this->table) {
            $this->table = $this->getDefaultTableName();
        }
    }
    
    /**
     * Get default table name from class name
     */
    protected function getDefaultTableName()
    {
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower($className) . 's'; // Simple pluralization
    }
    
    /**
     * Get query builder for this model
     */
    public function newQuery()
    {
        return DB::table($this->table, $this->connection);
    }
    
    /**
     * Create a new query builder instance
     */
    public static function query()
    {
        return (new static)->newQuery();
    }
    
    /**
     * Get all records
     */
    public static function all($columns = ['*'])
    {
        return static::query()->select($columns)->get();
    }
    
    /**
     * Find a record by ID
     */
    public static function find($id, $columns = ['*'])
    {
        return static::query()->select($columns)->find($id);
    }
    
    /**
     * Find a record by ID or throw exception
     */
    public static function findOrFail($id, $columns = ['*'])
    {
        $result = static::find($id, $columns);
        
        if (!$result) {
            throw new \Exception("Model not found with ID: {$id}");
        }
        
        return $result;
    }
    
    /**
     * Find records by column value
     */
    public static function where($column, $operator = null, $value = null)
    {
        return static::query()->where($column, $operator, $value);
    }
    
    /**
     * Find first record matching conditions
     */
    public static function first($columns = ['*'])
    {
        return static::query()->select($columns)->first();
    }
    
    /**
     * Create a new record
     */
    public static function create(array $attributes)
    {
        $instance = new static;
        $attributes = $instance->fillableFromArray($attributes);
        
        if ($instance->timestamps) {
            $now = date($instance->dateFormat);
            $attributes[static::CREATED_AT] = $now;
            $attributes[static::UPDATED_AT] = $now;
        }
        
        $id = $instance->newQuery()->insert($attributes);
        
        if ($id) {
            return static::find($id);
        }
        
        return false;
    }
    
    /**
     * Update records
     */
    public static function whereUpdate($conditions, array $attributes)
    {
        $instance = new static;
        $attributes = $instance->fillableFromArray($attributes);
        
        if ($instance->timestamps) {
            $attributes[static::UPDATED_AT] = date($instance->dateFormat);
        }
        
        $query = $instance->newQuery();
        
        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }
        
        return $query->update($attributes);
    }
    
    /**
     * Delete records
     */
    public static function destroy($ids)
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        
        return static::query()->whereIn((new static)->primaryKey, $ids)->delete();
    }
    
    /**
     * Get count of records
     */
    public static function count()
    {
        return static::query()->count();
    }
    
    /**
     * Check if records exist
     */
    public static function exists()
    {
        return static::query()->exists();
    }
    
    /**
     * Get max value of column
     */
    public static function max($column)
    {
        return static::query()->max($column);
    }
    
    /**
     * Get min value of column
     */
    public static function min($column)
    {
        return static::query()->min($column);
    }
    
    /**
     * Get average value of column
     */
    public static function avg($column)
    {
        return static::query()->avg($column);
    }
    
    /**
     * Get sum of column
     */
    public static function sum($column)
    {
        return static::query()->sum($column);
    }
    
    /**
     * Paginate results
     */
    public static function paginate($page = 1, $perPage = 15)
    {
        $query = static::query();
        $total = $query->count();
        $results = $query->forPage($page, $perPage)->get();
        
        return [
            'data' => $results,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'from' => (($page - 1) * $perPage) + 1,
            'to' => min($page * $perPage, $total),
        ];
    }
    
    /**
     * Get fillable attributes from array
     */
    protected function fillableFromArray(array $attributes)
    {
        if (count($this->fillable) > 0) {
            return array_intersect_key($attributes, array_flip($this->fillable));
        }
        
        if ($this->guarded === ['*']) {
            return [];
        }
        
        return array_diff_key($attributes, array_flip($this->guarded));
    }
    
    /**
     * Get table name
     */
    public function getTable()
    {
        return $this->table;
    }
    
    /**
     * Set table name
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }
    
    /**
     * Get primary key
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }
    
    /**
     * Set primary key
     */
    public function setKeyName($key)
    {
        $this->primaryKey = $key;
        return $this;
    }
    
    /**
     * Get connection name
     */
    public function getConnectionName()
    {
        return $this->connection;
    }
    
    /**
     * Set connection name
     */
    public function setConnection($name)
    {
        $this->connection = $name;
        return $this;
    }
    
    /**
     * Enable query logging for this model
     */
    public static function enableQueryLog()
    {
        $instance = new static;
        return DB::enableQueryLog($instance->connection);
    }
    
    /**
     * Get query log for this model
     */
    public static function getQueryLog()
    {
        $instance = new static;
        return DB::getQueryLog($instance->connection);
    }
    
    /**
     * Get table information
     */
    public static function getTableInfo()
    {
        $instance = new static;
        return DB::getTableInfo($instance->table, $instance->connection);
    }
    
    /**
     * Truncate table
     */
    public static function truncate()
    {
        $instance = new static;
        return DB::statement("TRUNCATE TABLE {$instance->table}", [], $instance->connection);
    }

    // ========================================
    // ELOQUENT-STYLE RELATIONSHIP METHODS
    // ========================================

    /**
     * Define a one-to-one relationship
     */
    protected function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasOneRelation($this, $instance, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-many relationship
     */
    protected function hasMany($related, $foreignKey = null, $localKey = null)
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasManyRelation($this, $instance, $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or one-to-many relationship
     */
    protected function belongsTo($related, $foreignKey = null, $ownerKey = null)
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: $instance->getForeignKey();
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return new BelongsToRelation($this, $instance, $foreignKey, $ownerKey);
    }

    /**
     * Define a many-to-many relationship
     */
    protected function belongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null)
    {
        $instance = new $related;
        $table = $table ?: $this->joiningTable($related);
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        return new BelongsToManyRelation($this, $instance, $table, $foreignPivotKey, $relatedPivotKey);
    }

    /**
     * Get the default foreign key name for the model
     */
    public function getForeignKey()
    {
        return strtolower(class_basename($this)) . '_' . $this->getKeyName();
    }

    /**
     * Get the joining table name for a many-to-many relationship
     */
    protected function joiningTable($related)
    {
        $models = [
            strtolower(class_basename($this)),
            strtolower(class_basename($related))
        ];

        sort($models);

        return implode('_', $models);
    }

    // ========================================
    // ELOQUENT-STYLE ATTRIBUTE METHODS
    // ========================================

    /**
     * Fill the model with an array of attributes
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Set a given attribute on the model
     */
    public function setAttribute($key, $value)
    {
        // Check for mutator
        $mutator = 'set' . $this->studlyCase($key) . 'Attribute';
        if (method_exists($this, $mutator)) {
            return $this->{$mutator}($value);
        }

        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get an attribute from the model
     */
    public function getAttribute($key)
    {
        if (!$key) {
            return null;
        }

        // Check if it's in attributes
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttributeValue($key);
        }

        // Check if it's a relationship
        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        return null;
    }

    /**
     * Get a plain attribute value
     */
    protected function getAttributeValue($key)
    {
        $value = $this->attributes[$key];

        // Check for accessor
        $accessor = 'get' . $this->studlyCase($key) . 'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->{$accessor}($value);
        }

        // Cast the value
        return $this->castAttribute($key, $value);
    }

    /**
     * Cast an attribute to a native PHP type
     */
    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        $cast = $this->casts[$key] ?? null;

        switch ($cast) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
            case 'json':
                return json_decode($value, true);
            case 'datetime':
                return new \DateTime($value);
            default:
                return $value;
        }
    }

    /**
     * Get a relationship value
     */
    protected function getRelationValue($key)
    {
        if (isset($this->relations[$key])) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            $relation = $this->{$key}();
            $this->relations[$key] = $relation->getResults();
            return $this->relations[$key];
        }

        return null;
    }

    /**
     * Determine if the given attribute is fillable
     */
    public function isFillable($key)
    {
        if (in_array($key, $this->fillable)) {
            return true;
        }

        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->fillable) && !str_starts_with($key, '_');
    }

    /**
     * Determine if the given key is guarded
     */
    public function isGuarded($key)
    {
        return in_array($key, $this->guarded) || $this->guarded === ['*'];
    }

    /**
     * Convert the model to its string representation
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Convert the model instance to JSON
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert the model instance to an array
     */
    public function toArray()
    {
        $attributes = $this->attributesToArray();
        $relations = $this->relationsToArray();
        $appended = $this->getAppendedAttributes();

        return array_merge($attributes, $relations, $appended);
    }

    /**
     * Convert the model's attributes to an array
     */
    protected function attributesToArray()
    {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            if ($this->hasGetMutator($key)) {
                $value = $this->getAttributeValue($key);
            } else {
                $value = $this->castAttribute($key, $value);
            }

            // Apply date formatting
            if (in_array($key, $this->getDates()) && !is_null($value)) {
                $value = $this->serializeDate($value);
            }

            $attributes[$key] = $value;
        }

        // Apply visible/hidden filters
        if (!empty($this->visible)) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        if (!empty($this->hidden)) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }

    /**
     * Convert the model's relations to an array
     */
    protected function relationsToArray()
    {
        $relations = [];

        foreach ($this->relations as $key => $value) {
            if (in_array($key, $this->hidden)) {
                continue;
            }

            if (!empty($this->visible) && !in_array($key, $this->visible)) {
                continue;
            }

            if (is_array($value)) {
                $relations[$key] = array_map(function($item) {
                    return $item instanceof Model ? $item->toArray() : $item;
                }, $value);
            } elseif ($value instanceof Model) {
                $relations[$key] = $value->toArray();
            } elseif ($value instanceof Collection) {
                $relations[$key] = $value->toArray();
            } else {
                $relations[$key] = $value;
            }
        }

        return $relations;
    }

    /**
     * Convert a string to studly case
     */
    protected function studlyCase($value)
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }

    /**
     * Dynamically retrieve attributes on the model
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute exists on the model
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]) || isset($this->relations[$key]);
    }

    /**
     * Unset an attribute on the model
     */
    public function __unset($key)
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    // ========================================
    // INSTANCE METHODS FOR ACTIVE RECORD
    // ========================================

    /**
     * Save the model to the database
     */
    public function save()
    {
        if ($this->exists) {
            return $this->performUpdate();
        } else {
            return $this->performInsert();
        }
    }

    /**
     * Perform a model insert operation
     */
    protected function performInsert()
    {
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        $attributes = $this->getAttributesForInsert();
        $id = $this->newQuery()->insert($attributes);

        if ($id) {
            $this->setAttribute($this->getKeyName(), $id);
            $this->exists = true;
            $this->wasRecentlyCreated = true;
            $this->syncOriginal();
            return true;
        }

        return false;
    }

    /**
     * Perform a model update operation
     */
    protected function performUpdate()
    {
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        $updated = $this->newQuery()
                       ->where($this->getKeyName(), $this->getKey())
                       ->update($dirty);

        if ($updated) {
            $this->syncOriginal();
            return true;
        }

        return false;
    }

    /**
     * Delete the model from the database
     */
    public function delete()
    {
        if (!$this->exists) {
            return false;
        }

        if ($this->softDeletes) {
            return $this->performSoftDelete();
        }

        $deleted = $this->newQuery()
                       ->where($this->getKeyName(), $this->getKey())
                       ->delete();

        if ($deleted) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * Perform a soft delete on the model
     */
    protected function performSoftDelete()
    {
        $this->setAttribute(static::DELETED_AT, date($this->dateFormat));
        return $this->save();
    }

    /**
     * Restore a soft-deleted model
     */
    public function restore()
    {
        if (!$this->softDeletes) {
            return false;
        }

        $this->setAttribute(static::DELETED_AT, null);
        return $this->save();
    }

    /**
     * Force delete a soft-deleted model
     */
    public function forceDelete()
    {
        $this->softDeletes = false;
        return $this->delete();
    }

    /**
     * Update the model's timestamps
     */
    protected function updateTimestamps()
    {
        $time = date($this->dateFormat);

        if (!$this->exists && !$this->isDirty(static::CREATED_AT)) {
            $this->setAttribute(static::CREATED_AT, $time);
        }

        if (!$this->isDirty(static::UPDATED_AT)) {
            $this->setAttribute(static::UPDATED_AT, $time);
        }
    }

    /**
     * Get the attributes that should be converted to dates
     */
    public function getDates()
    {
        $defaults = [static::CREATED_AT, static::UPDATED_AT];

        if ($this->softDeletes) {
            $defaults[] = static::DELETED_AT;
        }

        return array_unique(array_merge($this->dates, $defaults));
    }

    /**
     * Get the primary key value
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the attributes for insert
     */
    protected function getAttributesForInsert()
    {
        return $this->fillableFromArray($this->attributes);
    }

    /**
     * Get the dirty attributes
     */
    public function getDirty()
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $this->fillableFromArray($dirty);
    }

    /**
     * Determine if the model or given attribute(s) have been modified
     */
    public function isDirty($attributes = null)
    {
        $dirty = $this->getDirty();

        if (is_null($attributes)) {
            return count($dirty) > 0;
        }

        if (!is_array($attributes)) {
            $attributes = [$attributes];
        }

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sync the original attributes with the current
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;
        return $this;
    }

    /**
     * Get the original attribute values
     */
    public function getOriginal($key = null, $default = null)
    {
        if ($key) {
            return $this->original[$key] ?? $default;
        }

        return $this->original;
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Apply a scope to the query
     */
    public function scopeWhere($query, $column, $operator = null, $value = null)
    {
        return $query->where($column, $operator, $value);
    }

    /**
     * Apply a global scope for soft deletes
     */
    public function scopeWithTrashed($query)
    {
        if ($this->softDeletes) {
            return $query; // Remove the soft delete constraint
        }
        return $query;
    }

    /**
     * Apply a scope to only get trashed models
     */
    public function scopeOnlyTrashed($query)
    {
        if ($this->softDeletes) {
            return $query->whereNotNull(static::DELETED_AT);
        }
        return $query;
    }

    /**
     * Apply a scope to exclude trashed models
     */
    public function scopeWithoutTrashed($query)
    {
        if ($this->softDeletes) {
            return $query->whereNull(static::DELETED_AT);
        }
        return $query;
    }

    // ========================================
    // MODEL EVENTS & OBSERVERS
    // ========================================

    protected static $events = [
        'creating', 'created', 'updating', 'updated',
        'saving', 'saved', 'deleting', 'deleted',
        'restoring', 'restored'
    ];

    protected static $observers = [];
    protected static $globalScopes = [];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        static::bootTraits();
        static::registerGlobalScopes();
    }

    /**
     * Boot all of the bootable traits on the model
     */
    protected static function bootTraits()
    {
        $class = static::class;

        foreach (class_uses_recursive($class) as $trait) {
            if (method_exists($class, $method = 'boot'.class_basename($trait))) {
                forward_static_call([$class, $method]);
            }
        }
    }

    /**
     * Register global scopes for the model
     */
    protected static function registerGlobalScopes()
    {
        if (property_exists(static::class, 'softDeletes') && static::$softDeletes) {
            static::addGlobalScope('softDelete', function($query) {
                $query->whereNull(static::DELETED_AT);
            });
        }
    }

    /**
     * Add a global scope to the model
     */
    public static function addGlobalScope($identifier, $scope)
    {
        static::$globalScopes[static::class][$identifier] = $scope;
    }

    /**
     * Remove a global scope from the model
     */
    public static function removeGlobalScope($identifier)
    {
        unset(static::$globalScopes[static::class][$identifier]);
    }

    /**
     * Fire a model event
     */
    protected function fireModelEvent($event, $halt = true)
    {
        if (!isset(static::$observers[static::class])) {
            return true;
        }

        $method = $event;
        $observers = static::$observers[static::class];

        foreach ($observers as $observer) {
            if (method_exists($observer, $method)) {
                $result = $observer->{$method}($this);

                if ($halt && $result === false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Register an observer with the model
     */
    public static function observe($observer)
    {
        $instance = is_string($observer) ? new $observer : $observer;
        static::$observers[static::class][] = $instance;
    }

    // ========================================
    // ADVANCED RELATIONSHIP METHODS
    // ========================================

    /**
     * Define a polymorphic one-to-one relationship
     */
    protected function morphOne($related, $name, $type = null, $id = null, $localKey = null)
    {
        $instance = new $related;
        $type = $type ?: $name.'_type';
        $id = $id ?: $name.'_id';
        $localKey = $localKey ?: $this->getKeyName();

        return new MorphOneRelation($this, $instance, $type, $id, $localKey);
    }

    /**
     * Define a polymorphic one-to-many relationship
     */
    protected function morphMany($related, $name, $type = null, $id = null, $localKey = null)
    {
        $instance = new $related;
        $type = $type ?: $name.'_type';
        $id = $id ?: $name.'_id';
        $localKey = $localKey ?: $this->getKeyName();

        return new MorphManyRelation($this, $instance, $type, $id, $localKey);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship
     */
    protected function morphTo($name = null, $type = null, $id = null, $ownerKey = null)
    {
        $name = $name ?: $this->guessBelongsToRelation();
        $type = $type ?: $name.'_type';
        $id = $id ?: $name.'_id';

        return new MorphToRelation($this, $type, $id, $ownerKey);
    }

    /**
     * Define a polymorphic many-to-many relationship
     */
    protected function morphToMany($related, $name, $table = null, $foreignPivotKey = null,
                                  $relatedPivotKey = null, $parentKey = null, $relatedKey = null)
    {
        $instance = new $related;
        $table = $table ?: $name;
        $foreignPivotKey = $foreignPivotKey ?: $name.'_id';
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();
        $parentKey = $parentKey ?: $this->getKeyName();
        $relatedKey = $relatedKey ?: $instance->getKeyName();

        return new MorphToManyRelation($this, $instance, $name, $table,
                                     $foreignPivotKey, $relatedPivotKey,
                                     $parentKey, $relatedKey);
    }

    /**
     * Define a polymorphic many-to-many inverse relationship
     */
    protected function morphedByMany($related, $name, $table = null, $foreignPivotKey = null,
                                    $relatedPivotKey = null, $parentKey = null, $relatedKey = null)
    {
        $instance = new $related;
        $table = $table ?: $name;
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: $name.'_id';
        $parentKey = $parentKey ?: $this->getKeyName();
        $relatedKey = $relatedKey ?: $instance->getKeyName();

        return new MorphToManyRelation($instance, $this, $name, $table,
                                     $relatedPivotKey, $foreignPivotKey,
                                     $relatedKey, $parentKey, true);
    }

    /**
     * Guess the "belongs to" relationship name
     */
    protected function guessBelongsToRelation()
    {
        list(, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        return $caller['function'];
    }

    // ========================================
    // ADVANCED QUERY METHODS
    // ========================================

    /**
     * Create a new instance of the model
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = new static((array) $attributes);
        $model->exists = $exists;
        $model->setConnection($this->getConnectionName());
        $model->setTable($this->getTable());

        return $model;
    }

    /**
     * Create a new model instance that is existing
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $model = $this->newInstance([], true);
        $model->setRawAttributes((array) $attributes, true);
        $model->setConnection($connection ?: $this->getConnectionName());

        return $model;
    }

    /**
     * Set the array of model attributes. No checking is done
     */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Get all of the current attributes on the model
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Set the specific relationship in the model
     */
    public function setRelation($relation, $value)
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    /**
     * Unset a loaded relationship
     */
    public function unsetRelation($relation)
    {
        unset($this->relations[$relation]);
        return $this;
    }

    /**
     * Get all the loaded relations for the instance
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Set the entire relations array on the model
     */
    public function setRelations(array $relations)
    {
        $this->relations = $relations;
        return $this;
    }

    // ========================================
    // COLLECTION & SERIALIZATION METHODS
    // ========================================

    /**
     * Create a new Eloquent Collection instance
     */
    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }

    /**
     * Get the appended attributes
     */
    protected function getAppendedAttributes()
    {
        $appended = [];

        foreach ($this->appends as $key) {
            $appended[$key] = $this->getAttribute($key);
        }

        return $appended;
    }

    /**
     * Serialize a date for array/JSON representation
     */
    protected function serializeDate($date)
    {
        if ($date instanceof \DateTime) {
            return $date->format($this->dateFormat);
        }

        return $date;
    }

    /**
     * Determine if a get mutator exists for an attribute
     */
    public function hasGetMutator($key)
    {
        return method_exists($this, 'get'.Str::studly($key).'Attribute');
    }

    /**
     * Determine if a set mutator exists for an attribute
     */
    public function hasSetMutator($key)
    {
        return method_exists($this, 'set'.Str::studly($key).'Attribute');
    }

    // ========================================
    // UTILITY & HELPER METHODS
    // ========================================

    /**
     * Clone the model into a new, non-existing instance
     */
    public function replicate(array $except = null)
    {
        $defaults = [
            $this->getKeyName(),
            static::CREATED_AT,
            static::UPDATED_AT,
        ];

        if ($this->softDeletes) {
            $defaults[] = static::DELETED_AT;
        }

        $except = $except ? array_unique(array_merge($except, $defaults)) : $defaults;

        $attributes = array_diff_key($this->attributes, array_flip($except));

        $instance = new static;
        $instance->setRawAttributes($attributes);
        $instance->setRelations($this->relations);

        return $instance;
    }

    /**
     * Determine if the model uses timestamps
     */
    public function usesTimestamps()
    {
        return $this->timestamps;
    }

    /**
     * Get the name of the "created at" column
     */
    public function getCreatedAtColumn()
    {
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column
     */
    public function getUpdatedAtColumn()
    {
        return static::UPDATED_AT;
    }

    /**
     * Get the name of the "deleted at" column
     */
    public function getDeletedAtColumn()
    {
        return static::DELETED_AT;
    }

    /**
     * Get the fully qualified "deleted at" column
     */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }

    /**
     * Qualify the given column name by the model's table
     */
    public function qualifyColumn($column)
    {
        if (strpos($column, '.') !== false) {
            return $column;
        }

        return $this->getTable().'.'.$column;
    }

    /**
     * Get the value indicating whether the IDs are incrementing
     */
    public function getIncrementing()
    {
        return $this->incrementing ?? true;
    }

    /**
     * Set whether IDs are incrementing
     */
    public function setIncrementing($value)
    {
        $this->incrementing = $value;
        return $this;
    }

    /**
     * Get the auto-incrementing key type
     */
    public function getKeyType()
    {
        return $this->keyType ?? 'int';
    }

    /**
     * Set the data type for the primary key
     */
    public function setKeyType($type)
    {
        $this->keyType = $type;
        return $this;
    }

    /**
     * Get the route key for the model
     */
    public function getRouteKey()
    {
        return $this->getAttribute($this->getRouteKeyName());
    }

    /**
     * Get the route key name for the model
     */
    public function getRouteKeyName()
    {
        return $this->getKeyName();
    }

    /**
     * Retrieve the model for a bound value
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Get the value of the model's route key
     */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return $this->resolveRouteBinding($value, $field);
    }

    /**
     * Determine if two models have the same ID and belong to the same table
     */
    public function is($model)
    {
        return !is_null($model) &&
               $this->getKey() === $model->getKey() &&
               $this->getTable() === $model->getTable() &&
               $this->getConnectionName() === $model->getConnectionName();
    }

    /**
     * Determine if two models are not the same
     */
    public function isNot($model)
    {
        return !$this->is($model);
    }

    /**
     * Get the database connection for the model
     */
    public function getConnection()
    {
        return Database::getInstance($this->getConnectionName());
    }

    /**
     * Get a fresh timestamp for the model
     */
    public function freshTimestamp()
    {
        return new \DateTime;
    }

    /**
     * Get a fresh timestamp for the model as a string
     */
    public function freshTimestampString()
    {
        return $this->fromDateTime($this->freshTimestamp());
    }

    /**
     * Convert a DateTime to a storable string
     */
    public function fromDateTime($value)
    {
        if ($value instanceof \DateTime) {
            return $value->format($this->getDateFormat());
        }

        return $value;
    }

    /**
     * Get the format for database stored dates
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * Set the date format used by the model
     */
    public function setDateFormat($format)
    {
        $this->dateFormat = $format;
        return $this;
    }

    /**
     * Handle dynamic method calls into the model
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, ['increment', 'decrement'])) {
            return $this->$method(...$parameters);
        }

        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }

    /**
     * Handle dynamic static method calls into the method
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Forward a method call to the given object
     */
    protected function forwardCallTo($object, $method, $parameters)
    {
        try {
            return $object->{$method}(...$parameters);
        } catch (\Error|\BadMethodCallException $e) {
            $pattern = '~^Call to undefined method (?P<class>[^:]+)::(?P<method>[^\(]+)\(\)$~';

            if (!preg_match($pattern, $e->getMessage(), $matches)) {
                throw $e;
            }

            if ($matches['class'] != get_class($object) ||
                $matches['method'] != $method) {
                throw $e;
            }

            throw new \BadMethodCallException(sprintf(
                'Call to undefined method %s::%s()', static::class, $method
            ));
        }
    }

    // ========================================
    // MISSING QUERY BUILDER METHODS
    // ========================================

    /**
     * Add an "order by" clause to the query
     */
    public static function orderBy($column, $direction = 'asc')
    {
        return static::query()->orderBy($column, $direction);
    }

    /**
     * Add a limit clause to the query
     */
    public static function limit($count)
    {
        return static::query()->limit($count);
    }

    /**
     * Eager load relationships
     */
    public static function with($relations)
    {
        $instance = new static();
        $instance->with = is_array($relations) ? $relations : func_get_args();
        return $instance->newQuery();
    }

    /**
     * Process large datasets in chunks
     */
    public static function chunk($count, $callback)
    {
        $offset = 0;

        do {
            $results = static::query()
                            ->offset($offset)
                            ->limit($count)
                            ->get();

            if (empty($results) || count($results) === 0) {
                break;
            }

            $callback($results);
            $offset += $count;

        } while (count($results) == $count);

        return true;
    }

    /**
     * Include soft deleted models in results
     */
    public static function withTrashed()
    {
        $instance = new static();
        return $instance->newQuery()->withTrashed();
    }

    /**
     * Get only soft deleted models
     */
    public static function onlyTrashed()
    {
        $instance = new static();
        return $instance->newQuery()->onlyTrashed();
    }

    /**
     * Check if model uses soft deletes
     */
    protected function usesSoftDeletes()
    {
        return in_array('deleted_at', $this->getDates());
    }
}
