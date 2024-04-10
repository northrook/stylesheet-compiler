<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Gap extends AbstractRule {

	protected const TRIGGER = 'gap';

    protected function rules( ?string $class = null ) : array {
        return [
            'gap'     => [ 'gap' => $this->value ?? 'var(--gap)' ],
            'gap-x' => [ 'column-gap' => $this->value ?? 'var(--gap)' ],
            'gap-y' => [ 'row-gap' => $this->value ?? 'var(--gap)' ],
        ];
    }
}