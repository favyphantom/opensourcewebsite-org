<b>+<?= $walletTransaction->amount ?> <?= $walletTransaction->currency->code ?></b><br/>
<?php if ($walletTransaction->hasTypeLabel()): ?>
<br/>
<?= Yii::t('bot', 'Description') ?>: <?= $walletTransaction->getTypeLabel() ?><br/>
<?php endif; ?>
<br/>
<?= Yii::t('bot', 'Sender') ?>: <?= $walletTransaction->fromUser->botUser->getFullLink() ?><br/>
<?php if ($walletTransaction->hasGroupLabel()): ?>
<br/>
<?= Yii::t('bot', 'Group') ?>: <?= $walletTransaction->getGroupLabel() ?><br/>
<?php endif; ?>
————<br/>
<i><?= Yii::t('bot', 'Available amount') ?>: <?= $toUserWallet->amount ?> <?= $walletTransaction->currency->code ?></i><br/>
