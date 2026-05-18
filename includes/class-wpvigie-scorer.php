<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_Scorer {

	public static function score( array $results ): int {
		$total = 0;

		foreach ( $results as $result ) {
			if ( 'pass' === $result['severity'] ) {
				$total += 10;
			} elseif ( 'warn' === $result['severity'] ) {
				$total += 5;
			}
			// 'fail' contributes 0 points.
		}

		return $total;
	}
}
