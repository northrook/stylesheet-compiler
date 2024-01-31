<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Stylesheets\DynamicRules;
use Northrook\Support\Str;

abstract class AbstractRule {

	public const RULES = [];

	protected array $parse;
	protected array $rules = [];

	protected ?string $class = null;
	protected ?string $value = null;

	public static function build( array $classes = [] ): array {

		$build = new static( $classes );

		// DynamicRules::updatedParsedRules( $build->parsed ?? [] );

		return $build->rules;
	}

	private function __construct(
		array $parse
	) {

		$this->parse = $parse;
		$this->construct();
	}

	abstract protected function construct();

	protected function rules( string $class, array $rules ): void {

		$class = \str_ireplace( '#', '\#', $class );
		$class       = Str::replace( ':', '\:', $class, 1 );
		$this->rules = array_merge_recursive( $this->rules, [$class => $rules] );
	}

	protected function has( $class ): bool {
		return in_array( $class, $this->parse, true );
	}

	protected function value( string $class ) {

		$size = Str::after( $class, ':', strict: true );

		if ( ! $size ) {
			return;
		}


		foreach ( DynamicRules::SIZE as $key => $value ) {
			if ( str_starts_with( $key, $size ) ) {
				// var_dump( $key );
				$this->value = ( 'null' === $key || 'auto' === $key ) ? $value : "var(--{$key}, {$value})";
				break;
			}
		}

		if ( ! $this->value ) {
			$this->value = $size;
		}

	}

	protected function color() : ?string {
		

		if ( false === str_starts_with( $this->value ?? '', '#' ) ) {
			$this->value = "var(--{$this->value})";
		}
		
		return $this->value;
	}

}