<?php

namespace Fixture\Blog\View\Components;

use Illuminate\View\Component;

class Badge extends Component
{
    public function render(): string
    {
        return '<span>class badge</span>';
    }
}
