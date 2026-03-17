<?php
/**
 * Template: My Account – Knowledge Base home (category grid).
 *
 * Available variables:
 *   @var array $categories  Array of category objects from the FreeScout Docs API.
 *
 * Themes can override this file at: <theme>/fswa/myaccount/kb-home.php
 */

defined( 'ABSPATH' ) || exit;

$show_search = (bool) get_option( 'fswa_kb_show_search', 1 );
?>

<div class="fswa-kb">

	<div class="fswa-kb__header">
		<h2 class="fswa-kb__title"><?php echo esc_html( get_option( 'fswa_kb_menu_label', __( 'Knowledge Base', 'fswa' ) ) ); ?></h2>
	</div>

	<?php if ( $show_search ) : ?>
	<div class="fswa-kb-search" id="fswa-kb-search-wrap">
		<form class="fswa-kb-search__form" action="<?php echo esc_url( FSWA_KnowledgeBase::search_form_action() ); ?>" method="get" role="search">
			<?php FSWA_KnowledgeBase::search_form_extra_fields(); ?>
			<div class="fswa-kb-search__input-wrap">
				<input
					type="search"
					name="s"
					id="fswa-kb-search-input"
					class="fswa-kb-search__input"
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
	<?php endif; ?>

	<?php if ( empty( $categories ) ) : ?>
		<p class="fswa-kb__empty"><?php esc_html_e( 'No knowledge base categories found.', 'fswa' ); ?></p>
	<?php else : ?>
		<div class="fswa-kb-categories">
			<?php foreach ( $categories as $cat ) :
				$cat_id    = (int) ( $cat['id'] ?? 0 );
				$cat_name  = esc_html( $cat['name'] ?? '' );
				$cat_desc  = esc_html( $cat['text'] ?? $cat['description'] ?? '' );
				$art_count = (int) ( $cat['articlesCount'] ?? 0 );
				$cat_url   = FSWA_KnowledgeBase::category_url( $cat_id );
			?>
			<a href="<?php echo esc_url( $cat_url ); ?>" class="fswa-kb-category-card">
				<div class="fswa-kb-category-card__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
				</div>
				<div class="fswa-kb-category-card__body">
					<h3 class="fswa-kb-category-card__name"><?php echo $cat_name; ?></h3>
					<?php if ( $cat_desc ) : ?>
						<p class="fswa-kb-category-card__desc"><?php echo $cat_desc; ?></p>
					<?php endif; ?>
					<?php if ( $art_count ) : ?>
						<span class="fswa-kb-category-card__count">
							<?php echo esc_html(
								/* translators: %d: number of articles */
								sprintf( _n( '%d article', '%d articles', $art_count, 'fswa' ), $art_count )
							); ?>
						</span>
					<?php endif; ?>
				</div>
				<span class="fswa-kb-category-card__arrow" aria-hidden="true">&rsaquo;</span>
			</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

</div>
