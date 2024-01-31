<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Margin extends AbstractRule {

	protected const TRIGGER = 'm';

	private ?string $margin = null;

	protected function construct() {

		$this->margin = match ( Str::before( $this->class, ':' ) ) {
			'm-x' => 'horizontal',
			'm-y' => 'vertical',
			default => 'margin',
		};

		if ( $this->margin === 'horizontal' ) {
			$this->rules( ".{$this->class}", [
				'margin-left'  => $this->value ?? 'var(--margin)',
				'margin-right' => $this->value ?? 'var(--margin)']
			);
		} else if ( $this->margin === 'vertical' ) {
			$this->rules( ".{$this->class}", [
				'margin-top'    => $this->value ?? 'var(--margin)',
				'margin-bottom' => $this->value ?? 'var(--margin)']
			);
		} else {
			$this->rules( ".{$this->class}", [$this->margin => $this->value ?? 'var(--margin)'] );
		}
	}
}