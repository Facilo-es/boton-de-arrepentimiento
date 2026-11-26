<?php
/**
 * Shared day and ticket helpers (spec section 6). Free ships them and their tests; Pro's day counter uses them.
 *
 * All dates are plain Y-m-d strings in Argentine time. No clock is read here, so the helpers are pure.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Calendar.
 */
class Calendar {

	/**
	 * Non-working days of a year, or null when the rules have no list for that year.
	 *
	 * @param int $year Year.
	 * @return string[]|null
	 */
	public static function non_working( $year ) {
		$r    = Util::rules();
		$year = (int) $year;
		$list = array();
		foreach ( (array) $r['non_working'] as $d ) {
			if ( 0 === strpos( $d, $year . '-' ) ) {
				$list[] = $d;
			}
		}
		$s = Settings::get();
		if ( ! empty( $s['holidays'] ) ) {
			foreach ( preg_split( '/[\s,;]+/', (string) $s['holidays'] ) as $d ) {
				if ( preg_match( '/^' . $year . '-\d{2}-\d{2}$/', $d ) ) {
					$list[] = $d;
				}
			}
		}
		$list = $list ? array_values( array_unique( $list ) ) : null;
		/**
		 * Non-working days of a year (Y-m-d), or null if the year has no list.
		 *
		 * @param string[]|null $list Days.
		 * @param int           $year Year.
		 */
		return apply_filters( 'bclaro_non_working', $list, $year );
	}

	/**
	 * Monday to Friday and not in the list.
	 *
	 * @param string $ymd Date.
	 * @return bool
	 */
	public static function is_working( $ymd ) {
		$d = self::day( $ymd );
		if ( (int) $d->format( 'N' ) >= 6 ) {
			return false;
		}
		$list = self::non_working( (int) $d->format( 'Y' ) );
		return ! ( $list && in_array( $d->format( 'Y-m-d' ), $list, true ) );
	}

	/**
	 * The date itself if working, else the next working day.
	 *
	 * @param string $ymd Date.
	 * @return string
	 */
	public static function next_working( $ymd ) {
		$d = self::day( $ymd );
		for ( $i = 0; $i < 30; $i++ ) {
			if ( self::is_working( $d->format( 'Y-m-d' ) ) ) {
				break;
			}
			$d = $d->modify( '+1 day' );
		}
		return $d->format( 'Y-m-d' );
	}

	/**
	 * The last day of the term. start = later of contract and delivery; raw = start + term days; moved to a working day.
	 *
	 * @param string $contract_ymd Contract date.
	 * @param string $delivery_ymd Delivery date, or '' for services and virtual products.
	 * @return array raw, last, moved, missing_year (int or null), start.
	 */
	public static function last_day( $contract_ymd, $delivery_ymd = '' ) {
		$start = (string) $contract_ymd;
		if ( '' !== (string) $delivery_ymd && $delivery_ymd > $start ) {
			$start = (string) $delivery_ymd;
		}
		return self::from_start( $start );
	}

	/**
	 * The term from a start day.
	 *
	 * @param string $start Start date.
	 * @return array
	 */
	private static function from_start( $start ) {
		$r       = Util::rules();
		$raw     = self::day( $start )->modify( '+' . (int) $r['term_days'] . ' days' )->format( 'Y-m-d' );
		$last    = self::next_working( $raw );
		$missing = null;
		foreach ( array_unique( array( (int) substr( $raw, 0, 4 ), (int) substr( $last, 0, 4 ) ) ) as $y ) {
			if ( null === self::non_working( $y ) ) {
				$missing = $y;
			}
		}
		return array(
			'start'        => $start,
			'raw'          => $raw,
			'last'         => $last,
			'moved'        => $raw !== $last,
			'missing_year' => $missing,
		);
	}

	/**
	 * Day number: the delivery (start) day is day 0.
	 *
	 * @param string $start_ymd Start date.
	 * @param string $ymd       Day to number.
	 * @return int
	 */
	public static function day_number( $start_ymd, $ymd ) {
		$a = self::day( $start_ymd );
		$b = self::day( $ymd );
		return (int) round( ( $b->getTimestamp() - $a->getTimestamp() ) / DAY_IN_SECONDS );
	}

	/**
	 * Words for a day, as Pro's counter shows them.
	 *
	 * @param string $start_ymd Start date, or '' when nothing was delivered yet.
	 * @param string $last_ymd  Last day.
	 * @param string $ymd       Day.
	 * @return string
	 */
	public static function day_text( $start_ymd, $last_ymd, $ymd ) {
		if ( '' === (string) $start_ymd ) {
			return __( 'Sin entrega todavía: el plazo no empezó a correr', 'facilo-boton-arrepentimiento' );
		}
		if ( $ymd > $last_ymd ) {
			return __( 'Fuera de plazo según estos datos', 'facilo-boton-arrepentimiento' );
		}
		$r = Util::rules();
		/* translators: 1: day number, 2: term days. */
		return sprintf( __( 'Día %1$d de %2$d', 'facilo-boton-arrepentimiento' ), max( 0, self::day_number( $start_ymd, $ymd ) ), (int) $r['term_days'] );
	}

	/**
	 * Tickets and dated tourism: start = earlier of payment and ticket; limit = earlier of last 23:59:59 and event - 24 h.
	 *
	 * @param string $paid_ymd    Payment date.
	 * @param string $ticket_ymd  Ticket delivery date, or ''.
	 * @param string $event_local Event, 'Y-m-d H:i' or 'Y-m-d H:i:s', Argentine time.
	 * @return array limit ('Y-m-d H:i:s' local), by ('term' or 'event'), last, missing_year.
	 */
	public static function ticket_limit( $paid_ymd, $ticket_ymd, $event_local ) {
		$r     = Util::rules();
		$start = (string) $paid_ymd;
		if ( '' !== (string) $ticket_ymd && $ticket_ymd < $start ) {
			$start = (string) $ticket_ymd;
		}
		$term  = self::from_start( $start );
		$end   = new \DateTimeImmutable( $term['last'] . ' 23:59:59', Util::tz() );
		$event = new \DateTimeImmutable( (string) $event_local, Util::tz() );
		$event = $event->modify( '-' . (int) $r['event_hours'] . ' hours' );
		$by    = $event < $end ? 'event' : 'term';
		return array(
			'limit'        => ( 'event' === $by ? $event : $end )->format( 'Y-m-d H:i:s' ),
			'by'           => $by,
			'last'         => $term['last'],
			'missing_year' => $term['missing_year'],
		);
	}

	/**
	 * The line shown when a year has no holiday list.
	 *
	 * @param int $year Year.
	 * @return string
	 */
	public static function missing_note( $year ) {
		/* translators: %d: year. */
		return sprintf( __( 'Sin feriados cargados para %d: revisá la fecha final', 'facilo-boton-arrepentimiento' ), (int) $year );
	}

	/**
	 * A date at midnight, Argentine time.
	 *
	 * @param string $ymd Date.
	 * @return \DateTimeImmutable
	 */
	private static function day( $ymd ) {
		return new \DateTimeImmutable( substr( (string) $ymd, 0, 10 ) . ' 00:00:00', Util::tz() );
	}
}
