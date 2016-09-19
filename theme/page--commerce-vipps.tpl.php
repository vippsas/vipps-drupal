<?php if ($logo): ?>
    <img src="<?php print $logo; ?>" alt="<?php print t('Home'); ?>" />
<?php endif; ?>
<?php print $messages; ?>
<?php print render($page['content']); ?>

