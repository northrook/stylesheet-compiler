<?php

namespace Northrook\Stylesheets\Rules;


/** Box Shadow Rules
 * 
 * @link https://tailwindcss.com/docs/box-shadow
 */
class Shadow extends AbstractRule {

	protected const TRIGGER = 'shadow';

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