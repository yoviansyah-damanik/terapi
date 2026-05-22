<?php

namespace App\Http\ViewComposers;

use App\Models\Letter;
use App\Models\Disposition;
use Illuminate\View\View;

class SidebarComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        if (!$user) {
            return;
        }

    }
}
