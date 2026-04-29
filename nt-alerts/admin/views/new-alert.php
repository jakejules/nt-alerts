<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $nt_alerts_routes */
/** @var array|null $nt_alerts_editing */
$route_groups  = isset( $nt_alerts_routes ) && is_array( $nt_alerts_routes ) ? $nt_alerts_routes : array();
$editing       = isset( $nt_alerts_editing ) && is_array( $nt_alerts_editing ) ? $nt_alerts_editing : null;
$is_edit       = (bool) $editing;
$dashboard_url = admin_url( 'admin.php?page=' . NT_Alerts_Admin::MENU_SLUG );

$page_heading  = $is_edit ? __( 'Edit service alert', 'nt-alerts' ) : __( 'New service alert', 'nt-alerts' );
$submit_label  = $is_edit ? __( 'Save changes', 'nt-alerts' ) : __( 'Post Alert', 'nt-alerts' );
?>

<div class="wrap nt-alerts-wrap nt-alerts-new">

	<header class="nt-alerts-header">
		<h1 class="nt-alerts-header__title"><?php echo esc_html( $page_heading ); ?></h1>
		<a class="button nt-alerts-back-btn" href="<?php echo esc_url( $dashboard_url ); ?>">
			<?php esc_html_e( '← Dashboard', 'nt-alerts' ); ?>
		</a>
	</header>

	<div class="nt-alerts-feedback" role="status" aria-live="polite"></div>

	<?php if ( $is_edit ) : ?>
		<script type="application/json" id="nt-alerts-editing-data"><?php echo wp_json_encode( $editing ); ?></script>
	<?php endif; ?>

	<form id="nt-new-alert-form" class="nt-new-form" novalidate>

		<fieldset class="nt-new-form__section">
			<legend id="nt-cat-legend" class="nt-new-form__legend"><?php esc_html_e( '1. What happened?', 'nt-alerts' ); ?></legend>
			<div class="nt-new-form__choices" role="radiogroup" aria-labelledby="nt-cat-legend">
				<?php
				$categories = NT_Alerts_Admin::category_labels();
				foreach ( $categories as $value => $label ) :
					?>
					<button type="button"
					        class="nt-choice nt-choice--category"
					        role="radio"
					        aria-checked="false"
					        data-category="<?php echo esc_attr( $value ); ?>">
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<fieldset class="nt-new-form__section">
			<legend id="nt-routes-legend" class="nt-new-form__legend"><?php esc_html_e( '2. Which routes?', 'nt-alerts' ); ?></legend>

			<div class="nt-new-form__route-controls">
				<button type="button" class="button nt-route-select-all" data-action="select-all">
					<?php esc_html_e( 'Select all routes', 'nt-alerts' ); ?>
				</button>
				<button type="button" class="button nt-route-clear" data-action="clear">
					<?php esc_html_e( 'Clear', 'nt-alerts' ); ?>
				</button>
			</div>

			<?php if ( empty( $route_groups ) ) : ?>
				<p class="nt-alerts-placeholder">
					<?php esc_html_e( 'No routes configured yet. Ask an administrator to populate the routes list in Settings.', 'nt-alerts' ); ?>
				</p>
			<?php else : ?>
				<?php foreach ( $route_groups as $group_index => $group ) :
					if ( ! is_array( $group ) || empty( $group['routes'] ) ) {
						continue;
					}
					$group_label = isset( $group['group'] ) ? (string) $group['group'] : sprintf( 'Group %d', $group_index + 1 );
					$group_slug  = sanitize_title( $group_label );
					?>
					<div class="nt-route-group" data-group="<?php echo esc_attr( $group_slug ); ?>">
						<div class="nt-route-group__head">
							<h3 class="nt-route-group__title"><?php echo esc_html( $group_label ); ?></h3>
							<button type="button" class="button-link nt-route-group-toggle"
							        data-group-toggle="<?php echo esc_attr( $group_slug ); ?>">
								<?php
								printf(
									/* translators: %s: group label */
									esc_html__( 'Select all in %s', 'nt-alerts' ),
									esc_html( $group_label )
								);
								?>
							</button>
						</div>
						<div class="nt-route-group__grid">
							<?php foreach ( $group['routes'] as $route ) :
								if ( empty( $route['id'] ) ) {
									continue;
								}
								$rid    = (string) $route['id'];
								$label  = isset( $route['label'] ) ? (string) $route['label'] : $rid;
								$color  = isset( $route['color'] ) ? (string) $route['color'] : '';
								?>
								<button type="button"
								        class="nt-route-chip"
								        data-route-id="<?php echo esc_attr( $rid ); ?>"
								        data-route-group="<?php echo esc_attr( $group_slug ); ?>"
								        aria-pressed="false"
								        <?php if ( $color ) : ?>
								          style="--nt-route-color: <?php echo esc_attr( $color ); ?>"
								        <?php endif; ?>>
									<span class="nt-route-chip__id"><?php echo esc_html( $rid ); ?></span>
									<span class="nt-route-chip__label"><?php echo esc_html( $label ); ?></span>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</fieldset>

		<fieldset class="nt-new-form__section">
			<legend id="nt-duration-legend" class="nt-new-form__legend"><?php esc_html_e( '3. How long?', 'nt-alerts' ); ?></legend>
			<div class="nt-new-form__choices" role="radiogroup" aria-labelledby="nt-duration-legend">
				<?php
				$durations = array(
					'1h'        => array( 'label' => __( '1 hour',     'nt-alerts' ), 'default' => false ),
					'2h'        => array( 'label' => __( '2 hours',    'nt-alerts' ), 'default' => true  ),
					'4h'        => array( 'label' => __( '4 hours',    'nt-alerts' ), 'default' => false ),
					'rest_of_day' => array( 'label' => __( 'Rest of day', 'nt-alerts' ), 'default' => false ),
					'long_term' => array( 'label' => __( 'Long-term',  'nt-alerts' ), 'default' => false ),
					'custom'    => array( 'label' => __( 'Custom…',    'nt-alerts' ), 'default' => false ),
				);
				foreach ( $durations as $value => $conf ) : ?>
					<button type="button"
					        class="nt-choice nt-choice--duration"
					        role="radio"
					        aria-checked="<?php echo $conf['default'] ? 'true' : 'false'; ?>"
					        data-duration="<?php echo esc_attr( $value ); ?>">
						<?php echo esc_html( $conf['label'] ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="nt-new-form__duration-detail" data-role="long-term" hidden>
				<label for="nt-long-term-end"><?php esc_html_e( 'Expected end date (optional)', 'nt-alerts' ); ?></label>
				<input type="date" id="nt-long-term-end" name="long_term_end">
			</div>

			<div class="nt-new-form__duration-detail" data-role="custom" hidden>
				<label for="nt-custom-end"><?php esc_html_e( 'Ends at', 'nt-alerts' ); ?></label>
				<input type="datetime-local" id="nt-custom-end" name="custom_end">
			</div>
		</fieldset>

		<fieldset class="nt-new-form__section">
			<legend id="nt-reason-legend" class="nt-new-form__legend"><?php esc_html_e( '4. Reason (optional)', 'nt-alerts' ); ?></legend>
			<div class="nt-new-form__choices" role="radiogroup" aria-labelledby="nt-reason-legend">
				<button type="button"
				        class="nt-choice nt-choice--reason"
				        role="radio"
				        aria-checked="true"
				        data-reason="">
					<?php esc_html_e( '— None —', 'nt-alerts' ); ?>
				</button>
				<?php foreach ( NT_Alerts_Admin::reason_labels() as $value => $label ) : ?>
					<button type="button"
					        class="nt-choice nt-choice--reason"
					        role="radio"
					        aria-checked="false"
					        data-reason="<?php echo esc_attr( $value ); ?>">
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<p class="nt-new-form__hint">
				<?php esc_html_e( 'Adds context for riders, e.g. "Detour due to construction".', 'nt-alerts' ); ?>
			</p>
		</fieldset>

		<fieldset class="nt-new-form__section">
			<legend class="nt-new-form__legend"><?php esc_html_e( 'Title', 'nt-alerts' ); ?></legend>
			<label class="screen-reader-text" for="nt-alert-title"><?php esc_html_e( 'Alert title', 'nt-alerts' ); ?></label>
			<input type="text"
			       id="nt-alert-title"
			       name="title"
			       class="nt-new-form__title"
			       placeholder="<?php esc_attr_e( 'Auto-filled once you pick a category', 'nt-alerts' ); ?>"
			       autocomplete="off">
			<p class="nt-new-form__hint">
				<?php esc_html_e( 'Auto-filled from the category and routes. Edit if you want a specific wording.', 'nt-alerts' ); ?>
			</p>
		</fieldset>

		<fieldset class="nt-new-form__section">
			<legend class="nt-new-form__legend"><?php esc_html_e( '5. Stops affected (optional)', 'nt-alerts' ); ?></legend>
			<p class="nt-new-form__hint">
				<?php esc_html_e( 'Type a street, intersection, or stop code to find stops. Tap to add.', 'nt-alerts' ); ?>
			</p>

			<?php
			$stops_pickers = array(
				'closed' => array(
					'label'       => __( 'Closed stops', 'nt-alerts' ),
					'placeholder' => __( 'Search closed stops…', 'nt-alerts' ),
				),
				'alternate' => array(
					'label'       => __( 'Use these stops instead', 'nt-alerts' ),
					'placeholder' => __( 'Search alternate stops…', 'nt-alerts' ),
				),
			);
			foreach ( $stops_pickers as $key => $cfg ) :
				$input_id = 'nt-stops-' . esc_attr( $key );
				$listbox_id = 'nt-stops-' . esc_attr( $key ) . '-list';
				?>
				<div class="nt-new-form__field nt-stops-picker" data-stops-picker="<?php echo esc_attr( $key ); ?>">
					<label for="<?php echo esc_attr( $input_id ); ?>" class="nt-new-form__field-label">
						<?php echo esc_html( $cfg['label'] ); ?>
					</label>
					<div class="nt-stops-picker__chips" data-role="chips" aria-live="polite"></div>
					<div class="nt-stops-picker__combobox" role="combobox"
					     aria-haspopup="listbox"
					     aria-expanded="false"
					     aria-owns="<?php echo esc_attr( $listbox_id ); ?>">
						<input type="text"
						       id="<?php echo esc_attr( $input_id ); ?>"
						       class="nt-stops-picker__input"
						       autocomplete="off"
						       placeholder="<?php echo esc_attr( $cfg['placeholder'] ); ?>"
						       aria-controls="<?php echo esc_attr( $listbox_id ); ?>"
						       aria-autocomplete="list">
						<ul id="<?php echo esc_attr( $listbox_id ); ?>"
						    class="nt-stops-picker__list"
						    role="listbox"
						    hidden></ul>
					</div>
				</div>
			<?php endforeach; ?>
		</fieldset>

		<fieldset class="nt-new-form__section">
			<legend class="nt-new-form__legend"><?php esc_html_e( '6. Anything else?', 'nt-alerts' ); ?></legend>
			<label class="screen-reader-text" for="nt-alert-description"><?php esc_html_e( 'Optional description', 'nt-alerts' ); ?></label>
			<textarea id="nt-alert-description"
			          name="description"
			          rows="3"
			          placeholder="<?php esc_attr_e( 'Add details if helpful (optional)', 'nt-alerts' ); ?>"></textarea>
		</fieldset>

		<fieldset class="nt-new-form__section">
			<legend class="nt-new-form__legend"><?php esc_html_e( '7. Pictures (optional)', 'nt-alerts' ); ?></legend>
			<p class="nt-new-form__hint">
				<?php esc_html_e( 'Up to 3 images. Useful for detour maps. Riders see them on the public alerts feed.', 'nt-alerts' ); ?>
			</p>
			<div class="nt-images-picker" data-role="images-picker">
				<div class="nt-images-picker__previews" data-role="image-previews"></div>
				<input type="file"
				       class="nt-images-picker__file"
				       data-role="image-file-input"
				       accept="image/*"
				       multiple
				       hidden>
				<button type="button" class="button nt-images-picker__btn" data-role="open-image-picker">
					<?php esc_html_e( 'Choose images from your computer', 'nt-alerts' ); ?>
				</button>
				<p class="nt-images-picker__status" role="status" aria-live="polite" data-role="image-status"></p>
			</div>
		</fieldset>

		<fieldset class="nt-new-form__section nt-new-form__section--internal">
			<legend class="nt-new-form__legend">
				<?php esc_html_e( 'Internal only', 'nt-alerts' ); ?>
				<span class="nt-internal-badge"><?php esc_html_e( 'Not shown to riders', 'nt-alerts' ); ?></span>
			</legend>

			<p class="nt-new-form__hint">
				<?php esc_html_e( 'These fields stay inside Niagara Transit. They are not exposed in the public API or the embed.', 'nt-alerts' ); ?>
			</p>

			<div class="nt-new-form__field">
				<span id="nt-dept-legend" class="nt-new-form__field-label"><?php esc_html_e( 'Department responsible', 'nt-alerts' ); ?></span>
				<div class="nt-new-form__choices nt-new-form__choices--small" role="radiogroup" aria-labelledby="nt-dept-legend">
					<button type="button"
					        class="nt-choice nt-choice--dept"
					        role="radio"
					        aria-checked="true"
					        data-dept="">
						<?php esc_html_e( '— None —', 'nt-alerts' ); ?>
					</button>
					<?php foreach ( NT_Alerts_Admin::department_labels() as $value => $label ) : ?>
						<button type="button"
						        class="nt-choice nt-choice--dept"
						        role="radio"
						        aria-checked="false"
						        data-dept="<?php echo esc_attr( $value ); ?>">
							<?php echo esc_html( $label ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="nt-new-form__maintenance" data-role="maintenance" hidden>
				<div class="nt-new-form__field">
					<label for="nt-vehicle-number"><?php esc_html_e( 'Vehicle number', 'nt-alerts' ); ?></label>
					<input type="text"
					       id="nt-vehicle-number"
					       name="vehicle_number"
					       class="nt-new-form__title"
					       placeholder="<?php esc_attr_e( 'e.g. 504', 'nt-alerts' ); ?>"
					       autocomplete="off"
					       inputmode="numeric">
				</div>

				<div class="nt-new-form__field">
					<span id="nt-internal-reason-legend" class="nt-new-form__field-label"><?php esc_html_e( 'Maintenance reason', 'nt-alerts' ); ?></span>
					<div class="nt-new-form__choices nt-new-form__choices--small" role="radiogroup" aria-labelledby="nt-internal-reason-legend">
						<button type="button"
						        class="nt-choice nt-choice--internal-reason"
						        role="radio"
						        aria-checked="true"
						        data-internal-reason="">
							<?php esc_html_e( '— None —', 'nt-alerts' ); ?>
						</button>
						<?php foreach ( NT_Alerts_Admin::internal_reason_labels() as $value => $label ) : ?>
							<button type="button"
							        class="nt-choice nt-choice--internal-reason"
							        role="radio"
							        aria-checked="false"
							        data-internal-reason="<?php echo esc_attr( $value ); ?>">
								<?php echo esc_html( $label ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</fieldset>

		<div id="nt-new-form-errors" class="nt-new-form__errors" role="alert" aria-live="assertive" hidden></div>

		<button type="submit"
		        class="button button-primary button-hero nt-new-form__submit"
		        aria-describedby="nt-new-form-errors">
			<?php echo esc_html( $submit_label ); ?>
		</button>
	</form>

	<section id="nt-confirmation" class="nt-confirmation" hidden aria-live="polite">
		<h2 class="nt-confirmation__title"><?php esc_html_e( 'Alert posted', 'nt-alerts' ); ?></h2>
		<dl class="nt-confirmation__details">
			<dt><?php esc_html_e( 'Title', 'nt-alerts' ); ?></dt>
			<dd data-role="title"></dd>
			<dt><?php esc_html_e( 'Routes', 'nt-alerts' ); ?></dt>
			<dd data-role="routes"></dd>
			<dt><?php esc_html_e( 'Expires', 'nt-alerts' ); ?></dt>
			<dd data-role="expires"></dd>
		</dl>
		<div class="nt-confirmation__actions">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . NT_Alerts_Admin::MENU_SLUG . '&action=new' ) ); ?>">
				<?php esc_html_e( 'Post another', 'nt-alerts' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( $dashboard_url ); ?>">
				<?php esc_html_e( 'Back to dashboard', 'nt-alerts' ); ?>
			</a>
		</div>
	</section>

</div>
