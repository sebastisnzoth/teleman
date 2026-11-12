<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAgentController extends Controller
{
    /**
     * Display a listing of AI agents
     */
    public function index(): Response
    {
        return Inertia::render('ai-agents/index');
    }

    /**
     * Show the form for creating a new AI agent
     */
    public function create(): Response
    {
        return Inertia::render('ai-agents/create');
    }

    /**
     * Display live calls monitoring page
     */
    public function live(): Response
    {
        return Inertia::render('ai-agents/live');
    }

    /**
     * Display AI agent call history
     */
    public function calls(): Response
    {
        return Inertia::render('ai-agents/calls');
    }

    /**
     * Display the specified AI agent
     */
    public function show(string $id): Response
    {
        return Inertia::render('ai-agents/show', [
            'id' => $id,
        ]);
    }

    /**
     * Show the form for editing the specified AI agent
     */
    public function edit(string $id): Response
    {
        return Inertia::render('ai-agents/edit', [
            'id' => $id,
        ]);
    }
}
