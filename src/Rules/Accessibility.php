<?php

namespace Northrook\Stylesheets\Rules;

/** Font Rules
 *
 * * .font-size
 * * .font-color
 */
class Accessibility extends AbstractRule {

	protected const TRIGGER = 'sr';

	protected function rules( ?string $class = null ) : array {

		if ( $this->has( 'sr-only' ) ) {
			$this->rule(
				".$class",
				[
					'position'     => 'absolute',
					'width'        => '1px',
					'height'       => '1px',
					'padding'      => '0',
					'margin'       => '-1px',
					'overflow'     => 'hidden',
					'clip'         => 'rect(0, 0, 0, 0)',
					'white-space'  => 'nowrap',
					'border-width' => '0',
				]
			);
		}

        return [];
	}
}