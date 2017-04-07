# Events Manager Seats

This plugin extends the awesome [Events Manager plugin](https://wordpress.org/plugins/events-manager/) by allowing users to manage the seats/spaces associated with their bookings.  For example, if a user purchases/reserves two seats, they will be able to assign names and email addresses to those seats.

## Installation

The plugin is far from complete or stable and is a work in progress.

1. Upload the folder to your `wp-content/plugins` directory
2. Activate the plugin
3. Place the `[em_seats]` shortcode on the page where you would like visitors to manage their seats.

## How It Works
The shortcode renders a form that retrieves all of the user's **approved** bookings.  The owner of the booking can assign names and email addresses to each seat.  The named user will be emailed to create an account.  Any seats will be visible to, but not editable by, the named user.