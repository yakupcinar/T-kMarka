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
  <?php if($teslim): ?>
    <p style="margin:0 0 16px">Siparişiniz teslim edildi. Afiyet olsun!</p>
  <?php else: ?>
    <p style="margin:0 0 16px">Siparişiniz kargoya verildi.</p>
  <?php endif; ?>

  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr><td style="padding:6px 0;color:#71717a">Sipariş no</td><td style="text-align:right"><b><?php echo e($paket->order?->order_number); ?></b></td></tr>
    <?php if($paket->carrier): ?>
      <tr><td style="padding:6px 0;color:#71717a">Kargo</td><td style="text-align:right"><?php echo e($paket->carrier); ?></td></tr>
    <?php endif; ?>
    <?php if($paket->tracking_number): ?>
      <tr><td style="padding:6px 0;color:#71717a">Takip no</td><td style="text-align:right"><b><?php echo e($paket->tracking_number); ?></b></td></tr>
    <?php endif; ?>
  </table>

  
  <p style="margin:16px 0 6px;font-size:13px;color:#71717a">Bu pakettekiler:</p>
  <ul style="margin:0;padding-left:18px;font-size:14px">
    <?php $__currentLoopData = $paket->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kalem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <li><?php echo e($kalem->orderItem?->product_title); ?> × <?php echo e($kalem->quantity); ?></li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </ul>
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
<?php /**PATH /var/www/html/resources/views/mail/shipment.blade.php ENDPATH**/ ?>