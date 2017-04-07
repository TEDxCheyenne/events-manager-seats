<?php if ( is_user_logged_in() ): ?>

	<h2><?php echo __( "Seats You Have Booked" ); ?></h2>
	<p>Here you can modify and delegate access to your TEDxCheyenne guests. For each of your tickets, specify the
		attendee's
		name and email address. We will send them a digital copy of the ticket before the event. If you modify or cancel
		your booking, all of your guest information will be lost!</p>

	<?php if ( ! empty( $this->messages ) ): ?>
		<?php foreach ( $this->messages as $message ): ?>
			<p><?php _e( $message ); ?></p>
		<?php endforeach; ?>
	<?php endif; ?>

	<form method="post">
	<?php wp_nonce_field( 'em_update_seats' ); ?>
	<?php if ( ! empty( $_user_edit['Events'] ) ): ?>
		<?php foreach ( $_user_edit['Events'] as $event_id => $event ): ?>
			<h2><?php echo htmlspecialchars( $event['event_name'] ); ?></h2>
			<?php foreach ( $event['Tickets'] as $ticket_id => $ticket ): ?>
				<h3><?php echo htmlspecialchars( $ticket['ticket_name'] ); ?></h3>
				<?php foreach ( $ticket['Bookings'] as $booking_id => $booking ): ?>
					<?php $seatPlural = $booking['booking_spaces'] == 1
						? "Seat"
						: "Seats";
					?>
					<h4><?php echo sprintf( "%d %s Booked on %s", $booking['booking_spaces'], $seatPlural, date( 'F j, Y', strtotime( $booking['booking_date'] ) ) ); ?></h4>
					<table>
						<thead>
						<tr>
							<th><?php _e( 'Locator', 'em_seats' ); ?></th>
							<th><?php _e( 'First Name', 'em_seats' ); ?></th>
							<th><?php _e( 'Last Name', 'em_seats' ); ?></th>
							<th><?php _e( 'Email Address', 'em_seats' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ( $booking['Seats'] as $seat_id => $seat ): ?>
							<tr>
								<td>
									<code><?php echo substr( $seat['seat_locator'], 0, 10 ); ?></code>
									<input
										type="hidden"
										name="<?php echo esc_attr( "em_seats[" . $seat['seat_id'] . "][seat_id]" ); ?>"
										value="<?php echo esc_attr( $seat['seat_id'] ); ?>">
								</td>
								<td><input
										type="text"
										name="<?php echo esc_attr( "em_seats[" . $seat['seat_id'] . "][first_name]" ); ?>"
										value="<?php echo esc_attr( $seat['first_name'] ); ?>">
								</td>
								<td><input
										type="text"
										name="<?php echo esc_attr( "em_seats[" . $seat['seat_id'] . "][last_name]" ); ?>"
										value="<?php echo esc_attr( $seat['last_name'] ); ?>">
								</td>
								<td><input
										type="email"
										name="<?php echo esc_attr( "em_seats[" . $seat['seat_id'] . "][email]" ); ?>"
										value="<?php echo esc_attr( $seat['email'] ); ?>">
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<button type="submit">Save Seat Assignments</button>
					</p>
				<?php endforeach; ?>
			<?php endforeach; ?>


		<?php endforeach; ?>
		</form>
	<?php else: ?>
		<p><em>You have not booked any seats at this time. If you feel this is incorrect, contact us immediately</em>
		</p>

	<?php endif; ?>

	<h2><?php echo __( "Seats Booked For You" ); ?></h2>
	<p>Here you can see tickets that have been purchased on your behalf. You can print the ticket if you would like</p>

	<?php if ( count( $user_view ) > 0 ) : ?>
		<section class="seats">
			<?php
			require __DIR__ . '/vendor/autoload.php';
			$generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
			?>

			<?php foreach ( $user_view as $seat ) : ?>
				<?php
				$start_dt = new DateTime( $seat->event_start_date . " " . $seat->event_start_time );
				$stop_dt  = new DateTime( $seat->event_end_date . " " . $seat->event_end_time );
				$df       = "F j, Y ";
				$tf       = "g:s A";
				?>
				<section class="seat">

					<h2>
						<?php echo $seat->event_name; ?><br>
						<?php if ( "" !== $seat->first_name . $seat->last_name ) : ?>
							<strong><?php echo $seat->first_name; ?>
								&nbsp;<?php echo $seat->last_name; ?></strong> &ndash;
						<?php endif; ?> <?php echo $seat->ticket_name; ?>
					</h2>

					<p><?php echo $start_dt->format( $df . $tf ); ?> &ndash; <?php echo $stop_dt->format( $tf ); ?></p>

					<p><?php echo $seat->post_content; ?></p>
					<p><?php echo '<img src="data:image/png;base64,' . base64_encode( $generator->getBarcode( substr( $seat->seat_locator, 0, 10 ), $generator::TYPE_CODE_128 ) ) . '">'; ?></p>
					<table>
						<tr>
							<th>Record Locator</th>
							<td><code><?php echo substr( $seat->seat_locator, 0, 10 ); ?></code></td>
						</tr>
						<tr>
							<th>Booked By</th>
							<td><code><?php echo $seat->user_email; ?></code></td>
						</tr>
					</table>
				</section>
			<?php endforeach; ?>
		</section>
	<?php else: ?>
		<p>There are currently no tickets booked for you.</p>
	<?php endif; ?>

<?php else: ?>
	<p>Please login to manage your bookings and seats.</p>
<?php endif; ?>