<?php
/**
 * Template: My Account – Knowledge Base search results.
 *
 * Available variables:
 *   @var string $query        The search query string.
 *   @var array  $articles     Matching article objects.
 *   @var int    $page         Current page number.
 *   @var int    $total_pages  Total pages.
 *
 * Themes can override this file at: <theme>/fswa/myaccount/kb-search.php
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="fswa-kb">

	<nav class="fswa-breadcrumb">
		<a href="<?php echo esc_url( FSWA_KnowledgeBase::home_url() ); ?>"><?php esc_html_e( 'Knowledge Base', 'fswa' ); ?></a>
		<span class="fswa-breadcrumb__sep" aria-hidden="true">&rsaquo;</span>
		<span><?php esc_html_e( 'Search Results', 'fswa' ); ?></span>
	</nav>

	<div class="fswa-kb__header">
		<h2 class="fswa-kb__title"><?php esc_html_e( 'Search Results', 'fswa' ); ?></h2>
	</div>

	<!-- Search bar -->
	<div class="fswa-kb-search">
		<form class="fswa-kb-search__form" action="<?php echo esc_url( FSWA_KnowledgeBase::search_url() ); ?>" method="get" role="search">
			<div class="fswa-kb-search__input-wrap">
				<input
					type="search"
					name="s"
					id="fswa-kb-search-input"
					class="fswa-kb-search__input"
					value="<?php echo esc_attr( $query ); ?>"
					placeholder="<?php esc_attr_e( 'Search the knowledge base…', 'fswa' ); ?>"
					autocomplete="off"
					aria-label="<?php esc_attr_e( 'Search the knowledge base', 'fswa' ); ?>"
				>
				<button type="submit" class="fswa-kb-search__btn" aria-label="<?php esc_attr_e( 'Search', 'fswa' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				</button>
			</div>
			<ul class="fswa-kb-search__suggestions" id="fswa-kb-suggestions" role="listbox" aria-label="<?php esc_attr_e( 'Search suggestions', 'fswa' ); ?>" hidden></ul>
		</form>
	</div>

	<?php if ( '' === $query ) : ?>
		<p class="fswa-kb__empty"><?php esc_html_e( 'Enter a search term above.', 'fswa' ); ?></p>
	<?php elseif ( empty( $articles ) ) : ?>
		<p class="fswa-kb__empty">
			<?php echo esc_html(
				/* translators: %s: search query */
				sprintf( __( 'No articles found for "%s".', 'fswa' ), $query )
			); ?>
		</p>
	<?php else : ?>
		<p class="fswa-kb__result-count">
			<?php echo esc_html(
				/* translators: %s: search query */
				sprintf( __( 'Results for "%s":', 'fswa' ), $query )
			); ?>
		</p>

		<ul class="fswa-kb-article-list">
			<?php foreach ( $articles as $article ) :
				$art_id    = (int) ( $article['id'] ?? 0 );
				$art_title = esc_html( $article['name'] ?? $article['title'] ?? __( '(untitled)', 'fswa' ) );
				$art_url   = FSWA_KnowledgeBase::article_url( $art_id );
				$excerpt   = wp_trim_words( wp_strip_all_tags( $article['text'] ?? '' ), 20 );
			?>
			<li class="fswa-kb-article-list__item fswa-kb-article-list__item--search">
				<a href="<?php echo esc_url( $art_url ); ?>" class="fswa-kb-article-list__link">
					<svg class="fswa-kb-article-list__icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
					<?php echo $art_title; ?>
				</a>
				<?php if ( $excerpt ) : ?>
					<p class="fswa-kb-article-list__excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $total_pages > 1 ) : ?>
		<div class="fswa-pagination">
			<?php
			$base_url = FSWA_KnowledgeBase::search_url( $query );
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
