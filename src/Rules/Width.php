<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Width extends AbstractRule {

	protected const TRIGGER = 'w';

	private ?string $height  = null;

	protected function construct() {

		$this->height = match ( Str::before( $this->class, ':' ) ) {
			'w-min' => 'min-width',
			'w-max' => 'max-width',
			default => 'width',
		};

		$this->rules( ".{$this->class}", [$this->height => $this->value] );
	}

}