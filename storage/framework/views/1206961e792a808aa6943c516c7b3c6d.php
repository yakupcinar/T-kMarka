<?php $__env->startSection('baslik', $tema['ad'].' — şu anda kapalı'); ?>

<?php $__env->startSection('icerik'); ?>

    <div class="bos">
        <h1 style="color:#1c1917">Mağaza şu anda kapalı</h1>

        
        <p><?php echo e($tema['ad']); ?> kısa süre içinde tekrar hizmete girecek.</p>
    </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('storefront.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/storefront/kapali.blade.php ENDPATH**/ ?>