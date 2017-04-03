<h2><?php echo __( "Seats You Have Booked" ); ?></h2>
<p>Here you can modify and delegate access to your TEDxCheyenne guests. For each of your tickets, specify the attendee's
	name and email address. We will send them a digital copy of the ticket before the event. If you modify or cancel
	your booking, all of your guest information will be lost!</p>

<pre><?php print_r( $_user_edit ); ?></pre>
<pre><?php print_r( $user_edit ); ?></pre>
<?php if ( count( $user_edit ) > 0 ): ?>

	<?php foreach ( $this->groupBy( $user_edit, 'ticket_name' ) as $ticket_name => $ticket_bookings ): ?>
		<h3><?php echo $ticket_name; ?> Tickets</h3>
		<form method="post">
			<table>

				<?php foreach ( $this->groupBy( $ticket_bookings, 'booking_id' ) as $booking_id => $booking ): ?>

					<thead>
					<tr>
						<th colspan="4">
							Booking <?php echo $booking_id; ?>
						</th>
					</tr>
					<tr>
						<th>Locator</th>
						<th>First Name</th>
						<th>Last Name</th>
						<th>Email Address</th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( $booking as $seat ): ?>
						<tr>
							<td>
								<code><?php echo substr( $seat['seat_locator'], 0, 10 ); ?></code>
							</td>
							<td><input
									type="text"
									name="<?php echo esc_attr( "seats[owned][" . $seat['seat_locator'] . "][first_name]" ); ?>"
									value="<?php echo esc_attr( $seat['first_name'] ); ?>">
							</td>
							<td><input
									type="text"
									name="<?php echo esc_attr( "seats[owned][" . $seat['seat_locator'] . "][last_name]" ); ?>"
									value="<?php echo esc_attr( $seat['last_name'] ); ?>">
							</td>
							<td><input
									type="email"
									name="<?php echo esc_attr( "seats[owned][" . $seat['seat_locator'] . "][email]" ); ?>"
									value="<?php echo esc_attr( $seat['email'] ); ?>">
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				<?php endforeach; ?>
			</table>
			<p>
				<button type="submit">Save Seats</button>
			</p>
		</form>

	<?php endforeach; ?>

<?php else: ?>
	<p><em>You have not booked any seats at this time. If you feel this is incorrect, contact us immediately</em></p>

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
						<strong><?php echo $seat->first_name; ?><?php echo $seat->last_name; ?></strong> &ndash;
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
