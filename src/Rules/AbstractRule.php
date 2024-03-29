<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Stylesheets\DynamicRules;
use Northrook\Stylesheets\Rules\Types\Value;
use Northrook\Support\Arr;
use Northrook\Support\Str;

abstract class AbstractRule {

	protected const TRIGGER = null;

	public bool $bail = false;

	protected array $parse;
	protected array $rules = [];

	protected ?string $class = null;
	protected ?string $value = null;

	public static function build( array $classes = [] ): array {

		$build = new static( $classes );

		return $build->rules;
	}

	private function __construct(
		array $array
	) {
		$this->parse( $array );

		if ( ! $this->class ) {
			return;
		}

		$this->construct();
		if ( $this->bail ) {
			return;
		}
	}

	abstract protected function construct();

	private function parse( array $array ): void {

		if ( null === static::TRIGGER ) {
			throw new \Exception( 'TRIGGER is not defined' );
		}

		$this->parse = $array;

		foreach ( $this->parse as $value ) {
			if ( str_starts_with( $value, (string) $this::TRIGGER ) ) {
				$this->class = $value;
			}
		}

		if ( ! $this->class ) {
			throw new \Exception( $this::class . ' cannot find ' . static::TRIGGER . ': Class is not defined.' );
		}

		$this->value( $this->class );
	}


	private function escapeRuleString( string $class ): string {

		$class = \explode( ':', $class );

		foreach ( $class as $key => $value ) {

			//: Skip first and empty
			if ( 0 === $key || ! $value ) {
				continue;
			}

			//: Always escape hex colors and decimals
			$value = str_ireplace( ['#', '.'], ['\#', '\.'], $value );

			//: Only escape custom selectors, skip native
			if ( Str::startsWith( $value, ['not', 'has', 'hover', 'focus'] ) ) {
				$value = ':' . $value;
			} else {
				$value = '\:' . $value;
			}

			$class[$key] = $value;

		}

		return implode( '', $class );

	}

	protected function rules( string $class, array $rules ): void {

		$class = $this->escapeRuleString( $class );
		$this->rules = array_merge_recursive( $this->rules, [$class => $rules] );
		if ( static::TRIGGER === 'flow') {
			// \var_dump('classdump:', $class, $rules, $this->rules);
		}
	}

	protected function has( $class ): bool | string {
		$has = Arr::has( $this->parse, $class, 'startsWith' );

		return is_string( $has ) ? $this->escapeRuleString( $has ) : $has;
	}

	protected function value( string $class ) {

		$size = Str::after( $class, ':', strict: true );

		if ( ! $size ) {
			return;
		}

		foreach ( DynamicRules::SIZE as $key => $value ) {
			if ( str_starts_with( $key, $size ) ) {
				$this->value = ( in_array( $key, ['null', 'auto', 'full'], true ) ) ? $value : "var(--{$key}, {$value})";
				break;
			}
		}

		if ( ! $this->value ) {
			$this->value = $size;
		}

	}

	/** Returns the `$value` property if it exists
	 * 
	 * * If `$value` does not start with a `#`, it will be converted to `var(--$value)`
	 * * If `$value` starts with a `#`, it will be returned as-is
	 * 
	 * @return null|string
	 * */
	protected function color(): ?string {

		if ( ! $this->value ) {
			$this->bail = true;

			return null;
		}

		if ( false === str_starts_with( $this->value ?? '', '#' ) ) {
			if ( Str::isNumeric( $this->value ) ) {
				$this->value = "baseline-{$this->value}";
			}

			$this->value = "hsla(var(--{$this->value}))";
		}

		return $this->value;
	}

}