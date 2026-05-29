<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\LangfuseService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LangfuseController extends Controller
{
    public function index(Request $request, LangfuseService $langfuse)
    {
        $view = view('admin.langfuse-link.index', [
            'langfuseEnabled' => $langfuse->enabled(),
            'langfuseBaseUrl' => $langfuse->baseUrl(),
        ]);

        return $this->renderPjaxView($request, $view);
    }
}
