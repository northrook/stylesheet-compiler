<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Width extends AbstractRule
{
    protected const TRIGGER = 'w';

    protected function rules( ?string $class = null ) : array {
        return [
            'w'     => [ 'width' => $this->value ?? 'var(--width)' ],
            'w-min' => [ 'min-width' => $this->value ?? 'var(--width)' ],
            'w-max' => [ 'max-width' => $this->value ?? 'var(--width)' ],
        ];
    }

}