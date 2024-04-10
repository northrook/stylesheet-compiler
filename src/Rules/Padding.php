<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Padding extends AbstractRule
{
    protected const TRIGGER = 'p';

    protected function rules( ?string $class = null ) : array {
        return [
            'p'   => [ 'padding' => $this->value ?? 'var(--padding)' ],
            'p-x' => [
                'padding-left'  => $this->value ?? 'var(--padding)',
                'padding-right' => $this->value ?? 'var(--padding)',
            ],
            'p-y' => [
                'padding-top'    => $this->value ?? 'var(--padding)',
                'padding-bottom' => $this->value ?? 'var(--padding)',
            ],
            'p-t' => [ 'padding-top' => $this->value ?? 'var(--padding)' ],
            'p-r' => [ 'padding-right' => $this->value ?? 'var(--padding)' ],
            'p-b' => [ 'padding-bottom' => $this->value ?? 'var(--padding)' ],
            'p-l' => [ 'padding-left' => $this->value ?? 'var(--padding)' ],
        ];
    }
}