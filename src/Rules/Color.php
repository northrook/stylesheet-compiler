<?php

namespace Northrook\Stylesheets\Rules;

class Color extends AbstractRule {

	protected const TRIGGER = 'color';

	protected function rules( ?string $class = null ) : array {
		$this->rule( $class, [ 'color' => $this->color()] );

        return [];
	}
}