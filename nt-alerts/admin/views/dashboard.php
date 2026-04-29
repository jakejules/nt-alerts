<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $nt_alerts_data */
$data = isset( $nt_alerts_data ) ? $nt_alerts_data : array();

$active      = isset( $data['active'] )      ? $data['active']      : array();
$ongoing     = isset( $data['ongoing'] )     ? $data['ongoing']     : array();
$ended_today = isset( $data['ended_today'] ) ? $data['ended_today'] : array();
$can_see_all = ! empty( $data['can_see_all'] );

$reason_labels          = isset( $data['reason_labels'] )          ? $data['reason_labels']          : array();
$dept_labels            = isset( $data['dept_labels'] )            ? $data['dept_labels']            : array();
$internal_reason_labels = isset( $data['internal_reason_labels'] ) ? $data['internal_reason_labels'] : array();

$GLOBALS['nt_alerts_label_lookup'] = compact( 'reason_labels', 'dept_labels', 'internal_reason_labels' );

$new_url = admin_url( 'admin.php?page=' . NT_Alerts_Admin::MENU_SLUG . '&action=new' );

/**
 * Pick black or white text for a hex bg using a YIQ luminance check.
 */
function nt_alerts_pick_text_color( $hex ) {
	if ( ! is_string( $hex ) || '' === $hex || '#' !== $hex[0] ) {
		return '#1a1a1a';
	}
	$c = ltrim( $hex, '#' );
	if ( 3 === strlen( $c ) ) {
		$c = $c[0] . $c[0] . $c[1] . $c[1] . $c[2] . $c[2];
	}
	if ( 6 !== strlen( $c ) ) {
		return '#1a1a1a';
	}
	$r = hexdec( substr( $c, 0, 2 ) );
	$g = hexdec( substr( $c, 2, 2 ) );
	$b = hexdec( substr( $c, 4, 2 ) );
	$yiq = ( $r * 299 + $g * 587 + $b * 114 ) / 1000;
	return $yiq >= 150 ? '#1a1a1a' : '#ffffff';
}

function nt_alerts_severity_icon( $severity ) {
	$paths = array(
		'info'     => 'M12 7a1 1 0 100 2 1 1 0 000-2zm-1 4h2v6h-2v-6z',
		'warning'  => 'M12 3l10 17H2L12 3zm0 5v5h0m0 3v1',
		'critical' => 'M12 2a10 10 0 100 20 10 10 0 000-20zm-1 5h2v7h-2V7zm0 9h2v2h-2v-2z',
	);
	$d = isset( $paths[ $severity ] ) ? $paths[ $severity ] : $paths['info'];
	return sprintf(
		'<svg class="nt-alerts-card__severity-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="%s" fill="currentColor"/></svg>',
		esc_attr( $d )
	);
}

function nt_alerts_severity_label( $severity ) {
	$labels = array(
		'info'     => __( 'Info', 'nt-alerts' ),
		'warning'  => __( 'Warning', 'nt-alerts' ),
		'critical' => __( 'Critical', 'nt-alerts' ),
	);
	return isset( $labels[ $severity ] ) ? $labels[ $severity ] : ucfirst( (string) $severity );
}

/**
 * Render a single alert card.
 *
 * @param array $alert Transformed alert (NT_Alerts_Alert::to_admin_array output).
 * @param bool  $show_actions Whether to show Edit / Extend / End Now buttons.
 */
