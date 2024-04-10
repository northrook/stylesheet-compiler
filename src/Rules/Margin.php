<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Margin extends AbstractRule
{
    protected const TRIGGER = 'm';

    protected function rules( ?string $class = null ) : array {
        return [

            'm'   => [ 'margin' => $this->value ?? 'var(--margin)' ],
            'm-x' => [
                'margin-left'  => $this->value ?? 'var(--margin)',
                'margin-right' => $this->value ?? 'var(--margin)',
            ]
            ,
            'm-y' => [
                'margin-top'    => $this->value ?? 'var(--margin)',
                'margin-bottom' => $this->value ?? 'var(--margin)',
            ]
            ,
            'm-t' => [ 'margin-top' => $this->value ?? 'var(--margin)' ],
            'm-r' => [ 'margin-right' => $this->value ?? 'var(--margin)' ],
            'm-b' => [ 'margin-bottom' => $this->value ?? 'var(--margin)' ],
            'm-l' => [ 'margin-left' => $this->value ?? 'var(--margin)' ],
        ];
    }
}