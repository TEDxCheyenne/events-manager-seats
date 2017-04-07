<?php

/*
Plugin Name: Events Manager for TEDx
Plugin URI: https://tedxcheyenne.org
Description: Creates and allows users to manage seats
Version: 0.1.0
Author: Brad Kovach
Author URI: https://bradkovach.com
Text Domain: tedx-events-manager
*/

class SimpleOrm {

	/**
	 * @param $collection   mixed
	 * @param $keepers  array   properties to keep in the collection
	 *
	 * @return array
	 */
	static function pluck( $collection, $keepers ) {
		$result     = [];
		$collection = (array) $collection;
		foreach ( $keepers as $keeper ) {
			if ( isset( $collection[ $keeper ] ) ) {
				$result[ $keeper ] = $collection[ $keeper ];
			}
		}

		return $result;
	}

	/**
	 * @param $rows array   Unstructured rows of data
	 * @param $structure    array    Structure to impose upon the data.
	 *
	 * @return array
	 */
	static function map( $rows, $structure ) {

		$rows = (array) $rows;
		$groupedByKey = [];
		foreach ( $structure as $entity => $properties ) {

			if ( ! isset( $properties['__key'] ) ) {
				return [];
			}
			$primary_key = $properties['__key'];

			unset( $properties['__key'] );

			$keepers  = [];
			$children = [];

			foreach ( $properties as $propertyIdx => $property ) {
				if ( is_numeric( $propertyIdx ) ) {
					$keepers[] = $property;
				} else {
					$children[ $propertyIdx ] = $property;
				}
			}

			foreach ( $rows as $row_id => $rowObj ) {
				$row        = (array) $rowObj;
				$currentKey = $row[ $primary_key ];

				if ( isset( $groupedByKey[ $entity ][ $currentKey ] ) ) {
					continue;
				}

				$preparedRow = self::pluck( $row, $keepers );

				$subRows = array_filter($rows, function($_row) use ($primary_key, $currentKey) {
					return $_row->{$primary_key} === $currentKey;
				});

				foreach ( $children as $childEntity => $childProperties ) {
					$childStructure = array(
						$childEntity => $childProperties,
					);
					$preparedRow += self::map( $subRows, $childStructure );
				}

				$groupedByKey[ $entity ][ $currentKey ] = $preparedRow ;
			}
		}

		return $groupedByKey;
	}
}

class EventsManagerTEDx {

	var $messages= [];

	public function __construct() {

		add_filter( 'em_booking_set_status', array( $this, 'booking_set_status' ), 10, 2 );
		add_shortcode( 'em_seats', array( $this, 'current_user_seats_shortcode' ) );
		add_action( 'wp_head', array( $this, 'wp_head' ) );
		add_action('init', array($this, 'maybe_update_seats') );

	}

	public function maybe_update_seats() {
		if(
			isset($_POST['em_seats'])
		    && wp_verify_nonce($_POST['_wpnonce'], 'em_update_seats')
		) {
			foreach($_POST['em_seats'] as $seat) {
				$this->update_seat($seat);
			}
		}
	}



	public function wp_head() {
		?>
		<style>
			.seats > .seat + .seat {
				margin-top: 1.5em;
			}

			.seat {
				padding: 1.5em;
				border: solid 1px #ccc;
				page-break-inside: avoid;
			}

			.seat h2 {
				font-weight: normal;
				margin: 0;
				padding: 0;
			}

			.seat * {
				margin: 0;
				padding: 0;

			}

			.seat > * + * {
				margin-top: 1.5em;
			}

			.seat .right {
				float: left;

			}
		</style>
		<?php
	}

	public function booking_set_status( $old_status, $booking ) {
		/*
		 * 		$this->status_array = array(
			0 => __('Pending','events-manager'),
			1 => __('Approved','events-manager'),
			2 => __('Rejected','events-manager'),
			3 => __('Cancelled','events-manager'),
			4 => __('Awaiting Online Payment','events-manager'),
			5 => __('Awaiting Payment','events-manager')
		);
		 */
		switch ( $booking->booking_status ) {
			case 1: // Approved
				$this->create_seats( $booking );
				break;
			case 2: // Rejected
			case 3: // Cancelled
			case 0:
				$this->delete_seats( $booking );
				break;
		}
	}

	public function update_seat( $seat ) {
		global $wpdb;
		$user = wp_get_current_user();

		if( !($user instanceof WP_User) )
			die('Unable to update seat');

		if( !is_email($seat['email']) ) {
			$this->messages[] = __("Please enter valid email addresses", 'em_seats');
			return false;
		}

		$query = $wpdb->prepare(
			"
			UPDATE `" . $wpdb->prefix . "em_seats` Seat
			JOIN `" . $wpdb->prefix . "em_bookings` Booking on Seat.booking_id = Booking.booking_id
			JOIN `" . $wpdb->prefix . "users` User on Booking.person_id = User.id
			SET 
				Seat.first_name = %s,
				Seat.last_name = %s,
				Seat.email = %s
			WHERE User.id = %d
			AND Seat.seat_id = %d
			",
			$seat['first_name'],
			$seat['last_name'],
			$seat['email'],
			$user->ID,
			$seat['seat_id']
		);

		return $wpdb->query($query);
	}

