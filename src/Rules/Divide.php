<?php

namespace Northrook\Stylesheets\Rules;


/** Divide Rules
 * 
 * ## .divide
 * * Adds gap between -h or -v child elements
 * * Size is the divider height or width, default 100%
 * * * Number defaults to percentage
 * * * Accepts variables
 * 
 * Example:
 * .divide-h:80
 * Horizontal divider of 80%
 */
class Divide extends AbstractRule {

	protected const TRIGGER = 'divide';

	protected function construct() {

		// if ( $this->value ) {
		// 	$this->rules( ".{$this->class}", ['--flow-gap' => $this->value] );
		// }

		// if ( $this->has( 'reverse' ) ) {
		// 	$rules = ['margin-bottom' => 'var(--flow-gap, 1em)'];
		// } else {
		// 	$rules = ['margin-top' => 'var(--flow-gap, 1em)'];
		// }
		
		// $this->rules( ".{$this->class} > * + *", $rules );
	}
}