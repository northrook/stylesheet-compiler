<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Radius extends AbstractRule
{

    protected const TRIGGER = 'r';

    protected function rules( ?string $class = null ) : array {
        return [
            'r'   => [ 'border-radius' => $this->value ?? 'var(--radius)', ],
            'r-l' => [
                'border-top-left-radius'    => $this->value ?? 'var(--radius)',
                'border-bottom-left-radius' => $this->value ?? 'var(--radius)',
            ],
            'r-r' => [
                'border-top-right-radius'    => $this->value ?? 'var(--radius)',
                'border-bottom-right-radius' => $this->value ?? 'var(--radius)',
            ],
            'r-t' => [
                'border-top-left-radius'  => $this->value ?? 'var(--radius)',
                'border-top-right-radius' => $this->value ?? 'var(--radius)',
            ],
            'r-b' => [
                'border-bottom-right-radius' => $this->value ?? 'var(--radius)',
                'border-bottom-left-radius'  => $this->value ?? 'var(--radius)',
            ],
        ];
    }

}