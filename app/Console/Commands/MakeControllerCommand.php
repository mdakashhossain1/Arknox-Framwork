<?php

namespace App\Console\Commands;

/**
 * Make Controller Command
 * 
 * Creates a new controller class
 */
class MakeControllerCommand extends BaseCommand
{
    public function execute($arguments)
    {
        if (empty($arguments)) {
            $this->error("Controller name is required.");
            $this->info("Usage: php console make:controller <ControllerName>");
            return;
        }
        
        $controllerName = $arguments[0];
        
        // Ensure controller name ends with 'Controller'
        if (!str_ends_with($controllerName, 'Controller')) {
            $controllerName .= 'Controller';
        }
        
        $controllerPath = "app/Controllers/{$controllerName}.php";
        
        // Check if controller already exists
        if (file_exists($controllerPath)) {
            $this->error("Controller {$controllerName} already exists!");
            return;
        }
        
        // Create controller content
        $content = $this->getControllerTemplate($controllerName);
        
        // Write controller file
        if (file_put_contents($controllerPath, $content)) {
            $this->success("Controller {$controllerName} created successfully!");
            $this->info("Location: {$controllerPath}");
        } else {
            $this->error("Failed to create controller {$controllerName}");
        }
    }
    
    private function getControllerTemplate($controllerName)
    {
        $className = $controllerName;
        $viewName = strtolower(str_replace('Controller', '', $controllerName));
        
        return "<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * {$className}
 * 
 * Generated controller class
 */
class {$className} extends Controller
{
    /**
     * Display the main page
     */
    public function index()
    {
        \$data = [
            'title' => '{$viewName} Page'
        ];
        
        return \$this->render('{$viewName}/index', \$data);
    }
    
    /**
     * Show the form for creating a new resource
     */
    public function create()
    {
        \$data = [
            'title' => 'Create {$viewName}'
        ];
        
        return \$this->render('{$viewName}/create', \$data);
    }

    /**
     * Store a newly created resource
     */
    public function store()
    {
        // Validate input
        \$data = \$this->all();

        // Process the data
        // ...

        // Redirect or return response
        return \$this->redirect('/{$viewName}');
    }

    /**
     * Display the specified resource
     */
    public function show(\$id)
    {
        \$data = [
            'title' => 'View {$viewName}',
            'id' => \$id
        ];

        return \$this->render('{$viewName}/show', \$data);
    }

    /**
     * Show the form for editing the specified resource
     */
    public function edit(\$id)
    {
        \$data = [
            'title' => 'Edit {$viewName}',
            'id' => \$id
        ];

        return \$this->render('{$viewName}/edit', \$data);
    }
    
    /**
     * Update the specified resource
     */
    public function update(\$id)
    {
        // Validate input
        \$data = \$this->all();
        
        // Process the data
        // ...
        
        // Redirect or return response
        return \$this->redirect('/{$viewName}');
    }
    
    /**
     * Remove the specified resource
     */
    public function destroy(\$id)
    {
        // Delete the resource
        // ...
        
        return \$this->json(['success' => true]);
    }
}";
    }
}
