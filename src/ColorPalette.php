<?php

namespace Northrook\Stylesheets;

use Northrook\Support\Debug;

class ColorPalette {

	private array $palette = [
		'baseline' => null,
		'primary'  => null,
	];

	private array $theme = [
		'light' => [
			'baseline' => [
				50  => 1,
				100 => 4,
				200 => 9,
				300 => 15,
				400 => 35,
				500 => 85,
				600 => 90,
				700 => 93,
				800 => 96,
				900 => 99,
			],
			'primary'  => [
				'primary' => null,
				'tint'    => ['l' => '+8'],
				'dull'    => ['s' => '-8', 'l' => '+14'],
				'soft'    => ['s' => '-24', 'l' => '+26'],
				'full'    => ['l' => 99],
			],
		],
		'dark'  => [
			'baseline' => [
				50  => 99,
				100 => 96,
				200 => 93,
				300 => 90,
				400 => 85,
				500 => 35,
				600 => 15,
				700 => 9,
				800 => 4,
				900 => 1,
			],
			'primary'  => [
				'primary' => null,
				'tint'    => ['l' => '+8'],
				'dull'    => ['s' => '-8', 'l' => '+14'],
				'soft'    => ['s' => '-24', 'l' => '+26'],
				'full'    => ['l' => 99],
			],
		],
	];

	public ?array $variables = null;

	public function getVariables(): array {
		return $this->variables ?? $this->generateVariables();
	}

	public function setColor( string $palette, string $hsl ) {
		$this->palette[$palette] = new Color( $hsl );
	}

	private function generateVariables(): array {

		$this->variables = [];

		foreach ( $this->theme as $theme => $palette ) {
			foreach ( $palette as $name => $variable ) {

				if ( ! isset( $this->palette[$name] ) ) {
					Debug::log( "$name Color not set", $this->palette );
					continue;
				}

				$color = $this->palette[$name];

				if ( ! $color ) {
					continue;
				}

				foreach ( $variable as $key => $value ) {
					$key    = $this->variableKey( $name, $key );
					$modify = is_int( $value ) ? ['l' => $value] : $value;
					// var_dump( $key, $modify );
					// if ( is_int( $value ) ){
					//     $color->hsl['l'] = $value;
					// }
					// if ( is_array( $value ) ){
					//     $color->modifyHSL( $value );
					// }
					// $hsl->modifyHSL( )
					$this->variables[$theme][$key] = $color->string( $modify );
				}

			}
		}
		// foreach ( $this->palette as $palette => $color ) {

		//     if ( ! $color ) {
		//         continue;
		//     }
		// }

		return $this->variables;
	}

	private function variableKey( string $color, string $key ): string {
		return '--' . strtolower( implode( '-', array_unique( [trim( $color ), trim( $key )] ) ) );
	}

}