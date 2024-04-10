<?php

namespace Northrook\Stylesheets\Rules;


/** Box Shadow Rules
 *
 * @link https://tailwindcss.com/docs/box-shadow
 */
class Shadow extends AbstractRule
{

    protected const TRIGGER = 'shadow';

    protected function rules( ?string $class = null ) : array {
        return [];
    }

    // protected function construct() {
    //
    // 	if ( $this->value ) {
    // 		$this->rule( ".{$this->class}", [ '--flow-gap' => $this->value] );
    // 	}
    // 	if ( $this->has( 'reverse' ) ) {
    // 		$rules = ['margin-bottom' => 'var(--flow-gap, 1em)'];
    // 	} else {
    // 		$rules = ['margin-top' => 'var(--flow-gap, 1em)'];
    // 	}
    //
    // 	$this->rule( ".{$this->class} > * + *", $rules );
    //
    // }
}