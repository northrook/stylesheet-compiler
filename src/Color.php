<?php

namespace Northrook\Stylesheets;

use Northrook\Stylesheets\Colors\HSL;

class Color {

	public ?HSL $hsl = null;

	public function __construct( private mixed $color, private ?string $format = null ) {
		$this->parseColor();
	}

	public function getHSL(): mixed {
		return $this->hsl;
	}

	public function modifyHSL( mixed $array ): void {
		foreach ( $array as $key => $value ) {
			if ( is_int( $value ) ) {
				$this->hsl[$key] = (int) $value;
			} else {
				$this->hsl[$key] += (int) $value;
			}
		}
	}

	private function parseColor(): void {

		$this->color = trim( $this->color );

		if ( str_starts_with( $this->color, '#' ) ) {
			$this->format = 'hex';
			$this->color  = substr( $this->color, 1 );
		}

		if ( str_starts_with( $this->color, 'hsl' ) ) {
			$this->format = 'hsl';
			$this->hsl = new HSL( $this->color );
		}

	}
}