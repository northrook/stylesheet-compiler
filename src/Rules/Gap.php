<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Gap extends AbstractRule {

	protected const TRIGGER = 'gap';

	private ?string $gap = null;

	protected function construct() {

		$this->gap = match ( Str::before( $this->class, ':' ) ) {
			'gap-x' => 'column-gap',
			'gap-y' => 'row-gap',
			default => 'gap',
		};

		$this->rules( ".{$this->class}", [$this->gap => $this->value ?? 'var(--base)'] );

	}
}