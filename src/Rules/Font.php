<?php

namespace Northrook\Stylesheets\Rules;

/** Font Rules
 *
 * * .font-size
 * * .font-color
 */
class Font extends AbstractRule
{

    protected const TRIGGER = 'font';

    /**
     * @todo font:INT ( 200, 300, .. ) for weight. If length is 2 or 3, with no unit, it is a weight
     *       font-weight: 200, 300, .. instead?
     *
     * @todo Add support for :h1, :h2, :h3, :h4, :h5, :h6
     *
     * @todo font-size:h1, :h2, :h3, :h4, :h5, :h6 use var(--font-size)
     */
    protected function rules( ?string $class = null ) : array {

        if ( $this->has( 'font-size' ) ) {
            $this->rule( $class, [ 'font-size' => $this->value ?? 'var(--font-size)' ] );
        }

        if ( $this->has( 'font-color' ) ) {
            $this->rule( $class, [ 'color' => $this->color() ] );
        }

        return [];
    }
}