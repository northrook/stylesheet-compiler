<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Margin extends AbstractRule {

	protected const TRIGGER = 'm';

	protected function construct() {

		$type = Str::before( $this->class, ':' ) ;
		
		match ($type) {
			'm-x' => $this->rules( ".{$this->class}", [
				'margin-left'  => $this->value ?? 'var(--margin)',
				'margin-right' => $this->value ?? 'var(--margin)']
			),
			'm-y' => $this->rules( ".{$this->class}", [
				'margin-top'    => $this->value ?? 'var(--margin)',
				'margin-bottom' => $this->value ?? 'var(--margin)']
			),
			'm-t' => $this->rules( ".{$this->class}",['margin-top' => $this->value ?? 'var(--margin)'] ),
			'm-r' => $this->rules( ".{$this->class}",['margin-right' => $this->value ?? 'var(--margin)'] ),
			'm-b' => $this->rules( ".{$this->class}",['margin-bottom' => $this->value ?? 'var(--margin)'] ),
			'm-l' => $this->rules( ".{$this->class}",['margin-left' => $this->value ?? 'var(--margin)'] ),
			default => $this->rules( ".{$this->class}", ['margin' => $this->value ?? 'var(--margin)'] ),
		};

	}
}