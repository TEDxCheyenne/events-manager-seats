<?php if (is_user_logged_in()): ?>
	<h2><?php echo __("Seats You Have Booked", 'em_seats'); ?></h2>
	<p>
		<?php _e('Here you can modify and delegate access to your guests.', 'em_seats'); ?>
		<?php _e('For each of your tickets, specify the attendee\'s name and email address.', 'em_seats'); ?>
		<?php _e('We will send them a digital copy of the ticket before the event.', 'em_seats'); ?>
		<?php _e('If you modify or cancel your booking, all of your guest information will be lost!', 'em_seats'); ?>
	</p>
	
	<?php if (!empty($this->messages)): ?>
		<?php foreach ($this->messages as $message): ?>
			<p><?php _e($message, 'em_seats'); ?></p>
		<?php endforeach; ?>
	<?php endif; ?>
	
	<?php if (!empty($editable['Events'])): ?>
		<form method="post">
			<?php wp_nonce_field('em_update_seats'); ?>
			<?php foreach ($editable['Events'] as $event_id => $event): ?>
				<h2><?php echo htmlspecialchars($event['event_name']); ?></h2>
				<?php echo $event['post_content']; ?>
				<?php foreach ($event['Tickets'] as $ticket_id => $ticket): ?>
					<h3><?php echo htmlspecialchars($ticket['ticket_name']); ?></h3>
					<?php foreach ($ticket['TicketsBookings'] as $ticket_booking_id => $ticket_booking): ?>
						<h4><?php
							$booking = $ticket_booking['Bookings'][$ticket_booking['booking_id']];
							
							$seatPlural = count($ticket_booking['Seats']) == 1
								? "Seat"
								: "Seats";
							echo sprintf(__("%d %s %s Booked on %s", 'em_seats'),
								count($ticket_booking['Seats']),
								$ticket['ticket_name'],
								$seatPlural,
								date('F j, Y', strtotime($booking['booking_date']))); ?></h4>
						<table>
							<thead>
							<tr>
								<th><?php _e('Locator', 'em_seats'); ?></th>
								<th><?php _e('First Name', 'em_seats'); ?></th>
								<th><?php _e('Last Name', 'em_seats'); ?></th>
								<th><?php _e('Email Address', 'em_seats'); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ($ticket_booking['Seats'] as $seat_id => $seat): ?>
								<tr>
									<td>
										<code><?php echo substr($seat['seat_locator'], 0, 10); ?></code>
										<input
											type="hidden"
											name="<?php echo esc_attr("em_seats[" . $seat['seat_id'] . "][seat_id]"); ?>"
											value="<?php echo esc_attr($seat['seat_id']); ?>">
									</td>
									<td><input
											type="text"
											title="First Name for Seat <?php esc_attr_e( substr($seat['seat_locator'], 0, 10)) ?>"
											name="<?php echo esc_attr("em_seats[" . $seat['seat_id'] . "][first_name]"); ?>"
											value="<?php echo esc_attr($seat['first_name']); ?>">
									</td>
									<td><input
											type="text"
											title="Last Name for Seat <?php esc_attr_e( substr($seat['seat_locator'], 0, 10)) ?>"
											name="<?php echo esc_attr("em_seats[" . $seat['seat_id'] . "][last_name]"); ?>"
											value="<?php echo esc_attr($seat['last_name']); ?>">
									</td>
									<td><input
											type="email"
											title="Email for <?php esc_attr_e( substr($seat['seat_locator'], 0, 10)) ?>"
											name="<?php echo esc_attr("em_seats[" . $seat['seat_id'] . "][email]"); ?>"
											value="<?php echo esc_attr($seat['email']); ?>">
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<p>
							<button type="submit"><?php _e('Save Seat Assignments', 'em_seats'); ?></button>
						</p>
					<?php endforeach; ?>
				<?php endforeach; ?>
			
			
			<?php endforeach; ?>
		</form>
	<?php else: ?>
		<p><em>
				<?php _e('You have not booked any seats at this time.', 'em_seats'); ?>
				<?php _e('If you feel this is incorrect, contact us immediately.', 'em_seats') ?></em>
		</p>
	
	<?php endif; ?>
	
	<h2><?php echo __("Seats Booked For You", 'em_seats'); ?></h2>
	<p>
		<?php _e('Here you can see tickets that have been purchased on your behalf.', 'em_seats'); ?>
		<?php _e('You can print the ticket if you would like.', 'em_seats'); ?></p>
	
	<?php if ( !empty($viewable) ) : ?>
		<section class="seats">
			<?php
			
			require dirname(dirname(__FILE__)) . '/vendor/autoload.php';
			$generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
			
			?>
			
			<?php foreach ($viewable as $ticket) : ?>
				<?php
				$ticket = (array)$ticket;
				
				$start_dt = new DateTime($ticket['event_start_date'] . " " . $ticket['event_start_time']);
				$stop_dt = new DateTime($ticket['event_end_date'] . " " . $ticket['event_end_time']);
				$df = "F j, Y ";
				$tf = "g:s A";
				?>
				<section class="seat">
					<h2>
						<?php echo htmlspecialchars( $ticket['event_name'] ); ?><br>
						<?php if ("" !== $ticket['first_name'] . $ticket['last_name']) : ?>
							<strong><?php echo htmlspecialchars( $ticket['first_name'] ); ?>
								&nbsp;<?php echo htmlspecialchars( $ticket['last_name'] ); ?></strong> &ndash;
						<?php endif; ?> <?php echo htmlspecialchars( $ticket['ticket_name'] ); ?>
					</h2>
					
					<p><?php echo $start_dt->format($df . $tf); ?> &ndash; <?php echo $stop_dt->format($tf); ?></p>
					
					<p><?php echo '<img src="data:image/png;base64,' . base64_encode($generator->getBarcode(substr($ticket['seat_locator'], 0, 10), $generator::TYPE_CODE_128)) . '">'; ?></p>
					<table>
						<tr>
							<th><?php _e('Record Locator', 'em_seats'); ?></th>
							<td><code><?php echo substr($ticket['seat_locator'], 0, 10); ?></code></td>
						</tr>
						<tr>
							<th><?php _e('Booked By', 'em_seats'); ?></th>
							<td><code><?php echo htmlspecialchars( $ticket['booked_by_email'] ); ?></code></td>
						</tr>
					</table>
				</section>
			<?php endforeach; ?>
		</section>
	<?php else: ?>
		<p>
			<?php _e('There are currently no tickets booked for you.', 'em_seats'); ?>
			<?php _e('If you\'re trying to print tickets that you booked, set the seat email address to your email address.', 'em_seats'); ?>
		</p>
	<?php endif; ?>

<?php else: ?>
	<p><?php
		printf(
			__('Please <a href="%s">login</a> to manage your bookings and seats.', 'em_seats'),
			wp_login_url( get_permalink() )
		);
		?></p>
<?php endif; ?>

