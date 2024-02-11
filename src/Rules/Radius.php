<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Radius extends AbstractRule {

	protected const TRIGGER = 'r';

	protected function construct() {

		$type = Str::before( $this->class, ':' ) ;
		
		match ($type) {
			'r-l' => $this->rules( ".{$this->class}", [
				'border-top-left-radius'  => $this->value ?? 'var(--radius)',
				'border-bottom-left-radius' => $this->value ?? 'var(--radius)',
			]),
			'r-r' => $this->rules( ".{$this->class}", [
				'border-top-right-radius' => $this->value ?? 'var(--radius)',
				'border-bottom-right-radius' => $this->value ?? 'var(--radius)',
			]),
			'r-t' => $this->rules( ".{$this->class}",[
				'border-top-left-radius'  => $this->value ?? 'var(--radius)',
				'border-top-right-radius' => $this->value ?? 'var(--radius)',
			]),
			'r-b' => $this->rules( ".{$this->class}",[
				'border-bottom-right-radius' => $this->value ?? 'var(--radius)',
				'border-bottom-left-radius' => $this->value ?? 'var(--radius)',
			]),
			default => $this->rules( ".{$this->class}", [
				'border-radius' => $this->value ?? 'var(--radius)'
			])
		};

	}
}