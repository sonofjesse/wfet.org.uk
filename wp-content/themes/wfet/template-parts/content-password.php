<?php

/**
 * Centered password form when password-protected content is not unlocked.
 *
 * Expects setup from the loop: {@see the_post()} must have run for the current global post.
 *
 * @package SOJ_Core_Modern
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<section class="password-gate" aria-labelledby="password-gate-title">
    <div class="password-gate__content container">
        <h1 id="password-gate-title" class="password-gate__title"><?php echo esc_html((string) get_post_field('post_title', get_the_ID(), 'raw')); ?></h1>
        <?php echo get_the_password_form(); ?>
        <p class="password-gate__register-hint">
            <?php
            echo wp_kses(
                sprintf(
                    /* translators: %s: URL of the register-your-interest page */
                    __('If you do not have a password you can <a href="%s">register your interest</a> and the password will be sent to you.', 'soj-core'),
                    esc_url(home_url('/register-your-interest/'))
                ),
                [
                    'a' => [
                        'href' => [],
                    ],
                ]
            );
            ?>
        </p>
    </div>
</section>
