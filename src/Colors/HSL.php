<?php

namespace Northrook\Stylesheets\Colors;

use Northrook\Support\Arr;
use Northrook\Support\Debug;

class HSL {

	private const VALUES = ['hue', 'saturation', 'lightness', 'alpha'];

	public int $hue        = 222;
	public int $saturation = 100;
	public int $lightness  = 50;
	public ?int $alpha     = null;

	public function __construct( string $parse ) {

		$color = str_replace( ['hsl', '(', ')', ',', '/', '%'], ' ', $parse );
		$color = array_filter( explode( ' ', $color ) );

		if ( count( $color ) < 3 ) {
			return Debug::handleError( 'Invalid HSL color' );
		}

		$index = 0;

		foreach ( $color ?? [] as $value ) {
			if ( ! isset( $this::VALUES[$index] ) ) {
				return Debug::handleError( 'Invalid HSL color' );
			}
			$this->{$this::VALUES[$index]} = (int) $value;
			$index++;
		}
	}

	public function get( ?array $modify = null ): string {

		$color = [
			'h' => $this->hue,
			's' => $this->saturation,
			'l' => $this->lightness,
			'a' => $this->alpha,
		];

		foreach ( $color as $key => $value ) {

			if ( isset( $modify[$key] ) ) {
				if ( is_int( $modify[$key] ) ) {
					$value = (int) $modify[$key];
				} else {
					$value += (int) $modify[$key];
				}
				// dump( $color,$value, $modify );
				// if ( ! $value ) {
				// 	$value[$key] = $modify[$key];
				// } else {
				// }
				// $value += $modify[$key];
			}


			if ( ! $value ) {
				continue;
			}

			if ( $key === 'h' ) {
				$color[$key] = $this->range( $value, 360, 0 );
			}

			if ( $key === 's' || $key === 'l' ) {
				$color[$key] = $this->range( $value, 100, 0 ) . '%';
			}

			if ( $key === 'a' ) {
				$color[$key] = $value / 100;
			}
		}

		// dd( $modify, $color );

		// if ( $modify ) {
		// 	foreach ( $modify as $key => $value ) {
		// 		if ( is_int( $value ) ) {
		// 			$color[$key] = (int) $value;
		// 		} else {
		// 			$color[$key] += (int) $value;
		// 		}
		// 	}
		// }

		// $color['h'] = $this->range( $color['h'] ?? 0, 360, 0 );
		// $color['s'] = $this->range( $color['s'] ?? 0, 100, 0 );
		// $color['l'] = $this->range( $color['l'] ?? 0, 100, 0 );

		// $string = [
		// 	$color['h'],
		// 	$color['s'] . '%',
		// 	$color['l'] . '%',
		// ];

		// if ( isset( $color['a'] ) ) {
		// 	$color['a'] = $this->range( $color['a'] ?? 0, 100, 0 );
		// 	$string[]   = $color['a'] / 100;
		// }

		// \dump( $string );

		return Arr::implode( $color, ', ' );
	}

	private function range( int $value, float $ceil, float $floor ): int {
		return match ( true ) {
			$value >= $ceil => $ceil,
			$value < $floor => $floor,
			default => $value
		};
	}
}