<?php $__env->startSection('baslik', $arama ? $arama.' — '.$tema['ad'] : $tema['ad']); ?>

<?php $__env->startSection('icerik'); ?>

    <?php if($arama): ?>
        <p style="padding-top:24px">
            <strong>“<?php echo e($arama); ?>”</strong> için <?php echo e($urunler->count()); ?> sonuç
            · <a href="<?php echo e(route('vitrin.anasayfa')); ?>">aramayı temizle</a>
        </p>
    <?php endif; ?>

    <?php if($urunler->isEmpty()): ?>
        <p class="bos">
            <?php if($arama): ?>
                Aradığınız ürün bulunamadı.
            <?php else: ?>
                
                Bu mağazada henüz ürün yok.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <div class="izgara">
            <?php $__currentLoopData = $urunler; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $urun): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                
                <a class="kart" href="<?php echo e(route('vitrin.anasayfa')); ?>">
                    <?php if($urun->images->first()): ?>
                        <img src="<?php echo e($urun->images->first()->url()); ?>" alt="<?php echo e($urun->title); ?>">
                    <?php else: ?>
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" alt="">
                    <?php endif; ?>

                    <div class="govde">
                        <span class="ad"><?php echo e($urun->title); ?></span>

                        
                        <?php if($urun->variants->isNotEmpty()): ?>
                            <span class="fiyat">
                                <?php echo e(number_format((float) $urun->variants->min('price'), 2, ',', '.')); ?> TL
                            </span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('storefront.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/storefront/sade/anasayfa.blade.php ENDPATH**/ ?>