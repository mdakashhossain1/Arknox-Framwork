<?php

namespace App\Core\Admin;

/**
 * Admin Panel Builder
 * 
 * Dynamic admin panel generation
 */
class AdminPanelBuilder
{
    protected $models = [];
    protected $menus = [];
    protected $widgets = [];
    protected $customPages = [];

    /**
     * Register a model for admin management
     */
    public function registerModel($model, $config = [])
    {
        $this->models[$model] = array_merge([
            'name' => $this->getModelName($model),
            'icon' => 'fas fa-table',
            'fields' => $this->getModelFields($model),
            'actions' => ['create', 'read', 'update', 'delete'],
            'permissions' => [],
            'filters' => [],
            'searchable' => true,
            'sortable' => true,
            'paginated' => true,
            'per_page' => 25
        ], $config);

        return $this;
    }

    /**
     * Add menu item
     */
    public function addMenu($title, $url, $icon = null, $parent = null)
    {
        $menu = [
            'title' => $title,
            'url' => $url,
            'icon' => $icon ?: 'fas fa-circle',
            'parent' => $parent,
            'children' => []
        ];

        if ($parent) {
            $this->menus[$parent]['children'][] = $menu;
        } else {
            $this->menus[$title] = $menu;
        }

        return $this;
    }

    /**
     * Add dashboard widget
     */
    public function addWidget($name, $config)
    {
        $this->widgets[$name] = array_merge([
            'title' => $name,
            'type' => 'card',
            'size' => 'col-md-6',
            'data' => null,
            'template' => null
        ], $config);

        return $this;
    }

    /**
     * Add custom page
     */
    public function addPage($route, $title, $callback)
    {
        $this->customPages[$route] = [
            'title' => $title,
            'callback' => $callback
        ];

        return $this;
    }

    /**
     * Generate admin panel
     */
    public function build()
    {
        return [
            'models' => $this->models,
            'menus' => $this->buildMenuStructure(),
            'widgets' => $this->widgets,
            'pages' => $this->customPages
        ];
    }

    /**
     * Generate model CRUD interface
     */
    public function generateModelInterface($model)
    {
        $config = $this->models[$model] ?? [];
        
        if (empty($config)) {
            throw new \Exception("Model {$model} not registered");
        }

        return [
            'list' => $this->generateListView($model, $config),
            'create' => $this->generateCreateForm($model, $config),
            'edit' => $this->generateEditForm($model, $config),
            'view' => $this->generateDetailView($model, $config)
        ];
    }

    /**
     * Generate list view
     */
    protected function generateListView($model, $config)
    {
        $fields = $config['fields'];
        $columns = [];

        foreach ($fields as $field => $fieldConfig) {
            if ($fieldConfig['list'] ?? true) {
                $columns[] = [
                    'field' => $field,
                    'title' => $fieldConfig['label'] ?? ucfirst($field),
                    'sortable' => $fieldConfig['sortable'] ?? true,
                    'searchable' => $fieldConfig['searchable'] ?? true,
                    'type' => $fieldConfig['type'] ?? 'text'
                ];
            }
        }

        return [
            'title' => $config['name'],
            'columns' => $columns,
            'actions' => $config['actions'],
            'filters' => $config['filters'],
            'searchable' => $config['searchable'],
            'sortable' => $config['sortable'],
            'paginated' => $config['paginated'],
            'per_page' => $config['per_page']
        ];
    }

    /**
     * Generate create form
     */
    protected function generateCreateForm($model, $config)
    {
        return $this->generateForm($model, $config, 'create');
    }

    /**
     * Generate edit form
     */
    protected function generateEditForm($model, $config)
    {
        return $this->generateForm($model, $config, 'edit');
    }

    /**
     * Generate form
     */
    protected function generateForm($model, $config, $type)
    {
        $fields = $config['fields'];
        $formFields = [];

        foreach ($fields as $field => $fieldConfig) {
            if ($fieldConfig[$type] ?? true) {
                $formFields[] = [
                    'name' => $field,
                    'label' => $fieldConfig['label'] ?? ucfirst($field),
                    'type' => $fieldConfig['type'] ?? 'text',
                    'required' => $fieldConfig['required'] ?? false,
                    'validation' => $fieldConfig['validation'] ?? [],
                    'options' => $fieldConfig['options'] ?? [],
                    'help' => $fieldConfig['help'] ?? null
                ];
            }
        }

        return [
            'title' => $type === 'create' ? "Create {$config['name']}" : "Edit {$config['name']}",
            'fields' => $formFields,
            'actions' => ['save', 'cancel']
        ];
    }

