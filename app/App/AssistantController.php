<?php

namespace BookStack\App;

use BookStack\Http\Controller;

class AssistantController extends Controller
{
    /**
     * Display the AI Assistant chat interface.
     */
    public function index()
    {
        $this->setPageTitle(trans('common.ai_assistant'));

        return view('assistant.index');
    }
}

