<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Padding extends AbstractRule {

	protected const TRIGGER = 'p';

	private ?string $padding = null;

	protected function construct() {

		$type = Str::before( $this->class, ':' ) ;
		
		match ($type) {
			'p-x' => $this->rules( ".{$this->class}", [
				'padding-left'  => $this->value ?? 'var(--margin)',
				'padding-right' => $this->value ?? 'var(--margin)']
			),
			'p-y' => $this->rules( ".{$this->class}", [
				'padding-top'    => $this->value ?? 'var(--margin)',
				'padding-bottom' => $this->value ?? 'var(--margin)']
			),
			'p-t' => $this->rules( ".{$this->class}",['padding-top' => $this->value ?? 'var(--margin)'] ),
			'p-r' => $this->rules( ".{$this->class}",['padding-right' => $this->value ?? 'var(--margin)'] ),
			'p-b' => $this->rules( ".{$this->class}",['padding-bottom' => $this->value ?? 'var(--margin)'] ),
			'p-l' => $this->rules( ".{$this->class}",['padding-left' => $this->value ?? 'var(--margin)'] ),
			default => $this->rules( ".{$this->class}", ['padding' => $this->value ?? 'var(--margin)'] ),
		};

	}

}