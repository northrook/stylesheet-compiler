<?php

namespace Northrook\Stylesheets\Rules;

class Background extends AbstractRule
{

    protected const TRIGGER = 'bg';

    protected function rules( ?string $class = null ) : array {
        $this->rule( $class, [ 'background' => $this->color() ] );

        return [];
    }
}