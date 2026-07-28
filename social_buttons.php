<?php

// Social buttons component — tüm sayfalarda ortak



$stmt = $pdo->query(

    'SELECT show_whatsapp, whatsapp_number, show_instagram, instagram_username

     FROM notification_settings WHERE id = 1'

);

$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];



$showWa = !empty($settings['show_whatsapp']) && trim((string) ($settings['whatsapp_number'] ?? '')) !== '';

$showIg = !empty($settings['show_instagram']) && trim((string) ($settings['instagram_username'] ?? '')) !== '';

?>



<style>

.social-buttons {

    position: fixed;

    left: 20px;

    bottom: 40px;

    display: flex;

    flex-direction: column;

    gap: 12px;

    z-index: 1000;

}



.social-buttons a {

    display: block;

    width: 50px;

    height: 50px;

    border-radius: 50%;

    background-size: cover;

    background-position: center;

    transition: transform 0.2s ease, box-shadow 0.2s ease;

    box-shadow: 0 3px 8px rgba(0,0,0,0.2);

}



.social-buttons a:hover {

    transform: scale(1.1);

    box-shadow: 0 5px 12px rgba(0,0,0,0.25);

}



.social-buttons .whatsapp {

    background-image: url('uploads/images/whatsapp.png');

}



.social-buttons .instagram {

    background-image: url('uploads/images/instagram.png');

}

</style>



<?php if ($showWa || $showIg): ?>

<div class="social-buttons">

    <?php if ($showIg): ?>

    <a href="social_go.php?c=instagram" class="instagram" data-social-channel="instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram"></a>

    <?php endif; ?>



    <?php if ($showWa): ?>

    <a href="social_go.php?c=whatsapp" class="whatsapp" data-social-channel="whatsapp" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"></a>

    <?php endif; ?>

</div>

<?php

require_once __DIR__ . '/includes/conversion_tracking.php';

echo conversion_render_social_click_tracker_script($pdo);

?>

<?php endif; ?>

