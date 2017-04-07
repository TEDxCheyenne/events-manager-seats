<?php

/*
Plugin Name: Events Manager Seats
Plugin URI: https://tedxcheyenne.org
Description: Extends the Events Manager plugin to allow users to assign their seats to users.
Version: 1.0.0
Author: Brad Kovach
Author URI: https://bradkovach.com
Text Domain: em_seats
*/

require 'classes/SimpleOrm.class.php';

/**
 * Class EventsManagerSeats
 */
class EventsManagerSeats
{
	
	var $messages = [];
	
	public function __construct()
	{
		if (file_exists(WP_PLUGIN_DIR . '/events-manager/events-manager.php')) {
			// This will listen to the `em_booking_set_status` event and create seats whenever a booking is approved
			add_filter('em_booking_set_status', array($this, 'em_booking_set_status'), 10, 2);
			
			// em_seats can be placed on a page to show the users their bookings and allow them to configure their seats.
			add_shortcode('em_seats', array($this, 'em_seats_shortcode'));
			
			// maybe_update_seats validates that incoming data is from a valid, nonced form
			add_action('init', array($this, 'maybe_update_seats'));
			
			// Styles for rendering the Seat tickets.
			add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
		} else {
			add_action('admin_notices', array($this, 'em_is_required_admin_notice'));
		}
		
	}
	
	public function enqueue_scripts()
	{
		wp_enqueue_style('em_seats_styles', plugin_dir_url(__FILE__) . 'assets/style.css');
	}
	
	public function em_is_required_admin_notice()
	{
		$class = 'notice notice-error';
		$message = __('Please activate the Events Manager plugin to use Events Manager Seats', 'em_seats');
		
		printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
	}
	
	/**
	 * Analyzes incoming data to check for a situation where seats should be updated.
	 */
	public function maybe_update_seats()
	{
		if (
			isset($_POST['em_seats'])
			&& wp_verify_nonce($_POST['_wpnonce'], 'em_update_seats')
		) {
			foreach ($_POST['em_seats'] as $seat) {
				$this->update_seat($seat);
			}
		}
	}
	
	/**
	 * @param $old_status
	 * @param $booking  EM_Booking  Booking to process seats for.
	 */
	public function em_booking_set_status($old_status, $booking)
	{
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
		switch ($booking->booking_status) {
			case 1: // Approved
				$this->create_seats($booking);
				break;
			default:
				$this->delete_seats($booking);
				break;
		}
	}
	
