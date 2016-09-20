<div class="page-wrapper">
  <div class="content-wrapper">
    <?php if ($logo): ?>
      <img src="<?php print $logo; ?>" alt="<?php print t('Home'); ?>" class="logo" />
    <?php endif; ?>
    <?php print $messages; ?>
    <?php print render($page['content']); ?>
  </div>
</div>
