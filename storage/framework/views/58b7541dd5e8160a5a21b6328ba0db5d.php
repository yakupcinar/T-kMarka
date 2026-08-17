
<!doctype html>
<html lang="tr">
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:-apple-system,Segoe UI,Roboto,sans-serif;color:#18181b">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;padding:28px">

    <div style="font-size:18px;font-weight:600;margin-bottom:20px"><?php echo e($markaAdi); ?></div>

    <?php echo e($slot); ?>


    <hr style="border:0;border-top:1px solid #e4e4e7;margin:28px 0 16px">
    <div style="font-size:12px;color:#71717a">
      <?php echo e($markaAdi); ?>

      <?php if($iletisim): ?> · <?php echo e($iletisim); ?> <?php endif; ?>
      <?php if($telefon): ?> · <?php echo e($telefon); ?> <?php endif; ?>
    </div>
  </div>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/components/mail-layout.blade.php ENDPATH**/ ?>