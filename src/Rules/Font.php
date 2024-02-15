<?php

namespace Northrook\Stylesheets\Rules;

/** Font Rules
 *
 * * .font-size 
 * * .font-color
 */
class Font extends AbstractRule {

	protected const TRIGGER = 'font';

	protected function construct() {

		// TODO: Add support for :h1, :h2, :h3, :h4, :h5, :h6
		if ( $this->has( 'font-size' ) ) {
			$this->rules( ".{$this->class}", ['font-size' => $this->value ?? 'var(--font-size)'] );
		}

		if ( $this->has( 'font-color' ) ) {
			$this->rules( ".{$this->class}", ['color' => $this->color()] );
		}
	}
}