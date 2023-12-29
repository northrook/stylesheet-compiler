<?php

namespace Northrook\Stylesheets;

class Color {

	public ?array $hsl = null;

	// public float $hue        = 0;
	// public float $saturation = 0;
	// public float $lightness  = 0;
	// public float $alpha      = 1;

	public function __construct( private mixed $color, private ?string $format = null ) {
		$this->parseColor();
	}

	public function getHSL(): mixed {
		return $this->hsl;
	}

	public function modifyHSL( mixed $array ): void {
		// var_dump( $array );
		foreach ( $array as $key => $value ) {
			if ( is_int( $value ) ) {
				$this->hsl[$key] = (int) $value;
			} else {
				$this->hsl[$key] += (int) $value;
			}
		}
	}

	public function string( ?array $modify = null ): string {

		$color = $this->hsl;

		if ( $modify ) {
			foreach ( $modify as $key => $value ) {
				if ( is_int( $value ) ) {
					$color[$key] = (int) $value;
				} else {
					$color[$key] += (int) $value;
				}
			}
		}

		$color['h'] = $this->range( $color['h'] ?? 0, 360, 0 );
		$color['s'] = $this->range( $color['s'] ?? 0, 100, 0 );
		$color['l'] = $this->range( $color['l'] ?? 0, 100, 0 );

		$string = [
			$color['h'],
			$color['s'] . '%',
			$color['l'] . '%',
		];

		if ( isset( $color['a'] ) ) {
			$color['a'] = $this->range( $color['a'] ?? 0, 100, 0 );
			$string[]   = $color['a'] / 100;
		}

		return implode( ',', $string );
	}

	private function parseColor(): void {

		$this->color = trim( $this->color );

		if ( str_starts_with( $this->color, '#' ) ) {
			$this->format = 'hex';
			$this->color  = substr( $this->color, 1 );
		}

		if ( str_starts_with( $this->color, 'hsl' ) ) {
			$this->parseHSL();
		}

	}

	private function parseHSL(): void {

		$this->format = 'hsl';
		$color        = str_replace( ['hsl', '(', ')', ',', '/', '%'], ' ', $this->color );
		$color        = array_filter( explode( ' ', $color ) );

		$hsl  = [];
		$keys = ['h', 's', 'l'];
		foreach ( $color ?? [] as $value ) {
			if ( $value ) {
				$hsl[] = (float) $value;
			}
		}

		if ( isset( $hsl[3] ) ) {
			$keys[] = 'a';
		}
		$this->hsl = array_combine( $keys, $hsl );
	}

	private function range( int $value, float $ceil, float $floor ): int {
		return match ( true ) {
			$value >= $ceil => $ceil,
			$value < $floor => $floor,
			default => $value
		};
	}
}