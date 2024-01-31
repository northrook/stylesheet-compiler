<?php

declare ( strict_types = 1 );

namespace Northrook\Stylesheets\Types;

final class Selectors {

	public function __construct( string $selector ) {
		$string = \preg_replace( '/\'.*?\'/s', ' ', $selector );
	}
}
