<?php

namespace Northrook\Stylesheets\Rules;


/** Flow Rules
 * 
 * ## .flow
 * * Adds gap between horizontal child elements
 * * Uses the `--flow-gap` variable by default
 * * Fallbacks to `1em`
 * * Respects the `.reverse` class
 * 
 */
class Flow extends AbstractRule {

	protected const TRIGGER = 'flow';

	protected function construct() {

		if ( $this->value ) {
			$this->rules( ".{$this->class}", ['--flow-gap' => $this->value] );
		}

		if ( $this->has( 'reverse' ) ) {
			$rules = ['margin-bottom' => 'var(--flow-gap, 1em)'];
		} else {
			$rules = ['margin-top' => 'var(--flow-gap, 1em)'];
		}
		
		$this->rules( ".{$this->class} > * + *", $rules );
	}
}