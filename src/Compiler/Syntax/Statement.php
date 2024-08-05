<?php

declare( strict_types = 1 );

namespace Northrook\CSS\Compiler\Syntax;

/**
 * ```
 * @ identifier rule;
 * ```
 * @internal
 * @author Martin Nielsen <mn@northrook.com>
 */
class Statement
{
    public readonly string $identifier;
    public readonly string $rule;

    public function __construct(
        string $identifier,
        string $rule,
    ) {
        if ( !\str_starts_with( $identifier, '@' ) ) {
            throw new \InvalidArgumentException( 'CSS Identifier must start with "@".' );
        }

        $this->identifier = \strtolower( \trim( $identifier ) );
        $this->rule       = \trim( $rule, " \n\r\t\v\0\"';" );
    }

}