<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * PostController
 * 
 * Generated controller class
 */
class PostController extends Controller
{
    /**
     * Display the main page
     */
    public function index()
    {
        $data = [
            'title' => 'post Page'
        ];
        
        return $this->view('post/index', $data);
    }
    
    /**
     * Show the form for creating a new resource
     */
    public function create()
    {
        $data = [
            'title' => 'Create post'
        ];
        
        return $this->view('post/create', $data);
    }
    
    /**
     * Store a newly created resource
     */
    public function store()
    {
        // Validate input
        $data = $this->request->all();
        
        // Process the data
        // ...
        
        // Redirect or return response
        return $this->redirect('/post');
    }
    
    /**
     * Display the specified resource
     */
    public function show($id)
    {
        $data = [
            'title' => 'View post',
            'id' => $id
        ];
        
        return $this->view('post/show', $data);
    }
    
    /**
     * Show the form for editing the specified resource
     */
    public function edit($id)
    {
        $data = [
            'title' => 'Edit post',
            'id' => $id
        ];
        
        return $this->view('post/edit', $data);
    }
    
    /**
     * Update the specified resource
     */
    public function update($id)
    {
        // Validate input
        $data = $this->request->all();
        
        // Process the data
        // ...
        
        // Redirect or return response
        return $this->redirect('/post');
    }
    
    /**
     * Remove the specified resource
     */
    public function destroy($id)
    {
        // Delete the resource
        // ...
        
        return $this->json(['success' => true]);
    }
}