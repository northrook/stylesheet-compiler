<?php

namespace Northrook\Stylesheets\Rules;

class Flex extends AbstractRule {

	public const RULES = [
		'flex', 'col', 'row', 'reverse', 'wrap', 'nowrap', 'grow', 'shrink',
	];

	protected function __construct( array $parse ) {
		$parse = \array_intersect( $parse, self::RULES );

		if ( \in_array( 'flex', $parse ) ) {
			$this->rules['.flex'] = ['display' => 'flex'];
			
		}

		if ( \in_array( 'col', $parse ) ) {
			if ( \in_array( 'reverse', $parse ) ) {
				$this->rules['.flex.col.reverse'] = ['flex-direction' => 'column-reverse'];
			} else {
				$this->rules['.flex.col'] = ['flex-direction' => 'column'];
			}
		}

		if ( in_array( 'row', $parse ) ) {
			if ( in_array( 'reverse', $parse ) ) {
				$this->rules['.flex.row.reverse'] = ['flex-direction' => 'row-reverse'];
			} else {
				$this->rules['.flex.row'] = ['flex-direction' => 'row'];
			}
		}

		if ( \in_array( 'nowrap', $parse ) ) {
			$this->rules['.flex.nowrap'] = ['flex-wrap' => 'nowrap'];
		}

		if ( \in_array( 'wrap', $parse ) ) {
			$this->rules['.flex.wrap'] = ['flex-wrap' => 'wrap'];
		}
	}

}