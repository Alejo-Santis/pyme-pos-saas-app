<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\SetupProgressService;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function __construct(
        private readonly SetupProgressService $setup
    ) {}

    public function index(): Response
    {
        return Inertia::render('Setup/Index', [
            'setup' => $this->setup->progress(),
        ]);
    }
}
