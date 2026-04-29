<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $nt_archive */
$rows         = isset( $nt_archive['rows'] )       ? $nt_archive['rows']       : array();
$filters      = isset( $nt_archive['filters'] )    ? $nt_archive['filters']    : array();
$total        = isset( $nt_archive['total'] )      ? (int) $nt_archive['total']: 0;
$pages        = isset( $nt_archive['pages'] )      ? (int) $nt_archive['pages']: 1;
$current_page = isset( $nt_archive['current_page'] ) ? (int) $nt_archive['current_page'] : 1;
$page_url     = isset( $nt_archive['page_url'] )   ? $nt_archive['page_url']   : '';
$authors      = isset( $nt_archive['authors'] )    ? $nt_archive['authors']    : array();
$categories   = isset( $nt_archive['categories'] ) ? $nt_archive['categories'] : array();

$status_choices = array(
	'archived' => __( 'Archived',   'nt-alerts' ),
	'expired'  => __( 'Expired',    'nt-alerts' ),
	'active'   => __( 'Active',     'nt-alerts' ),
	'any'      => __( 'Any status', 'nt-alerts' ),
);

$category_labels = NT_Alerts_Admin::category_labels();
$reason_labels   = NT_Alerts_Admin::reason_labels();
$dept_labels     = NT_Alerts_Admin::department_labels();

function nt_alerts_archive_friendly( $iso ) {
	if ( ! $iso ) {
		return '—';
	}
	$ts = strtotime( $iso );
	if ( false === $ts ) {
		return $iso;
	}
	return wp_date( get_option( 'date_format', 'M j, Y' ) . ' ' . get_option( 'time_format', 'g:ia' ), $ts );
}
?>

