<?php
/**
 * Template: My Account – Knowledge Base category (article list).
 *
 * Available variables:
 *   @var array $category     Category object from the API.
 *   @var array $articles     Array of article objects for this category.
 *   @var int   $page         Current page number.
 *   @var int   $total_pages  Total pages.
 *   @var int   $category_id  Category ID.
 *
 * Themes can override this file at: <theme>/fswa/myaccount/kb-category.php
 */

defined( 'ABSPATH' ) || exit;

$cat_name = esc_html( $category['name'] ?? __( 'Category', 'fswa' ) );
$cat_desc = $category['text'] ?? $category['description'] ?? '';
?>

<div class="fswa-kb">

	<nav class="fswa-breadcrumb">
		<a href="<?php echo esc_url( FSWA_KnowledgeBase::home_url() ); ?>"><?php esc_html_e( 'Knowledge Base', 'fswa' ); ?></a>
		<span class="fswa-breadcrumb__sep" aria-hidden="true">&rsaquo;</span>
		<span><?php echo $cat_name; ?></span>
	</nav>

	<div class="fswa-kb__header">
		<h2 class="fswa-kb__title"><?php echo $cat_name; ?></h2>
	</div>

	<?php if ( $cat_desc ) : ?>
		<p class="fswa-kb__desc"><?php echo esc_html( $cat_desc ); ?></p>
	<?php endif; ?>

	<?php if ( empty( $articles ) ) : ?>
		<p class="fswa-kb__empty"><?php esc_html_e( 'No articles in this category yet.', 'fswa' ); ?></p>
	<?php else : ?>
		<ul class="fswa-kb-article-list">
			<?php foreach ( $articles as $article ) :
				$art_id    = (int) ( $article['id'] ?? 0 );
				$art_title = esc_html( $article['name'] ?? $article['title'] ?? __( '(untitled)', 'fswa' ) );
				$art_url   = FSWA_KnowledgeBase::article_url( $category_id, $art_id );
				$views     = (int) ( $article['viewsCount'] ?? 0 );
			?>
			<li class="fswa-kb-article-list__item">
				<a href="<?php echo esc_url( $art_url ); ?>" class="fswa-kb-article-list__link">
					<svg class="fswa-kb-article-list__icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
					<?php echo $art_title; ?>
				</a>
				<?php if ( $views ) : ?>
					<span class="fswa-kb-article-list__views">
						<?php echo esc_html(
							/* translators: %d: view count */
							sprintf( _n( '%d view', '%d views', $views, 'fswa' ), $views )
						); ?>
					</span>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $total_pages > 1 ) : ?>
		<div class="fswa-pagination">
			<?php
			$base_url = FSWA_KnowledgeBase::category_url( $category_id );
			echo paginate_links( [
				'base'      => add_query_arg( 'paged', '%#%', $base_url ),
				'format'    => '?paged=%#%',
				'current'   => $page,
				'total'     => $total_pages,
				'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'fswa' ),
				'next_text' => esc_html__( 'Next', 'fswa' ) . ' &raquo;',
			] );
			?>
		</div>
		<?php endif; ?>
	<?php endif; ?>

</div>
