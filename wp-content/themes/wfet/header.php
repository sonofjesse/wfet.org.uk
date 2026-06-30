<!doctype html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">

	<!-- Favicons -->
	<link rel="icon" type="image/png" href="<?php echo get_template_directory_uri(); ?>/src/images/favicons/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="<?php echo get_template_directory_uri(); ?>/src/images/favicons/favicon.svg" />
	<link rel="shortcut icon" href="<?php echo get_template_directory_uri(); ?>/src/images/favicons/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="<?php echo get_template_directory_uri(); ?>/src/images/favicons/apple-touch-icon.png" />

	<!-- Tag Manager -->
	<script type="text/javascript" src="https://www.googletagmanager.com/gtag/js?id=GT-5DDMFPJ2" id="google_gtagjs-js" async></script>
	<script type="text/javascript" id="google_gtagjs-js-after">
	/* <![CDATA[ */
	window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}
	gtag("set","linker",{"domains":["www.wfet.org.uk"]});
	gtag("js", new Date());
	gtag("set", "developer_id.dZTNiMT", true);
	gtag("config", "GT-5DDMFPJ2");
	window._googlesitekit = window._googlesitekit || {}; window._googlesitekit.throttledEvents = []; window._googlesitekit.gtagEvent = (name, data) => { var key = JSON.stringify( { name, data } ); if ( !! window._googlesitekit.throttledEvents[ key ] ) { return; } window._googlesitekit.throttledEvents[ key ] = true; setTimeout( () => { delete window._googlesitekit.throttledEvents[ key ]; }, 5 ); gtag( "event", name, { ...data, event_source: "site-kit" } ); }; 
	//# sourceURL=google_gtagjs-js-after
	/* ]]> */
	</script>

	<?php wp_head(); ?>
</head>

<body class="soj-frontend">

	<!-- Google Tag Manager (noscript) -->
	<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-MNJTL8Z"
	height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
	<!-- End Google Tag Manager (noscript) -->
	 
	<header id="masthead">
		<div class="container">
			<div class="desktop-nav ">
				<div class="nav-container d-flex justify-content-between align-items-center w-100">

					<!-- Logo -->
					<?php $logo = get_field('logo', 'option');
					if ($logo) : ?>
						<div class="logo-container">
							<a href="/" class="logo relative" title="Back to home">
								<img class='fluid-img dark-logo' src="<?php echo esc_url($logo['url']); ?>" alt="<?php echo esc_attr($logo['alt']); ?>" width="254">
							</a>
						</div>
					<?php endif; ?>

					<!-- Navigation -->
					<div class="header-menu">
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'primary',
								'menu_class'      => 'menu-wrapper',
								'container_class' => '',
								'items_wrap'      => '<ul id="header-menu" class="%2$s">%3$s</ul>',
								'fallback_cb'     => false,
							)
						); ?>
					</div>

					<!-- Mobile Menu Toggle Button -->
					<button
						class="mobile-menu-toggle"
						aria-expanded="false"
						aria-controls="mobile-menu"
						aria-label="<?php esc_attr_e('Toggle mobile menu', 'soj-core'); ?>">
						<div class="icon">
							<span class="white-line line1"></span>
							<span class="white-line line2"></span>
							<span class="white-line line3"></span>
						</div>
					</button>
				</div>
			</div>
		</div>
	</header><!-- #masthead -->


	<!-- Mobile Menu Container -->
	<div id="mobile-menu" class="mobile-menu" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Mobile navigation menu', 'soj-core'); ?>" tabindex="-1" hidden>
		<nav class="mobile-menu-nav d-flex flex-column justify-content-end w-100" role="navigation" aria-label="<?php esc_attr_e('Mobile navigation', 'soj-core'); ?>">

			<div class="container">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'mobile',
						'menu_class'      => 'mobile-menu-list',
						'container'       => false,
						'fallback_cb'     => false,
						// Use default WordPress walker for proper nested structure
					)
				);
				?>
			</div>
		</nav>
		<div class="mobile-menu-overlay"></div>
	</div>