    /**
     * Generate detail view
     */
    protected function generateDetailView($model, $config)
    {
        $fields = $config['fields'];
        $viewFields = [];

        foreach ($fields as $field => $fieldConfig) {
            if ($fieldConfig['view'] ?? true) {
                $viewFields[] = [
                    'field' => $field,
                    'label' => $fieldConfig['label'] ?? ucfirst($field),
                    'type' => $fieldConfig['type'] ?? 'text'
                ];
            }
        }

        return [
            'title' => "View {$config['name']}",
            'fields' => $viewFields,
            'actions' => ['edit', 'delete', 'back']
        ];
    }

    /**
     * Build menu structure
     */
    protected function buildMenuStructure()
    {
        $menu = [];

        // Add model menus
        foreach ($this->models as $model => $config) {
            $menu[] = [
                'title' => $config['name'],
                'url' => "/admin/{$model}",
                'icon' => $config['icon'],
                'children' => []
            ];
        }

        // Add custom menus
        foreach ($this->menus as $menuItem) {
            $menu[] = $menuItem;
        }

        return $menu;
    }

    /**
     * Get model name
     */
    protected function getModelName($model)
    {
        $parts = explode('\\', $model);
        $className = end($parts);
        return ucfirst(str_replace('_', ' ', snake_case($className)));
    }

    /**
     * Get model fields
     */
    protected function getModelFields($model)
    {
        // This would typically introspect the model
        // For now, return basic fields
        return [
            'id' => [
                'type' => 'number',
                'label' => 'ID',
                'create' => false,
                'edit' => false,
                'list' => true,
                'view' => true
            ],
            'name' => [
                'type' => 'text',
                'label' => 'Name',
                'required' => true,
                'validation' => ['required', 'max:255']
            ],
            'created_at' => [
                'type' => 'datetime',
                'label' => 'Created At',
                'create' => false,
                'edit' => false,
                'list' => true,
                'view' => true
            ],
            'updated_at' => [
                'type' => 'datetime',
                'label' => 'Updated At',
                'create' => false,
                'edit' => false,
                'list' => true,
                'view' => true
            ]
        ];
    }

    /**
     * Generate dashboard
     */
    public function generateDashboard()
    {
        $widgets = [];

        // Add default widgets
        $widgets['stats'] = [
            'title' => 'Statistics',
            'type' => 'stats',
            'data' => $this->getStatistics()
        ];

        $widgets['recent_activity'] = [
            'title' => 'Recent Activity',
            'type' => 'activity',
            'data' => $this->getRecentActivity()
        ];

        // Add custom widgets
        foreach ($this->widgets as $name => $widget) {
            $widgets[$name] = $widget;
        }

        return [
            'title' => 'Dashboard',
            'widgets' => $widgets
        ];
    }

    /**
     * Get statistics
     */
    protected function getStatistics()
    {
        $stats = [];

        foreach ($this->models as $model => $config) {
            try {
                $count = $model::count();
                $stats[] = [
                    'title' => $config['name'],
                    'value' => $count,
                    'icon' => $config['icon'],
                    'url' => "/admin/{$model}"
                ];
            } catch (\Exception $e) {
                // Skip if model doesn't exist or has issues
            }
        }

        return $stats;
    }

    /**
     * Get recent activity
     */
    protected function getRecentActivity()
    {
        // This would typically get recent activities from a log
        return [
            ['action' => 'User created', 'time' => '2 minutes ago'],
            ['action' => 'Product updated', 'time' => '5 minutes ago'],
            ['action' => 'Order processed', 'time' => '10 minutes ago']
        ];
    }
}

// Helper function for snake_case conversion
if (!function_exists('snake_case')) {
    function snake_case($value) {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
