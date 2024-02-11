<?php

namespace Northrook\Stylesheets\Rules;

class Flex extends AbstractRule {

	protected const TRIGGER = 'flex';

	protected function construct() {

		if ( $this->has( 'flex' ) ) {
			$this->rules( '.flex', ['display' => 'flex'] );
		}


		$flow = $this->has( 'flow' );
		if ( $flow ) {
			if ( $this->has( 'reverse' ) ) {
				$this->rules[".flex.$flow.reverse"] = ['flex-direction' => 'column-reverse'];
			} else {
				$this->rules[".flex.$flow"]  = ['flex-direction' => 'column'];
			}

		} else if ( $this->has( 'col' ) ) {
			if ( $this->has( 'reverse' ) ) {
				$this->rules['.flex.col.reverse'] = ['flex-direction' => 'column-reverse'];
			} else {
				$this->rules['.flex.col'] = ['flex-direction' => 'column'];
			}

		} else {
			if ( $this->has( 'reverse' ) ) {
				$this->rules['.flex.reverse'] = ['flex-direction' => 'row-reverse'];
			} else {
				// $this->rules( '.flex', ['flex-direction' => 'row'] );
				// $this->rules['.flex'] = ['flex-direction' => 'row'];
			}
		}

		if ( $this->has( 'align-top' ) ) {
			$this->rules['.flex.align-top'] = ['justify-content' => 'flex-start'];
		}
		if ( $this->has( 'align-center' ) ) {
			$this->rules['.flex.align-center'] = ['justify-content' => 'center'];
		}
		if ( $this->has( 'align-baseline' ) ) {
			$this->rules['.flex.align-center'] = ['align-items' => 'baseline'];
		}
		if ( $this->has( 'align-left' ) ) {
			$this->rules['.flex.align-left'] = ['align-items' => 'flex-start'];
		}
		if ( $this->has( 'align-right' ) ) {
			$this->rules['.flex.align-right'] = ['align-items' => 'flex-end'];
		}
		if ( $this->has( 'align-bottom' ) ) {
			$this->rules['.flex.align-bottom'] = ['align-items' => 'flex-end'];
		}

		if ( $this->has( 'justify-between' ) ) {
			$this->rules['.flex.justify-between'] = ['justify-content' => 'space-between'];
		}

		if ( $this->has( 'grow' ) ) {
			$this->rules['.flex.grow'] = ['flex-grow' => '1'];
		}

		if ( $this->has( 'shrink' ) ) {
			$this->rules['.flex.shrink'] = ['flex-shrink' => '1'];
		}

		if ( $this->has( 'nowrap' ) ) {
			$this->rules['.flex.nowrap'] = ['flex-wrap' => 'nowrap'];
		}

		if ( $this->has( 'wrap' ) ) {
			$this->rules['.flex.wrap'] = ['flex-wrap' => 'wrap'];
		}
	}

}