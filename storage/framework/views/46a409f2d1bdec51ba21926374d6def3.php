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
  <p style="margin:0 0 16px"><b><?php echo e($siparis->order_number); ?></b> numaralı siparişinizin ödemesi alınamadı.</p>

  
  <p style="margin:0 0 16px;font-size:14px">
    Kartınızdan tahsilat yapılmadı. Farklı bir kartla tekrar deneyebilirsiniz.
  </p>

  <p style="margin:0;font-size:13px;color:#71717a">
    Siparişiniz kaydımızda duruyor; sorun yaşarsanız bize yazabilirsiniz.
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
<?php /**PATH /var/www/html/resources/views/mail/payment-failed.blade.php ENDPATH**/ ?>