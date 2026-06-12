<?php

/**
 * The template for displaying the footer
 *
 * @package WordPress
 * @subpackage soj
 */

$logo           = get_field('logo', 'option');
$twitter        = get_field('twitter', 'option');
$linkedin       = get_field('linkedin', 'option');
$footer_one     = get_field('footer_one', 'option');
$footer_two     = get_field('footer_two', 'option');
$footer_three   = get_field('footer_three', 'option');
$footer_four    = get_field('footer_four', 'option');
$footer_five    = get_field('footer_five', 'option');
$footer_links   = get_field('footer_links', 'option');
$copyright_text = get_field('copyright_text', 'option');
$find_us        = get_field('find_us', 'option');
$find_us_link   = get_field('find_us_link', 'option');
$footer_accreditation = get_field('footer_accreditation', 'option');
$footer_image = get_field('footer_image', 'option');

$footer_nav_columns = array(
	$footer_one,
	$footer_two,
	$footer_three,
	$footer_four,
);
?>

<footer id="footer" class="footer">
	<?php if ($footer_image) : ?>
		<div class="footer__circle" aria-hidden="true">
			<?php
			echo wp_get_attachment_image(
				$footer_image['ID'],
				'full',
				false,
				[
					'class'   => 'footer__circle-img',
					'loading' => 'lazy',
					'decoding' => 'async',
				]
			);
			?>
		</div>
	<?php endif; ?>

	<div class="footer__main">
		<div class="container footer__main-inner" data-gsap-animate="stagger">
			<?php if ($logo) : ?>
				<div class="footer__brand">
					<a href="<?php echo esc_url(home_url('/')); ?>" class="footer__logo" title="<?php esc_attr_e('Back to home', 'soj-core'); ?>">
						<img class="footer__logo-img" src="<?php echo esc_url($logo['url']); ?>" alt="<?php echo esc_attr($logo['alt']); ?>" width="245" height="64">
					</a>

					<?php if ($twitter || $linkedin) : ?>
						<ul class="footer__social" aria-label="<?php esc_attr_e('Social media', 'soj-core'); ?>">
							<?php if ($twitter) : ?>
								<li>
									<a class="footer__social-link" href="<?php echo esc_url($twitter); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e('X (Twitter)', 'soj-core'); ?>">
										<svg class="footer__social-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
											<path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
										</svg>
									</a>
								</li>
							<?php endif; ?>

							<?php if ($linkedin) : ?>
								<li>
									<a class="footer__social-link" href="<?php echo esc_url($linkedin); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e('LinkedIn', 'soj-core'); ?>">
										<svg class="footer__social-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
											<path fill="currentColor" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
										</svg>
									</a>
								</li>
							<?php endif; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php foreach ($footer_nav_columns as $column) : ?>
				<?php if (!$column || (empty($column['title']) && empty($column['links']))) : ?>
					<?php continue; ?>
				<?php endif; ?>

				<div class="footer__column">
					<?php if (!empty($column['title'])) : ?>
						<h2 class="footer__heading"><?php echo esc_html($column['title']); ?></h2>
					<?php endif; ?>

					<?php if (!empty($column['links'])) : ?>
						<ul class="footer__links">
							<?php foreach ($column['links'] as $link) : ?>
								<?php if (empty($link['link'])) : ?>
									<?php continue; ?>
								<?php endif; ?>

								<?php
								$link_target = !empty($link['link']['target']) ? $link['link']['target'] : '_self';
								$href        = $link['link']['url'];
								$is_current  = soj_footer_link_is_current($href);
								?>
								<li class="footer__link-item">
									<a href="<?php echo esc_url($href); ?>" target="<?php echo esc_attr($link_target); ?>"<?php echo $is_current ? ' class="is-active" aria-current="page"' : ''; ?>>
										<?php echo esc_html($link['link']['title']); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="footer__bottom">
		<div class="container footer__bottom-inner">
			<?php if ($copyright_text) : ?>
				<div class="footer__copyright">
					&copy; <?php echo esc_html(gmdate('Y')); ?> <?php echo wp_kses_post($copyright_text); ?>
				</div>
			<?php endif; ?>

			<?php if ($footer_links) : ?>
				<ul class="footer__legal">
					<?php foreach ($footer_links as $link) : ?>
						<?php
						$legal_href       = get_permalink($link);
						$legal_is_current = soj_footer_link_is_current($legal_href);
						?>
						<li class="footer__legal-item">
							<a href="<?php echo esc_url($legal_href); ?>" title="<?php echo esc_attr($link->post_title); ?>"<?php echo $legal_is_current ? ' class="is-active" aria-current="page"' : ''; ?>>
								<?php echo esc_html($link->post_title); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ($footer_accreditation) : ?>
				<div class="footer__accreditation">
					<img
						class="footer__accreditation-img"
						src="<?php echo esc_url($footer_accreditation['url']); ?>"
						alt="<?php echo esc_attr($footer_accreditation['alt'] ?: __('Footer accreditation', 'soj-core')); ?>"
						<?php if (!empty($footer_accreditation['width'])) : ?>
							width="<?php echo esc_attr($footer_accreditation['width']); ?>"
						<?php endif; ?>
						<?php if (!empty($footer_accreditation['height'])) : ?>
							height="<?php echo esc_attr($footer_accreditation['height']); ?>"
						<?php endif; ?>
						loading="lazy"
						decoding="async"
					>
				</div>
			<?php endif; ?>
		</div>
	</div>
</footer>

</div><!-- #page -->

<?php wp_footer(); ?>

</body>

</html>