function nt_alerts_render_card( $alert, $show_actions ) {
	$id        = (int) $alert['id'];
	$severity  = isset( $alert['severity'] ) ? $alert['severity'] : 'info';
	$title     = isset( $alert['title'] ) ? $alert['title'] : '';
	$desc      = isset( $alert['description'] ) ? $alert['description'] : '';
	$routes_d  = isset( $alert['routes_detail'] ) && is_array( $alert['routes_detail'] ) ? $alert['routes_detail'] : array();
	if ( empty( $routes_d ) && ! empty( $alert['routes'] ) ) {
		$routes_d = array_map( function ( $rid ) {
			return array( 'id' => (string) $rid, 'color' => '', 'label' => '' );
		}, (array) $alert['routes'] );
	}
	$posted    = isset( $alert['posted_at'] ) ? $alert['posted_at'] : '';
	$expires   = isset( $alert['expires_at'] ) ? $alert['expires_at'] : '';
	$posted_by = isset( $alert['posted_by']['name'] ) ? (string) $alert['posted_by']['name'] : '';
	?>
	<article class="nt-alerts-card nt-alerts-card--<?php echo esc_attr( $severity ); ?>"
	         data-alert-id="<?php echo esc_attr( $id ); ?>"
	         data-end-time="<?php echo esc_attr( $expires ); ?>"
	         data-alert-title="<?php echo esc_attr( $title ); ?>"
	         aria-labelledby="nt-alert-<?php echo esc_attr( $id ); ?>-title">

		<header class="nt-alerts-card__head">
			<span class="nt-alerts-card__severity-chip">
				<?php echo nt_alerts_severity_icon( $severity ); // SVG, escaped in helper. ?>
				<span class="nt-alerts-card__severity-chip-text">
					<?php echo esc_html( nt_alerts_severity_label( $severity ) ); ?>
				</span>
			</span>
		</header>

		<h3 id="nt-alert-<?php echo esc_attr( $id ); ?>-title" class="nt-alerts-card__title">
			<?php echo esc_html( $title ); ?>
		</h3>

		<?php if ( $desc ) : ?>
			<p class="nt-alerts-card__desc"><?php echo esc_html( $desc ); ?></p>
		<?php endif; ?>

		<?php if ( $routes_d ) : ?>
			<ul class="nt-alerts-card__route-chips" aria-label="<?php esc_attr_e( 'Routes affected', 'nt-alerts' ); ?>">
				<?php foreach ( $routes_d as $r ) :
					$rid    = isset( $r['id'] ) ? (string) $r['id'] : '';
					$rcolor = isset( $r['color'] ) ? (string) $r['color'] : '';
					$rlabel = isset( $r['label'] ) ? (string) $r['label'] : '';
					if ( '' === $rid ) { continue; }
					$style = '';
					if ( '' !== $rcolor ) {
						$style = sprintf(
							'background-color:%s;border-color:%s;color:%s;',
							esc_attr( $rcolor ),
							esc_attr( $rcolor ),
							esc_attr( nt_alerts_pick_text_color( $rcolor ) )
						);
					}
					?>
					<li class="nt-alerts-card__route-chip-item">
						<span class="nt-alerts-card__route-chip"
						      <?php if ( $style ) : ?>style="<?php echo $style; ?>"<?php endif; ?>
						      <?php if ( $rlabel ) : ?>title="<?php echo esc_attr( $rlabel ); ?>"<?php endif; ?>>
							<?php echo esc_html( $rid ); ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php
		$lookup = isset( $GLOBALS['nt_alerts_label_lookup'] ) ? $GLOBALS['nt_alerts_label_lookup'] : array();
		$reason_labels          = isset( $lookup['reason_labels'] )          ? $lookup['reason_labels']          : array();
		$dept_labels            = isset( $lookup['dept_labels'] )            ? $lookup['dept_labels']            : array();
		$internal_reason_labels = isset( $lookup['internal_reason_labels'] ) ? $lookup['internal_reason_labels'] : array();

		$reason_key = isset( $alert['reason'] ) ? $alert['reason'] : '';
		if ( $reason_key && isset( $reason_labels[ $reason_key ] ) ) : ?>
			<p class="nt-alerts-card__reason">
				<span class="nt-alerts-card__reason-label"><?php esc_html_e( 'Reason:', 'nt-alerts' ); ?></span>
				<?php echo esc_html( $reason_labels[ $reason_key ] ); ?>
			</p>
		<?php endif; ?>

		<?php
		$closed_stops = isset( $alert['closed_stops'] ) && is_array( $alert['closed_stops'] ) ? $alert['closed_stops'] : array();
		$alt_stops    = isset( $alert['alternate_stops'] ) && is_array( $alert['alternate_stops'] ) ? $alert['alternate_stops'] : array();

		$render_stops_block = function ( $heading_class, $heading, $list ) {
			if ( empty( $list ) ) {
				return;
			}
			?>
			<div class="nt-alerts-card__stops nt-alerts-card__stops--<?php echo esc_attr( $heading_class ); ?>">
				<p class="nt-alerts-card__stops-label"><?php echo esc_html( $heading ); ?></p>
				<ul class="nt-alerts-card__stops-list">
					<?php foreach ( $list as $s ) :
						$name = isset( $s['name'] ) && '' !== $s['name'] ? $s['name'] : ( isset( $s['id'] ) ? $s['id'] : '' );
						if ( '' === $name ) {
							continue;
						}
						?>
						<li><?php echo esc_html( $name ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		};

		$render_stops_block( 'closed', __( 'Closed:', 'nt-alerts' ), $closed_stops );
		$render_stops_block( 'alternate', __( 'Use instead:', 'nt-alerts' ), $alt_stops );
		?>

		<?php
		$images = isset( $alert['images'] ) && is_array( $alert['images'] ) ? $alert['images'] : array();
		if ( $images ) : ?>
			<div class="nt-alerts-card__images">
				<?php foreach ( $images as $img ) :
					if ( empty( $img['thumbnail'] ) ) {
						continue;
					}
					?>
					<a class="nt-alerts-card__image"
					   href="<?php echo esc_url( $img['url'] ); ?>"
					   target="_blank"
					   rel="noopener">
						<img src="<?php echo esc_url( $img['thumbnail'] ); ?>"
						     alt="<?php echo esc_attr( isset( $img['alt'] ) ? $img['alt'] : '' ); ?>"
						     loading="lazy">
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		$dept_key = isset( $alert['dept_responsible'] ) ? $alert['dept_responsible'] : '';
		$vehicle  = isset( $alert['vehicle_number'] )  ? $alert['vehicle_number']  : '';
		$ir_key   = isset( $alert['internal_reason'] ) ? $alert['internal_reason'] : '';
		if ( $dept_key || $vehicle || $ir_key ) : ?>
			<aside class="nt-alerts-card__internal" aria-label="<?php esc_attr_e( 'Internal information', 'nt-alerts' ); ?>">
				<span class="nt-internal-badge"><?php esc_html_e( 'Internal', 'nt-alerts' ); ?></span>
				<?php if ( $dept_key && isset( $dept_labels[ $dept_key ] ) ) : ?>
					<span><strong><?php esc_html_e( 'Dept:', 'nt-alerts' ); ?></strong> <?php echo esc_html( $dept_labels[ $dept_key ] ); ?></span>
				<?php endif; ?>
				<?php if ( $vehicle ) : ?>
					<span><strong><?php esc_html_e( 'Vehicle:', 'nt-alerts' ); ?></strong> <?php echo esc_html( $vehicle ); ?></span>
				<?php endif; ?>
				<?php if ( $ir_key && isset( $internal_reason_labels[ $ir_key ] ) ) : ?>
					<span><strong><?php esc_html_e( 'Reason:', 'nt-alerts' ); ?></strong> <?php echo esc_html( $internal_reason_labels[ $ir_key ] ); ?></span>
				<?php endif; ?>
			</aside>
		<?php endif; ?>

		<p class="nt-alerts-card__times">
			<?php
			if ( '' !== $posted_by ) {
				printf(
					/* translators: 1: posted time, 2: author display name */
					esc_html__( 'Posted %1$s by %2$s', 'nt-alerts' ),
					'<time datetime="' . esc_attr( $posted ) . '">' . esc_html( nt_alerts_friendly_time( $posted ) ) . '</time>',
					'<span class="nt-alerts-card__author">' . esc_html( $posted_by ) . '</span>'
				);
			} else {
				printf(
					/* translators: %s: posted time */
					esc_html__( 'Posted %s', 'nt-alerts' ),
					'<time datetime="' . esc_attr( $posted ) . '">' . esc_html( nt_alerts_friendly_time( $posted ) ) . '</time>'
				);
			}
			?>
			<span class="nt-alerts-card__times-sep" data-role="times-sep" <?php echo $expires ? '' : 'hidden'; ?>> · </span>
			<span class="nt-alerts-card__expiry-display"
			      data-role="expiry-display"
			      <?php echo $expires ? '' : 'hidden'; ?>>
				<?php
				if ( $expires ) {
					printf(
						/* translators: %s: friendly expiry time */
						esc_html__( 'expires %s', 'nt-alerts' ),
						'<time datetime="' . esc_attr( $expires ) . '">' . esc_html( nt_alerts_friendly_time( $expires ) ) . '</time>'
					);
				}
				?>
			</span>
		</p>

		<?php if ( $show_actions ) :
			$edit_url = add_query_arg(
				array( 'page' => NT_Alerts_Admin::MENU_SLUG, 'action' => 'edit', 'id' => $id ),
				admin_url( 'admin.php' )
			);
			?>
			<div class="nt-alerts-card__actions">
				<a class="button button-large nt-alerts-edit-btn"
				   href="<?php echo esc_url( $edit_url ); ?>">
					<?php esc_html_e( 'Edit', 'nt-alerts' ); ?>
				</a>
				<button type="button"
				        class="button button-large nt-alerts-extend-btn"
				        data-alert-id="<?php echo esc_attr( $id ); ?>">
					<?php esc_html_e( 'Extend', 'nt-alerts' ); ?>
				</button>
				<button type="button"
				        class="button button-large button-primary nt-alerts-end-btn"
				        data-alert-id="<?php echo esc_attr( $id ); ?>">
					<?php esc_html_e( 'End now', 'nt-alerts' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</article>
	<?php
}

function nt_alerts_friendly_time( $iso ) {
	if ( ! $iso ) {
		return '';
	}
	$ts = strtotime( $iso );
	if ( false === $ts ) {
		return $iso;
	}
	$today = strtotime( 'today 00:00:00' );
	$tomorrow = strtotime( 'tomorrow 00:00:00' );

	if ( $ts >= $today && $ts < $tomorrow ) {
		return wp_date( get_option( 'time_format', 'g:ia' ), $ts );
	}
	return wp_date( get_option( 'date_format', 'M j' ) . ' ' . get_option( 'time_format', 'g:ia' ), $ts );
}
?>

<div class="wrap nt-alerts-wrap" data-nt-scope="<?php echo $can_see_all ? 'all' : 'mine'; ?>">

	<header class="nt-alerts-header">
		<h1 class="nt-alerts-header__title"><?php esc_html_e( 'Service Alerts', 'nt-alerts' ); ?></h1>
		<a class="button button-primary button-hero nt-alerts-new-btn" href="<?php echo esc_url( $new_url ); ?>">
			<span aria-hidden="true">+</span> <?php esc_html_e( 'New Alert', 'nt-alerts' ); ?>
		</a>
	</header>

	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible nt-alerts-flash" role="status">
			<p>
				<?php esc_html_e( 'Alert updated.', 'nt-alerts' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="nt-alerts-feedback" role="status" aria-live="polite"></div>

	<div class="nt-alerts-row">
		<section class="nt-alerts-section" data-section="active">
			<header class="nt-alerts-section__head">
				<h2><?php esc_html_e( 'Active now', 'nt-alerts' ); ?>
					<span class="nt-alerts-count"><?php echo (int) count( $active ); ?></span>
				</h2>
			</header>
			<?php if ( ! empty( $active ) ) : ?>
				<div class="nt-alerts-cards">
					<?php foreach ( $active as $alert ) { nt_alerts_render_card( $alert, true ); } ?>
				</div>
			<?php else : ?>
				<p class="nt-alerts-empty"><?php esc_html_e( 'No active alerts right now.', 'nt-alerts' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="nt-alerts-section" data-section="ongoing">
			<details <?php echo empty( $ongoing ) ? '' : 'open'; ?>>
				<summary>
					<h2 class="nt-alerts-section__summary-title">
						<?php esc_html_e( 'Ongoing (long-term)', 'nt-alerts' ); ?>
						<span class="nt-alerts-count"><?php echo (int) count( $ongoing ); ?></span>
					</h2>
				</summary>
				<?php if ( ! empty( $ongoing ) ) : ?>
					<div class="nt-alerts-cards">
						<?php foreach ( $ongoing as $alert ) { nt_alerts_render_card( $alert, true ); } ?>
					</div>
				<?php else : ?>
					<p class="nt-alerts-empty"><?php esc_html_e( 'No long-term alerts are active.', 'nt-alerts' ); ?></p>
				<?php endif; ?>
			</details>
		</section>
	</div>

	<dialog id="nt-extend-dialog" class="nt-extend-dialog" aria-labelledby="nt-extend-title">
		<form method="dialog" class="nt-extend-dialog__form">
			<h2 id="nt-extend-title" class="nt-extend-dialog__title">
				<?php esc_html_e( 'Extend alert', 'nt-alerts' ); ?>
			</h2>
			<p class="nt-extend-dialog__subtitle" data-role="subject"></p>
			<p class="nt-extend-dialog__current">
				<?php esc_html_e( 'Currently expires:', 'nt-alerts' ); ?>
				<strong data-role="current-expiry">—</strong>
			</p>
			<div class="nt-extend-dialog__choices" role="group" aria-label="<?php esc_attr_e( 'Choose how much to extend by', 'nt-alerts' ); ?>">
				<button type="button" class="button button-large nt-extend-pick" data-minutes="30">
					<?php esc_html_e( '+30 min', 'nt-alerts' ); ?>
				</button>
				<button type="button" class="button button-large nt-extend-pick" data-minutes="60">
					<?php esc_html_e( '+1 hour', 'nt-alerts' ); ?>
				</button>
				<button type="button" class="button button-large nt-extend-pick" data-minutes="120">
					<?php esc_html_e( '+2 hours', 'nt-alerts' ); ?>
				</button>
				<button type="button" class="button button-large nt-extend-pick" data-minutes="240">
					<?php esc_html_e( '+4 hours', 'nt-alerts' ); ?>
				</button>
			</div>
			<p class="nt-extend-dialog__error" role="alert" hidden></p>
			<div class="nt-extend-dialog__footer">
				<button type="submit" value="cancel" class="button">
					<?php esc_html_e( 'Cancel', 'nt-alerts' ); ?>
				</button>
			</div>
		</form>
	</dialog>

	<section class="nt-alerts-section" data-section="ended-today">
		<details>
			<summary>
				<h2 class="nt-alerts-section__summary-title">
					<?php esc_html_e( 'Ended today', 'nt-alerts' ); ?>
					<span class="nt-alerts-count"><?php echo (int) count( $ended_today ); ?></span>
				</h2>
			</summary>
			<?php if ( ! empty( $ended_today ) ) : ?>
				<div class="nt-alerts-cards">
					<?php foreach ( $ended_today as $alert ) { nt_alerts_render_card( $alert, false ); } ?>
				</div>
			<?php else : ?>
				<p class="nt-alerts-empty"><?php esc_html_e( 'No alerts have ended today.', 'nt-alerts' ); ?></p>
			<?php endif; ?>
		</details>
	</section>

</div>
