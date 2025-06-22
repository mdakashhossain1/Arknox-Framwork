<?php

namespace App\Core\Database\Relations;

use App\Core\Database\Model;
use App\Core\Database\QueryBuilder;

/**
 * Base Relation Class
 * 
 * Abstract base class for all Eloquent-style relationships
 */
abstract class Relation
{
    protected $query;
    protected $parent;
    protected $related;

    public function __construct(Model $parent, Model $related)
    {
        $this->parent = $parent;
        $this->related = $related;
        $this->query = $related->newQuery();
    }

    /**
     * Get the results of the relationship
     */
    abstract public function getResults();

    /**
     * Add constraints to the relationship query
     */
    abstract public function addConstraints();

    /**
     * Get the underlying query builder
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Get the parent model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Get the related model
     */
    public function getRelated()
    {
        return $this->related;
    }

    /**
     * Handle dynamic method calls
     */
    public function __call($method, $parameters)
    {
        $result = $this->query->{$method}(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}

/**
 * Has One Relation
 */
class HasOneRelation extends Relation
{
    protected $foreignKey;
    protected $localKey;

    public function __construct(Model $parent, Model $related, $foreignKey, $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        
        parent::__construct($parent, $related);
        $this->addConstraints();
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->getParentKey());
        }
    }

    public function getResults()
    {
        return $this->query->first();
    }

    protected function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    protected static $constraints = true;
}

/**
 * Has Many Relation
 */
class HasManyRelation extends Relation
{
    protected $foreignKey;
    protected $localKey;

    public function __construct(Model $parent, Model $related, $foreignKey, $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        
        parent::__construct($parent, $related);
        $this->addConstraints();
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->getParentKey());
        }
    }

    public function getResults()
    {
        return $this->query->get();
    }

    protected function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    protected static $constraints = true;
}

/**
 * Belongs To Relation
 */
class BelongsToRelation extends Relation
{
    protected $foreignKey;
    protected $ownerKey;

    public function __construct(Model $parent, Model $related, $foreignKey, $ownerKey)
    {
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        
        parent::__construct($parent, $related);
        $this->addConstraints();
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->ownerKey, '=', $this->getForeignKey());
        }
    }

    public function getResults()
    {
        return $this->query->first();
    }

    protected function getForeignKey()
    {
        return $this->parent->getAttribute($this->foreignKey);
    }

    protected static $constraints = true;
}

/**
 * Belongs To Many Relation
 */
class BelongsToManyRelation extends Relation
{
    protected $table;
    protected $foreignPivotKey;
    protected $relatedPivotKey;
    protected $parentKey;
    protected $relatedKey;

    public function __construct(Model $parent, Model $related, $table, $foreignPivotKey, $relatedPivotKey, $parentKey = null, $relatedKey = null)
    {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey ?: $parent->getKeyName();
        $this->relatedKey = $relatedKey ?: $related->getKeyName();
        
        parent::__construct($parent, $related);
        $this->addConstraints();
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $this->performJoin();
            $this->query->where($this->table . '.' . $this->foreignPivotKey, '=', $this->getParentKey());
        }
    }

    public function getResults()
    {
        return $this->query->get();
    }

    protected function performJoin()
    {
        $this->query->join(
            $this->table,
            $this->related->getTable() . '.' . $this->relatedKey,
            '=',
            $this->table . '.' . $this->relatedPivotKey
        );
    }

    protected function getParentKey()
    {
        return $this->parent->getAttribute($this->parentKey);
    }

    /**
     * Attach models to the relationship
     */
    public function attach($id, array $attributes = [])
    {
        $attributes[$this->foreignPivotKey] = $this->getParentKey();
        $attributes[$this->relatedPivotKey] = $id;
        
        return $this->parent->newQuery()->getConnection()->table($this->table)->insert($attributes);
    }

    /**
     * Detach models from the relationship
     */
    public function detach($ids = null)
    {
        $query = $this->parent->newQuery()->getConnection()->table($this->table)
                     ->where($this->foreignPivotKey, $this->getParentKey());

        if (!is_null($ids)) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    /**
     * Sync the relationship with given IDs
     */
    public function sync(array $ids)
    {
        $this->detach();
        
        foreach ($ids as $id) {
            $this->attach($id);
        }
        
        return $this;
    }

    protected static $constraints = true;
}

/**
 * Morph To Relation (Polymorphic)
 */
class MorphToRelation extends Relation
{
    protected $morphType;
    protected $morphId;
    protected $ownerKey;

    public function __construct(Model $parent, $morphType, $morphId, $ownerKey)
    {
        $this->morphType = $morphType;
        $this->morphId = $morphId;
        $this->ownerKey = $ownerKey;
        
        $relatedClass = $parent->getAttribute($morphType);
        $related = new $relatedClass;
        
        parent::__construct($parent, $related);
        $this->addConstraints();
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->ownerKey, '=', $this->parent->getAttribute($this->morphId));
        }
    }

    public function getResults()
    {
        return $this->query->first();
    }

    protected static $constraints = true;
}

/**
 * Morph One Relation (Polymorphic)
 */
class MorphOneRelation extends Relation
{
    protected $morphType;
    protected $morphId;
    protected $localKey;

    public function __construct(Model $parent, Model $related, $morphType, $morphId, $localKey)
    {
        $this->morphType = $morphType;
        $this->morphId = $morphId;
        $this->localKey = $localKey;
        
        parent::__construct($parent, $related);
        $this->addConstraints();
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->morphId, '=', $this->getParentKey())
                        ->where($this->morphType, '=', get_class($this->parent));
        }
    }

    public function getResults()
    {
        return $this->query->first();
    }

    protected function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    protected static $constraints = true;
}

/**
 * Morph Many Relation (Polymorphic)
 */
class MorphManyRelation extends MorphOneRelation
{
    public function getResults()
    {
        return $this->query->get();
    }
}
