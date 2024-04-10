<?php

declare( strict_types = 1 );

namespace Northrook\Stylesheets\Rules;

use Northrook\Stylesheets\DynamicRules;
use Northrook\Support\Arr;
use Northrook\Support\Str;

/**
 * Breaks off any passed variable, and strips hyphens for loose comparison.
 *
 * @param string  $class
 *
 * @return string
 */
function strip( string $class ) : string {

    // Strip any encoded variables
    $class = explode( ':', $class )[ 0 ] ?? $class;

    // Strip any hyphens
    $class = str_replace( '-', '', $class );

    // Return lowercase
    return strtolower( $class );
}

abstract class AbstractRule
{

    protected const TRIGGER = null;

    protected array   $rules = [];
    protected ?string $class = null;
    protected ?string $value = null;

    private function __construct(
        public readonly array $properties,
    ) {
        if ( null === static::TRIGGER ) {
            trigger_error(
                $this::class . '::TRIGGER is not defined',
                E_USER_ERROR,
            );
        }

        // Loop through properties, they are passed as a list of classes
        foreach ( $properties as $class ) {

            // Skip unrelated classes
            if ( !str_starts_with( $class, (string) $this::TRIGGER ) ) {
                continue;
            }

            // Parse any encoded variables - `w-1:small` -> `w-1`, storing `small` as the value
            $this->variable( $class );

            // Loop through rules provided in extending class::rules()
            foreach ( $this->rules( $class ) as $rule => $value ) {
                // Strip and compare classnames, ignoring encoded variables
                if ( strip( $class ) === strip( $rule ) ) {
                    // Append to the rule array
                    $this->rules[ $class ] = $value;
                }
            }
        }
    }

    public static function build( array $classes = [] ) : array {
        return ( new static( $classes ) )->rules;
    }

    abstract protected function rules( ?string $class = null ) : array;


    /**
     * Manually add a rule.
     *
     * @param string  $class
     * @param array   $rules
     *
     * @return void
     */
    protected function rule( string $class, array $rules ) : void {
        $this->rules = array_merge_recursive( $this->rules, [ $class => $rules ] );
    }

    /**
     * Check if a rule exists in {@see self::properties}.
     *
     * @param $class
     *
     * @return bool|string
     */
    final public function has( $class ) : bool | string {
        return Arr::has( $this->properties, $class, 'startsWith' );
    }


    final protected function variable( string $class ) : void {

        $size = Str::after( $class, ':', strict : true );

        if ( !$size ) {
            return;
        }

        foreach ( DynamicRules::SIZE as $key => $value ) {
            if ( str_starts_with( $key, $size ) ) {
                $this->value =
                    ( in_array( $key, [ 'null', 'auto', 'full' ], true ) ) ? $value : "var(--$key, $value)";
                break;
            }
        }

        if ( !$this->value ) {
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
    protected function color() : ?string {

        if ( null === $this->value ) {
            return $this->value;
        }

        if ( false === str_starts_with( $this->value, '#' ) ) {
            if ( Str::isNumeric( $this->value ) ) {
                $this->value = "--baseline-$this->value";
            }

            if ( str_starts_with( $this->value, '--' ) ) {
                $this->value = "hsla(var($this->value))";
            }
        }

        return $this->value;
    }

}