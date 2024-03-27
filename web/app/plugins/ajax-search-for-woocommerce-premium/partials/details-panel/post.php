<?php
/**
 * The Template for displaying post info in Details Panel
 *
 * This template can be overridden by copying it to yourtheme/fibosearch/details-panel/post.php.
 */

use DgoraWcas\Helpers;

// Exit if accessed directly
if ( ! defined( 'DGWT_WCAS_FILE' ) ) {
	exit;
}

?>
<div class="dgwt-wcas-details-inner dgwt-wcas-details-page dgwt-wcas-details-cpt">

	<?php do_action( 'dgwt/wcas/details_panel/post/container_before', $vars ); ?>

	<div class="dgwt-wcas-product-details">

		<?php do_action( 'dgwt/wcas/details_panel/post/image_before', $vars ); ?>
		<a class="dgwt-wcas-pd-details-post" href="<?php echo esc_url( $vars->link ); ?>" title="<?php echo wp_strip_all_tags( $vars->title ); ?>">
			<?php if ( ! empty( $vars->imageSrc ) ): ?>
				<div class="dgwt-wcas-details-main-image">
					<img
						src="<?php echo esc_url( $vars->imageSrc ); ?>"
						<?php echo ( ! empty( $vars->imageSrcset ) && ! empty( $vars->imageSizes ) ) ? 'srcset="' . esc_attr( $vars->imageSrcset ) . '"' : '' ?>
						<?php echo ( ! empty( $vars->imageSrcset ) && ! empty( $vars->imageSizes ) ) ? 'sizes="' . esc_attr( $vars->imageSizes ) . '"' : '' ?>
						alt="<?php echo wp_strip_all_tags( $vars->title ); ?>"
					>
				</div>
			<?php endif; ?>
		</a>
		<?php do_action( 'dgwt/wcas/details_panel/post/image_after', $vars ); ?>

		<div class="dgwt-wcas-details-space">
			<a class="dgwt-wcas-details-post-title" href="<?php echo esc_url( $vars->link ); ?>" title="<?php echo wp_strip_all_tags( $vars->title ); ?>">
				<?php echo esc_attr( $vars->title ); ?>
			</a>

			<?php if ( ! empty( $vars->desc ) ): ?>
				<div class="dgwt-wcas-details-desc">
					<?php echo wp_kses_post( $vars->desc ); ?>
				</div>
			<?php endif; ?>

			<div class="dgwt-wcas-details-hr"></div>

			<a class="dgwt-wcas-product-details-readmore" href="<?php echo esc_url( $vars->link ); ?>"><?php echo Helpers::getLabel( 'read_more' ); ?></a>
		</div>

	</div>

	<?php do_action( 'dgwt/wcas/details_panel/post/container_after', $vars ); ?>

</div>

