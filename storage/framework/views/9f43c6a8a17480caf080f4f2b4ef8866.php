<?php if (isset($component)) { $__componentOriginalaccef25896c411ee749b60cdeb8bf4e5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaccef25896c411ee749b60cdeb8bf4e5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.mail-layout','data' => ['markaAdi' => $markaAdi,'iletisim' => $iletisim,'telefon' => $telefon]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mail-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['markaAdi' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($markaAdi),'iletisim' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($iletisim),'telefon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($telefon)]); ?>
  <p style="margin:0 0 16px">Siparişiniz alındı, hazırlanmaya başlıyor.</p>

  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr><td style="padding:6px 0;color:#71717a">Sipariş no</td><td style="text-align:right"><b><?php echo e($siparis->order_number); ?></b></td></tr>
    <?php $__currentLoopData = $siparis->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $satir): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td style="padding:6px 0;border-top:1px solid #f4f4f5"><?php echo e($satir->product_title); ?> × <?php echo e($satir->quantity); ?></td>
        <td style="text-align:right;border-top:1px solid #f4f4f5"><?php echo e($satir->line_total); ?> TL</td>
      </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <tr><td style="padding:6px 0;border-top:1px solid #e4e4e7;color:#71717a">Kargo</td><td style="text-align:right;border-top:1px solid #e4e4e7"><?php echo e($siparis->shipping_total); ?> TL</td></tr>
    <tr><td style="padding:6px 0"><b>Toplam</b></td><td style="text-align:right"><b><?php echo e($siparis->grand_total); ?> TL</b></td></tr>
    
    <tr><td style="padding:2px 0;font-size:12px;color:#a1a1aa">KDV (dâhil)</td><td style="text-align:right;font-size:12px;color:#a1a1aa"><?php echo e($siparis->tax_total); ?> TL</td></tr>
  </table>

  <p style="margin:20px 0 0;font-size:13px;color:#71717a">
    Kargoya verildiğinde takip bilgisiyle tekrar yazacağız.
  </p>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaccef25896c411ee749b60cdeb8bf4e5)): ?>
<?php $attributes = $__attributesOriginalaccef25896c411ee749b60cdeb8bf4e5; ?>
<?php unset($__attributesOriginalaccef25896c411ee749b60cdeb8bf4e5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaccef25896c411ee749b60cdeb8bf4e5)): ?>
<?php $component = $__componentOriginalaccef25896c411ee749b60cdeb8bf4e5; ?>
<?php unset($__componentOriginalaccef25896c411ee749b60cdeb8bf4e5); ?>
<?php endif; ?>
<?php /**PATH /var/www/html/resources/views/mail/order-paid.blade.php ENDPATH**/ ?>