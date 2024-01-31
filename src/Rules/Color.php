<?php

namespace Northrook\Stylesheets\Rules;

class Color extends AbstractRule {

	protected const TRIGGER = 'color';

	protected function construct() {
		$this->rules( ".{$this->class}", ['color' => $this->color()] );
	}
}