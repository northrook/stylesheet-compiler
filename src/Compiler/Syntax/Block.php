<?php

declare( strict_types = 1 );

namespace Northrook\CSS\Compiler\Syntax;

/**
 * ```
 * block {
 *     selector {
 *         property: value;
 *     }
 * }
 * ```
 * @internal
 * @author Martin Nielsen <mn@northrook.com>
 */
class Block
{
    /**
     * @param string          $selector
     * @param Rule[]|Block[]  $declarations
     */
    public function __construct(
        public readonly string $selector,
        public readonly array  $declarations,
    ) {}

}