	public function create_seats( $booking ) {
		global $wpdb;

		$query_header = sprintf( "INSERT INTO %sem_seats (booking_id, seat_locator, first_name, last_name, email)", $wpdb->prefix );
		$query_rows   = array();

		for ( $i = 0; $i < $booking->booking_spaces; $i ++ ) {
			$seat_locator = hash( 'sha256', sprintf( 'booking__%d__%d__%d__%s', $booking->timestamp, $booking->booking_id, $i, $booking->person->data->user_email ) );
			$query_rows[] = sprintf( "(%d, '%s', '%s', '%s', '%s')", $booking->booking_id, $seat_locator, "", "", $booking->person->data->user_email );
		}

		$query = $query_header;
		if ( count( $query_rows ) > 0 ) {
			$query = $query . " VALUES " . implode( ",", $query_rows );
		}

		$wpdb->query( $query );

	}

	public function delete_seats( $booking ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'em_seats', array(
			'booking_id' => $booking->booking_id,
		) );
	}

	public function current_user_seats_shortcode() {
		global $wpdb;
		$current_user = wp_get_current_user();

		$user_owns_query = $wpdb->prepare( "SELECT Booking.*, Ticket.*, Seat.*, Event.*
FROM `$wpdb->prefix" . "users` User
INNER JOIN `$wpdb->prefix" . "em_bookings` Booking on User.id = Booking.person_id
INNER JOIN `$wpdb->prefix" . "em_tickets_bookings` BookingsTickets on Booking.booking_id = BookingsTickets.booking_id
INNER JOIN `$wpdb->prefix" . "em_tickets` Ticket on BookingsTickets.ticket_id = Ticket.ticket_id
INNER JOIN `$wpdb->prefix" . "em_seats` Seat on Booking.booking_id = Seat.booking_id
INNER JOIN `$wpdb->prefix" . "em_events` Event on Ticket.event_id = Event.event_id
WHERE User.ID = %d", $current_user->ID );

		$user_view_query = $wpdb->prepare( "SELECT Ticket.*, Seat.*, Event.*, BookUser.*
FROM `$wpdb->prefix" . "users` User
INNER JOIN `$wpdb->prefix" . "em_seats` Seat on User.user_email = Seat.email
INNER JOIN `$wpdb->prefix" . "em_bookings` Booking on Seat.booking_id = Booking.booking_id
INNER JOIN `$wpdb->prefix" . "users` BookUser on Booking.person_id = BookUser.ID
INNER JOIN `$wpdb->prefix" . "em_tickets_bookings` TB on Booking.booking_id = TB.booking_id
INNER JOIN `$wpdb->prefix" . "em_tickets` Ticket on TB.ticket_id = Ticket.ticket_id
INNER JOIN `$wpdb->prefix" . "em_events` Event on Ticket.event_id = Event.event_id
WHERE User.ID = %d", $current_user->ID );


		$user_edit = $wpdb->get_results( $user_owns_query );

		$structure = array(
			'Events' => array(
				'__key'   => 'event_id',
				'event_id',
				'event_slug',
				'event_owner',
				'event_status',
				'event_name',
				'event_start_time',
				'event_end_time',
				'event_all_day',
				'event_start_date',
				'event_end_date',
				'post_content',
				'event_rsvp',
				'event_rsvp_date',
				'event_rsvp_time',
				'event_rsvp_spaces',
				'event_spaces',
				'event_private',
				'Tickets' => array(
					'__key'    => 'ticket_id',
					'ticket_id',
					'ticket_name',
					'ticket_description',
					'Bookings' => array(
						'__key' => 'booking_id',
						'booking_id',
						'booking_date',
						'booking_status',
						'booking_spaces',
						'Seats' => array(
							'__key' => 'seat_id',
							'seat_id',
							'seat_locator',
							'first_name',
							'last_name',
							'email',
						),
					),

				),
			),
		);

		$_user_edit = SimpleOrm::map( $user_edit, $structure );

		$user_view = $wpdb->get_results( $user_view_query );


		require 'my_seats.php';
	}

	static function groupBy( $collection, $key ) {
		$result     = array();
		$collection = (array) $collection;

		foreach ( $collection as $member ) {
			$data = (array) $member;
			$id   = $data[ $key ];
			if ( isset( $result[ $id ] ) ) {
				$result[ $id ][] = $data;
			} else {
				$result[ $id ] = array( $data );
			}
		}

		return $result;
	}

	static function log() {

		if ( WP_DEBUG ) {
			$result = array();

			foreach ( func_get_args() as $idx => $arg ) {
				$value = $arg;
				if ( is_object( $arg ) || is_array( $arg ) ) {
					$value = print_r( $arg, true );
				}

				array_push( $result, $value );

			}


			error_log( implode( "\t", $result ) );
		}
	}

	static function install() {
		global $wpdb;

		$query = "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "em_seats` ("
		         . "`seat_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, "
		         . "booking_id INT NOT NULL, "
		         . "seat_locator CHAR(64) NOT NULL, "
		         . "first_name VARCHAR(50) NOT NULL, "
		         . "last_name VARCHAR(50) NOT NULL, "
		         . "email VARCHAR(255) NOT NULL "
		         . ")";

		$wpdb->query( $query );

	}
}

$EventsManagerTEDx = new EventsManagerTEDx();
register_activation_hook( __FILE__, array( 'EventsManagerTEDx', 'install' ) );
