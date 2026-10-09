<?php

function a(): void { echo $a; ?><?php echo $b ?><?php $c = '<?= $a ?>'; $d = "<?= $a ?>"; $e = <<<EOT
<?= $a ?>
EOT; /* <?= $a ?> */ // <?= $a ?>
}
