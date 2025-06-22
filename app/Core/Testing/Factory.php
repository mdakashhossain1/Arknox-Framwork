<?php

namespace App\Core\Testing;

use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;

/**
 * Model Factory
 * 
 * Laravel-style model factories for generating test data
 * with relationships and custom states
 */
class Factory
{
    protected static $factories = [];
    protected static $faker;
    protected $model;
    protected $count = 1;
    protected $states = [];
    protected $afterMaking = [];
    protected $afterCreating = [];

    public function __construct($model)
    {
        $this->model = $model;
        
        if (!static::$faker) {
            static::$faker = FakerFactory::create();
        }
    }

    /**
     * Define a model factory
     */
    public static function define($model, callable $definition)
    {
        static::$factories[$model] = $definition;
    }

    /**
     * Define a model state
     */
    public static function state($model, $state, callable $definition)
    {
        if (!isset(static::$factories[$model . '@' . $state])) {
            static::$factories[$model . '@' . $state] = [];
        }
        
        static::$factories[$model . '@' . $state] = $definition;
    }

    /**
     * Create a factory instance for a model
     */
    public static function of($model)
    {
        return new static($model);
    }

    /**
     * Set the number of models to create
     */
    public function times($count)
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Apply states to the factory
     */
    public function states(...$states)
    {
        $this->states = array_merge($this->states, $states);
        return $this;
    }

    /**
     * Add a callback to run after making models
     */
    public function afterMaking(callable $callback)
    {
        $this->afterMaking[] = $callback;
        return $this;
    }

    /**
     * Add a callback to run after creating models
     */
    public function afterCreating(callable $callback)
    {
        $this->afterCreating[] = $callback;
        return $this;
    }

    /**
     * Make model instances without persisting them
     */
    public function make(array $attributes = [])
    {
        if ($this->count === 1) {
            return $this->makeInstance($attributes);
        }

        $instances = [];
        for ($i = 0; $i < $this->count; $i++) {
            $instances[] = $this->makeInstance($attributes);
        }

        return $instances;
    }

    /**
     * Create and persist model instances
     */
    public function create(array $attributes = [])
    {
        if ($this->count === 1) {
            return $this->createInstance($attributes);
        }

        $instances = [];
        for ($i = 0; $i < $this->count; $i++) {
            $instances[] = $this->createInstance($attributes);
        }

        return $instances;
    }

    /**
     * Create raw attributes array
     */
    public function raw(array $attributes = [])
    {
        return $this->getRawAttributes($attributes);
    }

    /**
     * Make a single model instance
     */
    protected function makeInstance(array $attributes = [])
    {
        $rawAttributes = $this->getRawAttributes($attributes);
        $model = new $this->model($rawAttributes);

        // Run after making callbacks
        foreach ($this->afterMaking as $callback) {
            $callback($model, static::$faker);
        }

        return $model;
    }

    /**
     * Create and persist a single model instance
     */
    protected function createInstance(array $attributes = [])
    {
        $model = $this->makeInstance($attributes);
        $model->save();

        // Run after creating callbacks
        foreach ($this->afterCreating as $callback) {
            $callback($model, static::$faker);
        }

        return $model;
    }

    /**
     * Get raw attributes for the model
     */
    protected function getRawAttributes(array $attributes = [])
    {
        $definition = $this->getDefinition();
        $rawAttributes = $definition(static::$faker);

        // Apply states
        foreach ($this->states as $state) {
            $stateDefinition = $this->getStateDefinition($state);
            if ($stateDefinition) {
                $stateAttributes = $stateDefinition(static::$faker);
                $rawAttributes = array_merge($rawAttributes, $stateAttributes);
            }
        }

        return array_merge($rawAttributes, $attributes);
    }

    /**
     * Get the factory definition for the model
     */
    protected function getDefinition()
    {
        if (!isset(static::$factories[$this->model])) {
            throw new \InvalidArgumentException("No factory defined for model [{$this->model}]");
        }

        return static::$factories[$this->model];
    }

    /**
     * Get the state definition for the model
     */
    protected function getStateDefinition($state)
    {
        $key = $this->model . '@' . $state;
        return static::$factories[$key] ?? null;
    }

    /**
     * Get the Faker instance
     */
    public static function faker()
    {
        if (!static::$faker) {
            static::$faker = FakerFactory::create();
        }

        return static::$faker;
    }

    /**
     * Reset all factories
     */
    public static function reset()
    {
        static::$factories = [];
    }

    /**
     * Get all defined factories
     */
    public static function getFactories()
    {
        return static::$factories;
    }
}

/**
 * Factory Builder
 * 
 * Provides a fluent interface for building complex factory scenarios
 */
class FactoryBuilder
{
    protected $factory;
    protected $relationships = [];

    public function __construct(Factory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Define a relationship for the factory
     */
    public function has($related, $count = 1, $relationship = null)
    {
        $relationship = $relationship ?: $this->guessRelationship($related);
        
        $this->relationships[$relationship] = [
            'model' => $related,
            'count' => $count,
            'factory' => null
        ];

        return $this;
    }

    /**
     * Define a relationship with a specific factory
     */
    public function hasFactory($related, Factory $factory, $relationship = null)
    {
        $relationship = $relationship ?: $this->guessRelationship($related);
        
        $this->relationships[$relationship] = [
            'model' => $related,
            'count' => 1,
            'factory' => $factory
        ];

        return $this;
    }

    /**
     * Create the model with relationships
     */
    public function create(array $attributes = [])
    {
        $model = $this->factory->create($attributes);

        foreach ($this->relationships as $relationship => $config) {
            $relatedFactory = $config['factory'] ?: Factory::of($config['model']);
            
            if ($config['count'] > 1) {
                $relatedFactory = $relatedFactory->times($config['count']);
            }

            $related = $relatedFactory->create();
            
            // Attach the relationship (this would depend on your ORM implementation)
            $this->attachRelationship($model, $relationship, $related);
        }

        return $model;
    }

    /**
     * Guess the relationship name from the model class
     */
    protected function guessRelationship($model)
    {
        $className = class_basename($model);
        return strtolower($className);
    }

    /**
     * Attach a relationship to the model
     */
    protected function attachRelationship($model, $relationship, $related)
    {
        // This would depend on your ORM implementation
        // For now, we'll just set it as a property
        $model->$relationship = $related;
    }
}

/**
 * Sequence Class
 * 
 * Provides sequential values for factory attributes
 */
class Sequence
{
    protected $sequence;
    protected $index = 0;

    public function __construct(...$sequence)
    {
        $this->sequence = $sequence;
    }

    /**
     * Get the next value in the sequence
     */
    public function __invoke()
    {
        $value = $this->sequence[$this->index % count($this->sequence)];
        $this->index++;
        
        return is_callable($value) ? $value() : $value;
    }
}

/**
 * Helper functions for factories
 */

/**
 * Create a factory for a model
 */
function factory($model, $count = null)
{
    $factory = Factory::of($model);
    
    if ($count !== null) {
        $factory = $factory->times($count);
    }
    
    return $factory;
}

/**
 * Create a sequence for factory attributes
 */
function sequence(...$sequence)
{
    return new Sequence(...$sequence);
}

/**
 * Get a random element from an array
 */
function randomElement(array $array)
{
    return $array[array_rand($array)];
}

/**
 * Get multiple random elements from an array
 */
function randomElements(array $array, $count = 1)
{
    $keys = array_rand($array, min($count, count($array)));
    
    if (!is_array($keys)) {
        $keys = [$keys];
    }
    
    return array_intersect_key($array, array_flip($keys));
}
