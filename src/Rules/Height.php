<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Height extends AbstractRule {

	protected const TRIGGER = 'h';

    protected function rules( ?string $class = null ) : array {
        return [
            'h'     => [ 'height' => $this->value ?? 'var(--height)' ],
            'h-min' => [ 'min-height' => $this->value ?? 'var(--height)' ],
            'h-max' => [ 'max-height' => $this->value ?? 'var(--height)' ],
        ];
    }
}