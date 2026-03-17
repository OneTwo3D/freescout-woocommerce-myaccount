<?php
/**
 * Template: My Account – Knowledge Base single article.
 *
 * Available variables:
 *   @var array      $article      Article object from the API.
 *   @var int        $article_id   Article ID.
 *   @var array|null $category     Parent category object (may be null).
 *   @var int        $category_id  Parent category ID (0 if unknown).
 *
 * Themes can override this file at: <theme>/fswa/myaccount/kb-article.php
 */

defined( 'ABSPATH' ) || exit;

$title   = esc_html( $article['name'] ?? $article['title'] ?? __( '(untitled)', 'fswa' ) );
$body    = $article['text'] ?? $article['body'] ?? '';
$cat_name = $category ? esc_html( $category['name'] ?? '' ) : '';
$updated  = ! empty( $article['updatedAt'] )
	? date_i18n( get_option( 'date_format' ), strtotime( $article['updatedAt'] ) )
	: '';
?>

<div class="fswa-kb fswa-kb-article">

	<!-- Breadcrumb -->
	<nav class="fswa-breadcrumb">
		<a href="<?php echo esc_url( FSWA_KnowledgeBase::home_url() ); ?>"><?php esc_html_e( 'Knowledge Base', 'fswa' ); ?></a>
		<?php if ( $category_id && $cat_name ) : ?>
			<span class="fswa-breadcrumb__sep" aria-hidden="true">&rsaquo;</span>
			<a href="<?php echo esc_url( FSWA_KnowledgeBase::category_url( $category_id ) ); ?>"><?php echo $cat_name; ?></a>
		<?php endif; ?>
		<span class="fswa-breadcrumb__sep" aria-hidden="true">&rsaquo;</span>
		<span><?php echo $title; ?></span>
	</nav>

	<!-- Article header -->
	<header class="fswa-kb-article__header">
		<h2 class="fswa-kb-article__title"><?php echo $title; ?></h2>
		<?php if ( $updated ) : ?>
			<p class="fswa-kb-article__meta">
				<?php
				/* translators: %s: date string */
				echo esc_html( sprintf( __( 'Last updated: %s', 'fswa' ), $updated ) );
				?>
			</p>
		<?php endif; ?>
	</header>

	<!-- Article body -->
	<div class="fswa-kb-article__body entry-content">
		<?php echo wp_kses_post( $body ); ?>
	</div>

	<!-- Footer navigation -->
	<footer class="fswa-kb-article__footer">
		<?php if ( $category_id ) : ?>
			<a href="<?php echo esc_url( FSWA_KnowledgeBase::category_url( $category_id ) ); ?>" class="button fswa-btn fswa-btn--sm">
				&larr; <?php echo $cat_name ? esc_html( $cat_name ) : esc_html__( 'Back to category', 'fswa' ); ?>
			</a>
		<?php else : ?>
			<a href="<?php echo esc_url( FSWA_KnowledgeBase::home_url() ); ?>" class="button fswa-btn fswa-btn--sm">
				&larr; <?php esc_html_e( 'Back to Knowledge Base', 'fswa' ); ?>
			</a>
		<?php endif; ?>

		<?php if ( get_option( 'fswa_allow_new_tickets', 1 ) ) : ?>
			<a href="<?php echo esc_url( FSWA_KnowledgeBase::ticket_url() ); ?>" class="button fswa-btn fswa-kb-article__contact-btn">
				<?php esc_html_e( 'Still need help? Open a ticket', 'fswa' ); ?>
			</a>
		<?php endif; ?>
	</footer>

</div>
