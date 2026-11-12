<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeBaseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('knowledge-bases/index');
    }

    public function create(): Response
    {
        return Inertia::render('knowledge-bases/create');
    }

    public function edit(string $id): Response
    {
        return Inertia::render('knowledge-bases/edit', [
            'id' => (int) $id,
        ]);
    }
}
