<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $nt_settings */
$s = isset( $nt_settings ) ? $nt_settings : array();

$cors_lines  = is_array( $s['cors_origins'] )  ? implode( "\n", $s['cors_origins'] )  : '';
$notify_lines = isset( $s['notify_emails'] ) && is_array( $s['notify_emails'] ) ? implode( "\n", $s['notify_emails'] ) : '';

$routes_json = wp_json_encode(
	is_array( $s['routes'] ) ? $s['routes'] : array(),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
if ( false === $routes_json ) {
	$routes_json = '[]';
}

$durations = is_array( $s['default_durations'] ) ? $s['default_durations'] : array();

$duration_choices = array(
	'1h'          => __( '1 hour',      'nt-alerts' ),
	'2h'          => __( '2 hours',     'nt-alerts' ),
	'4h'          => __( '4 hours',     'nt-alerts' ),
	'rest_of_day' => __( 'Rest of day', 'nt-alerts' ),
	'long_term'   => __( 'Long-term',   'nt-alerts' ),
);

$category_labels = array(
	'detour'         => __( 'Detour',         'nt-alerts' ),
	'delay'          => __( 'Delay',          'nt-alerts' ),
	'cancelled_trip' => __( 'Cancelled trip', 'nt-alerts' ),
	'stop_closure'   => __( 'Stop closure',   'nt-alerts' ),
	'weather'        => __( 'Weather',        'nt-alerts' ),
	'other'          => __( 'Other',          'nt-alerts' ),
);
?>

<div class="wrap nt-alerts-wrap nt-alerts-settings">

	<h1><?php esc_html_e( 'Service Alerts settings', 'nt-alerts' ); ?></h1>

	<?php settings_errors(); ?>

	<form method="post" action="options.php" class="nt-settings-form">
		<?php settings_fields( NT_Alerts_Settings::OPTION_GROUP ); ?>

		<section class="nt-settings-section">
			<h2><?php esc_html_e( 'Allowed CORS origins', 'nt-alerts' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'One origin per line, including scheme. Example: https://nrtransit.ca. Wildcards are not allowed. Same-origin requests (this WordPress site) are already permitted for the public read endpoints — no need to list them here.', 'nt-alerts' ); ?>
			</p>
			<label class="screen-reader-text" for="nt-cors-origins"><?php esc_html_e( 'Allowed CORS origins', 'nt-alerts' ); ?></label>
			<textarea id="nt-cors-origins"
			          name="<?php echo esc_attr( NT_Alerts_Settings::OPT_CORS_ORIGINS ); ?>"
			          rows="5"
			          class="nt-settings-textarea"><?php echo esc_textarea( $cors_lines ); ?></textarea>
		</section>

		<section class="nt-settings-section">
			<h2><?php esc_html_e( 'Notify on every new alert', 'nt-alerts' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Email addresses that receive a notification each time an alert is posted. One per line. Leave blank to disable.', 'nt-alerts' ); ?>
			</p>
			<label class="screen-reader-text" for="nt-notify-emails"><?php esc_html_e( 'Notification emails', 'nt-alerts' ); ?></label>
			<textarea id="nt-notify-emails"
			          name="<?php echo esc_attr( NT_Alerts_Settings::OPT_NEW_ALERT_NOTIFY ); ?>"
			          rows="4"
			          class="nt-settings-textarea"><?php echo esc_textarea( $notify_lines ); ?></textarea>
		</section>

		<section class="nt-settings-section">
			<h2><?php esc_html_e( 'API cache duration', 'nt-alerts' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Seconds to cache the public alerts response. Lower values are fresher but cost more database reads. Range 5–3600.', 'nt-alerts' ); ?>
			</p>
			<label for="nt-cache-ttl">
				<?php esc_html_e( 'Seconds:', 'nt-alerts' ); ?>
				<input type="number"
				       id="nt-cache-ttl"
				       name="<?php echo esc_attr( NT_Alerts_Settings::OPT_CACHE_TTL ); ?>"
				       value="<?php echo esc_attr( $s['cache_ttl'] ); ?>"
				       min="5" max="3600" step="1" class="small-text">
			</label>
		</section>

		<section class="nt-settings-section">
			<h2><?php esc_html_e( 'Embed script version pin', 'nt-alerts' ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: %s: plugin version */
					esc_html__( 'Appended as the ?ver=… query string on embed.js / embed.css. Update only when you want third-party caches to refresh. Plugin is currently at %s.', 'nt-alerts' ),
					'<code>' . esc_html( NT_ALERTS_VERSION ) . '</code>'
				);
				?>
			</p>
			<label for="nt-embed-version">
				<?php esc_html_e( 'Pinned version:', 'nt-alerts' ); ?>
				<input type="text"
				       id="nt-embed-version"
				       name="<?php echo esc_attr( NT_Alerts_Settings::OPT_EMBED_VERSION ); ?>"
				       value="<?php echo esc_attr( $s['embed_version'] ); ?>"
				       class="regular-text"
				       pattern="[A-Za-z0-9._-]+">
			</label>
		</section>

		<section class="nt-settings-section">
			<h2><?php esc_html_e( 'Auto-archive expired alerts', 'nt-alerts' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'After this many days an expired alert is moved to the archive (out of the supervisor dashboard). Range 1–365.', 'nt-alerts' ); ?>
			</p>
			<label for="nt-archive-after">
				<?php esc_html_e( 'Days:', 'nt-alerts' ); ?>
				<input type="number"
				       id="nt-archive-after"
				       name="<?php echo esc_attr( NT_Alerts_Settings::OPT_ARCHIVE_AFTER ); ?>"
				       value="<?php echo esc_attr( $s['archive_after'] ); ?>"
				       min="1" max="365" step="1" class="small-text">
			</label>
		</section>

		<section class="nt-settings-section">
			<h2><?php esc_html_e( 'Default duration per category', 'nt-alerts' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Used to pre-select a duration when a supervisor picks a category in the new-alert form.', 'nt-alerts' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
				<?php foreach ( $category_labels as $cat => $label ) :
					$current = isset( $durations[ $cat ] ) ? $durations[ $cat ] : '2h';
					?>
					<tr>
						<th scope="row">
							<label for="nt-duration-<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $label ); ?></label>
						</th>
						<td>
							<select id="nt-duration-<?php echo esc_attr( $cat ); ?>"
							        name="<?php echo esc_attr( NT_Alerts_Settings::OPT_DEFAULT_DURATIONS . '[' . $cat . ']' ); ?>">
								<?php foreach ( $duration_choices as $value => $dlabel ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"
									        <?php selected( $current, $value ); ?>>
										<?php echo esc_html( $dlabel ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>

		<section class="nt-settings-section">
			<h2><?php esc_html_e( 'Stops catalogue', 'nt-alerts' ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: %d: number of stops currently seeded */
					esc_html__( '%d stops are currently in the catalogue. Stops are seeded from the bundled GTFS export on activation. To refresh after a GTFS update, replace data/stops-default.php in the plugin folder and click Re-seed below.', 'nt-alerts' ),
					(int) ( isset( $s['stops_count'] ) ? $s['stops_count'] : 0 )
				);
				?>
			</p>
			<?php if ( ! empty( $s['reseeded'] ) ) : ?>
				<div class="notice notice-success inline" role="status">
					<p>
						<?php
						printf(
							/* translators: %d: stops count */
							esc_html__( 'Re-seeded %d stops from the bundled GTFS data.', 'nt-alerts' ),
							(int) $s['reseeded']
						);
						?>
					</p>
				</div>
			<?php endif; ?>
			<p>
				<button type="submit" form="nt-reseed-stops-form" class="button">
					<?php esc_html_e( 'Re-seed stops from default file', 'nt-alerts' ); ?>
				</button>
			</p>
		</section>

		<section class="nt-settings-section">
			<h2><?php esc_html_e( 'Routes catalogue', 'nt-alerts' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'JSON array of route groups. Each group has a label and a list of routes (id, label, optional color). Saving here busts the public alerts cache.', 'nt-alerts' ); ?>
			</p>

			<details class="nt-settings-help">
				<summary><?php esc_html_e( 'Show example shape', 'nt-alerts' ); ?></summary>
<pre><code>[
  {
    "group": "St. Catharines / Thorold",
    "routes": [
      { "id": "301", "label": "Route 301 — Hospital", "color": "#ed171f" }
    ]
  }
]</code></pre>
			</details>

			<label class="screen-reader-text" for="nt-routes-json"><?php esc_html_e( 'Routes JSON', 'nt-alerts' ); ?></label>
			<textarea id="nt-routes-json"
			          name="<?php echo esc_attr( NT_Alerts_Settings::OPT_ROUTES ); ?>[json]"
			          rows="20"
			          spellcheck="false"
			          class="nt-settings-textarea nt-settings-textarea--mono"><?php echo esc_textarea( $routes_json ); ?></textarea>
		</section>

		<?php submit_button( __( 'Save settings', 'nt-alerts' ) ); ?>
	</form>

	<form id="nt-reseed-stops-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:none;">
		<input type="hidden" name="action" value="nt_alerts_reseed_stops">
		<?php wp_nonce_field( 'nt_alerts_reseed_stops' ); ?>
	</form>
</div>
