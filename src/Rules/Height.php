<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Height extends AbstractRule {

	protected const TRIGGER = 'h';

	private ?string $height  = null;

	protected function construct() {

		$this->height = match ( Str::before( $this->class, ':' ) ) {
			'h-min' => 'min-height',
			'h-max' => 'max-height',
			default => 'height',
		};

		$this->rules( ".{$this->class}", [$this->height => $this->value] );
	}

}