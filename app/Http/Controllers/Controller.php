<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function renderPjaxView(Request $request, View $view)
    {
        if ($request->header('X-PJAX')) {
            $sections = $view->renderSections();

            return $sections['content'] ?? $view->render();
        }

        return $view;
    }
}