	/**
	 * Updates a seat as long as the user owns it.
	 * @param $seat array   Array filled with Seat parameters
	 * @return bool|false|int
	 */
	public function update_seat($seat)
	{
		global $wpdb;
		$user = wp_get_current_user();
		
		if (!($user instanceof WP_User))
			die('Unable to update seat');
		
		if (!is_email($seat['email'])) {
			$this->messages[] = __("Please enter valid email addresses", 'em_seats');
			return false;
		}
		
		$query = $wpdb->prepare(
			"
			UPDATE `" . $this->table_name('em_seats') . "` Seat
			JOIN `" . $this->table_name('em_tickets_bookings') . "` TicketsBookings on Seat.ticket_booking_id = TicketsBookings.ticket_booking_id 
			JOIN `" . $this->table_name('em_bookings') . "` Booking on TicketsBookings.booking_id = Booking.booking_id
			JOIN `" . $this->table_name('users') . "` User on Booking.person_id = User.id
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
	
	public static function table_name($table_name)
	{
		global $wpdb;
		
		return $wpdb->prefix . $table_name;
	}
	
	/**
	 * @param $booking  EM_Booking  The booking to create seats for.
	 */
	public function create_seats($booking)
	{
		global $wpdb;
		
		$query_rows = array();
		
		foreach ($booking->tickets_bookings->tickets_bookings as $ticket_id => $ticket_booking) {
			for ($i = 0; $i < $ticket_booking->ticket_booking_spaces; $i++) {
				$seat_locator = hash('sha256', sprintf('booking__%d__%d__%d__%d__%s',
					$booking->timestamp,
					$ticket_id,
					$ticket_booking->ticket_booking_id,
					$i,
					$booking->person->data->user_email
				));
				$query_rows[] = $wpdb->prepare("(%d, %s, %s, %s, %s)",
					$ticket_booking->ticket_booking_id,
					$seat_locator,
					"",
					"",
					$booking->person->data->user_email
				);
			}
		}
//
//        for ($i = 0; $i < $booking->booking_spaces; $i++) {

//        }
		
		$query = "INSERT INTO " . $this->table_name('em_seats') . " (ticket_booking_id, seat_locator, first_name, last_name, email)";
		
		if (count($query_rows) > 0) {
			$query = $query . " VALUES " . implode(",", $query_rows);
		}
		print_r($query);
		//       $wpdb->query($query);
		
	}
	
	/**
	 * @param $booking  EM_Booking  Booking object to delete corresponding seats for.
	 */
	public function delete_seats($booking)
	{
		global $wpdb;
		
		foreach ($booking->tickets_bookings->tickets_bookings as $ticket_id => $ticket_booking) {
			$wpdb->delete($wpdb->prefix . 'em_seats', array(
				'ticket_booking_id' => $ticket_booking->ticket_booking_id,
			));
		}
	}
	
	/**
	 * Handles the querying of data and rendering of the seat management form.
	 */
	public function em_seats_shortcode()
	{
		global $wpdb;
		
		$editable = [];
		$viewable = [];
		if ( is_user_logged_in() ) {
			
			$current_user = wp_get_current_user();
			
			$editable_query = $wpdb->prepare("
SELECT 
	User.*,
	Booking.*,
	TicketsBookings.*,
	Ticket.*,
	Seat.*,
	Evt.*
FROM `" . $this->table_name('users') . "` User
INNER JOIN `" . $this->table_name('em_bookings') . "` Booking on User.id = Booking.person_id
INNER JOIN `" . $this->table_name('em_tickets_bookings') . "` TicketsBookings on Booking.booking_id = TicketsBookings.booking_id
INNER JOIN `" . $this->table_name('em_tickets') . "` Ticket on Ticket.ticket_id = TicketsBookings.ticket_id
INNER JOIN `" . $this->table_name('em_events') . "` Evt on Ticket.event_id = Evt.event_id
INNER JOIN `" . $this->table_name('em_seats') . "` Seat on TicketsBookings.ticket_booking_id = Seat.ticket_booking_id
WHERE User.ID = %d", $current_user->ID);
			
			$viewable_query = $wpdb->prepare("
SELECT Evt.*, Ticket.*, TicketsBookings.*, Booking.*, Seat.*, BookUser.user_email as booked_by_email
FROM `" . $this->table_name('users') . "` User
INNER JOIN `" . $this->table_name('em_seats') . "` Seat on User.user_email = Seat.email
INNER JOIN `" . $this->table_name('em_tickets_bookings') . "` TicketsBookings on Seat.ticket_booking_id = TicketsBookings.ticket_booking_id
INNER JOIN `" . $this->table_name('em_tickets') . "` Ticket on TicketsBookings.ticket_id = Ticket.ticket_id
INNER JOIN `" . $this->table_name('em_bookings') . "` Booking on Booking.booking_id = TicketsBookings.booking_id
INNER JOIN `" . $this->table_name('em_events') . "` Evt on Evt.event_id = Ticket.event_id
INNER JOIN `" . $this->table_name('users') . "` BookUser on Booking.person_id = BookUser.ID
WHERE User.user_email = %s
AND DATE(Evt.event_end_date) >= CURDATE()", $current_user->data->user_email);
			
			
			$structure_editable = array(
				'Events' => array(
					'__key' => 'event_id',
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
						'__key' => 'ticket_id',
						'ticket_id',
						'ticket_name',
						'ticket_description',
						'TicketsBookings' => array(
							'__key' => 'ticket_booking_id',
							'ticket_booking_id',
							'ticket_id',
							'booking_id',
							'Bookings' => array(
								'__key' => 'booking_id',
								'booking_id',
								'booking_date',
								'booking_status',
								'booking_spaces',
							),
							'Seats' => array(
								'__key' => 'seat_id',
								'seat_id',
								'seat_locator',
								'first_name',
								'last_name',
								'email',
							),
						)
					
					),
				),
			);
			
			
			$editable = SimpleOrm::map($wpdb->get_results($editable_query), $structure_editable);
			$viewable = $wpdb->get_results($viewable_query);
			
		}
		
		
		require 'templates/shortcode__em_seats.php';
	}
	
	/**
	 * Logs data if the WordPress installation has WP_DEBUG set and true.
	 */
	static function log()
	{
		if (WP_DEBUG) {
			$result = array();
			
			foreach (func_get_args() as $idx => $arg) {
				$value = $arg;
				if (is_object($arg) || is_array($arg)) {
					$value = print_r($arg, true);
				}
				
				array_push($result, $value);
				
			}
			
			
			error_log(implode("\t", $result));
		}
	}
	
	/**
	 * Performs install procedure for Events Manager.
	 */
	static function install()
	{
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		$query = "CREATE TABLE IF NOT EXISTS `" . self::table_name('em_seats') . "` ("
			. "`seat_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, "
			. "ticket_booking_id INT NOT NULL, "
			. "seat_locator CHAR(64) NOT NULL, "
			. "first_name VARCHAR(50) NOT NULL, "
			. "last_name VARCHAR(50) NOT NULL, "
			. "email VARCHAR(255) NOT NULL "
			. ") $charset_collate;";
		
		$wpdb->query($query);
		
	}
	
	/**
	 * Performs deactivation procedure for Events Manager Seats plugin
	 */
	static function deactivate()
	{
		self::log("Deactivating Events Manager Seats plugin", __FILE__);
	}
	
	/**
	 * Performs uninstallation procedure for Events Manager Seats plugin
	 */
	static function uninstall()
	{
		self::log("Uninstalling Events Manager Seats plugin", __FILE__);
	}
}

$EventsManagerSeats = new EventsManagerSeats();

// Creates the Plugin's table
register_activation_hook(__FILE__, array('EventsManagerSeats', 'install'));

// On Deactivate
register_deactivation_hook(__FILE__, array("EventsManagerSeats", 'deactivate'));

// On Uninstall
register_uninstall_hook(__FILE__, array('EventsManagerSeats', 'uninstall'));
