<?php

namespace Northrook\Stylesheets\Rules;

class Background extends AbstractRule {

	protected const TRIGGER = 'bg';

	protected function construct() {
		$this->rules( ".{$this->class}", ['background' => $this->color()] );
	}
}