<div class="wrap nt-alerts-wrap nt-alerts-archive">

	<h1><?php esc_html_e( 'Service alerts archive', 'nt-alerts' ); ?></h1>

	<?php
	$export_action = admin_url( 'admin-post.php' );
	$export_nonce  = wp_create_nonce( 'nt_alerts_export_csv' );
	?>
	<div class="nt-archive-export">
		<span class="nt-archive-export__label"><?php esc_html_e( 'Quick export:', 'nt-alerts' ); ?></span>
		<?php foreach ( array(
			'week'       => __( 'This week', 'nt-alerts' ),
			'last_week'  => __( 'Last week', 'nt-alerts' ),
			'month'      => __( 'This month', 'nt-alerts' ),
			'last_month' => __( 'Last month', 'nt-alerts' ),
		) as $range_key => $range_label ) :
			$url = add_query_arg(
				array(
					'action'   => 'nt_alerts_export_csv',
					'range'    => $range_key,
					'_wpnonce' => $export_nonce,
				),
				$export_action
			);
			?>
			<a class="button" href="<?php echo esc_url( $url ); ?>">
				<?php echo esc_html( $range_label ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<form method="get" class="nt-archive-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( NT_Alerts_Archive::PAGE_SLUG ); ?>">

		<div class="nt-archive-filters__row">
			<label>
				<span class="nt-archive-filters__label"><?php esc_html_e( 'Status', 'nt-alerts' ); ?></span>
				<select name="status">
					<?php foreach ( $status_choices as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filters['status'], $val ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label>
				<span class="nt-archive-filters__label"><?php esc_html_e( 'Category', 'nt-alerts' ); ?></span>
				<select name="category">
					<option value=""><?php esc_html_e( 'Any', 'nt-alerts' ); ?></option>
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $filters['category'], $cat ); ?>>
							<?php echo esc_html( isset( $category_labels[ $cat ] ) ? $category_labels[ $cat ] : $cat ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label>
				<span class="nt-archive-filters__label"><?php esc_html_e( 'Posted by', 'nt-alerts' ); ?></span>
				<select name="author">
					<option value="0"><?php esc_html_e( 'Anyone', 'nt-alerts' ); ?></option>
					<?php foreach ( $authors as $user_id => $name ) : ?>
						<option value="<?php echo esc_attr( $user_id ); ?>" <?php selected( (int) $filters['author'], (int) $user_id ); ?>>
							<?php echo esc_html( $name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

		<div class="nt-archive-filters__row">
			<label>
				<span class="nt-archive-filters__label"><?php esc_html_e( 'From', 'nt-alerts' ); ?></span>
				<input type="date" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>">
			</label>
			<label>
				<span class="nt-archive-filters__label"><?php esc_html_e( 'To', 'nt-alerts' ); ?></span>
				<input type="date" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>">
			</label>
			<label class="nt-archive-filters__search">
				<span class="nt-archive-filters__label"><?php esc_html_e( 'Search title', 'nt-alerts' ); ?></span>
				<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Geneva', 'nt-alerts' ); ?>">
			</label>
		</div>

		<div class="nt-archive-filters__actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply filters', 'nt-alerts' ); ?></button>
			<button type="submit" form="nt-archive-export-form" class="button">
				<?php esc_html_e( 'Export filtered (CSV)', 'nt-alerts' ); ?>
			</button>
			<a class="button-link" href="<?php echo esc_url( $page_url ); ?>"><?php esc_html_e( 'Reset', 'nt-alerts' ); ?></a>
		</div>
	</form>

	<form id="nt-archive-export-form" method="get" action="<?php echo esc_url( $export_action ); ?>" style="display:none;">
		<input type="hidden" name="action" value="nt_alerts_export_csv">
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $export_nonce ); ?>">
		<input type="hidden" name="status"   value="<?php echo esc_attr( $filters['status'] ); ?>">
		<input type="hidden" name="category" value="<?php echo esc_attr( $filters['category'] ); ?>">
		<input type="hidden" name="author"   value="<?php echo esc_attr( $filters['author'] ); ?>">
		<input type="hidden" name="from"     value="<?php echo esc_attr( $filters['from'] ); ?>">
		<input type="hidden" name="to"       value="<?php echo esc_attr( $filters['to'] ); ?>">
		<input type="hidden" name="s"        value="<?php echo esc_attr( $filters['search'] ); ?>">
	</form>

	<p class="nt-archive-summary">
		<?php
		printf(
			/* translators: %d: total result count */
			esc_html( _n( '%d alert found.', '%d alerts found.', $total, 'nt-alerts' ) ),
			$total
		);
		?>
	</p>

	<?php if ( empty( $rows ) ) : ?>
		<div class="nt-alerts-placeholder"><?php esc_html_e( 'No alerts match these filters.', 'nt-alerts' ); ?></div>
	<?php else : ?>
		<table class="widefat striped nt-archive-table" role="grid">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Title', 'nt-alerts' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Category', 'nt-alerts' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Reason', 'nt-alerts' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Severity', 'nt-alerts' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Routes', 'nt-alerts' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Dept.', 'nt-alerts' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Posted by', 'nt-alerts' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Posted', 'nt-alerts' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Ended', 'nt-alerts' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'nt-alerts' ); ?></th>
					<th scope="col" class="column-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'nt-alerts' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) :
				$alert  = $row['alert'];
				$post   = $row['post'];
				$cat    = isset( $alert['category'] ) ? $alert['category'] : '';
				$cat_label = isset( $category_labels[ $cat ] ) ? $category_labels[ $cat ] : $cat;
				$status = (string) get_post_meta( $post->ID, 'status', true );
				$status_label = isset( $status_choices[ $status ] ) ? $status_choices[ $status ] : ucfirst( $status );
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $alert['title'] ); ?></strong>
						<?php if ( ! empty( $alert['description'] ) ) : ?>
							<div class="nt-archive-table__desc"><?php echo esc_html( wp_trim_words( $alert['description'], 18, '…' ) ); ?></div>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $cat_label ); ?></td>
					<td>
						<?php
						$rk = isset( $alert['reason'] ) ? $alert['reason'] : '';
						echo esc_html( $rk && isset( $reason_labels[ $rk ] ) ? $reason_labels[ $rk ] : '—' );
						?>
					</td>
					<td><?php echo esc_html( ucfirst( isset( $alert['severity'] ) ? $alert['severity'] : '' ) ); ?></td>
					<td><?php echo esc_html( implode( ', ', isset( $alert['routes'] ) ? $alert['routes'] : array() ) ); ?></td>
					<td>
						<?php
						$dk = isset( $alert['dept_responsible'] ) ? $alert['dept_responsible'] : '';
						$dlabel = $dk && isset( $dept_labels[ $dk ] ) ? $dept_labels[ $dk ] : '';
						$veh    = isset( $alert['vehicle_number'] ) ? $alert['vehicle_number'] : '';
						if ( $dlabel || $veh ) {
							echo esc_html( trim( $dlabel . ( $veh ? ' #' . $veh : '' ) ) );
						} else {
							echo '—';
						}
						?>
					</td>
					<td><?php echo esc_html( $row['author_name'] ); ?></td>
					<td><?php echo esc_html( nt_alerts_archive_friendly( $alert['posted_at'] ) ); ?></td>
					<td><?php echo esc_html( nt_alerts_archive_friendly( $alert['expires_at'] ) ); ?></td>
					<td>
						<span class="nt-archive-pill nt-archive-pill--<?php echo esc_attr( $status ); ?>">
							<?php echo esc_html( $status_label ); ?>
						</span>
					</td>
					<td class="column-actions">
						<?php
						$edit_url = add_query_arg(
							array( 'page' => NT_Alerts_Admin::MENU_SLUG, 'action' => 'edit', 'id' => $post->ID ),
							admin_url( 'admin.php' )
						);
						if ( current_user_can( 'edit_post', $post->ID ) ) :
							?>
							<a class="button-link" href="<?php echo esc_url( $edit_url ); ?>">
								<?php esc_html_e( 'Edit', 'nt-alerts' ); ?>
							</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) :
			$pagination = paginate_links( array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'current'   => $current_page,
				'total'     => $pages,
				'prev_text' => __( '« Prev', 'nt-alerts' ),
				'next_text' => __( 'Next »', 'nt-alerts' ),
				'type'      => 'array',
			) );
			if ( $pagination ) : ?>
				<nav class="nt-archive-pagination" aria-label="<?php esc_attr_e( 'Archive pagination', 'nt-alerts' ); ?>">
					<ul>
						<?php foreach ( $pagination as $link ) : ?>
							<li><?php echo $link; // Trusted output from paginate_links. ?></li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endif;
		endif; ?>
	<?php endif; ?>
</div>
