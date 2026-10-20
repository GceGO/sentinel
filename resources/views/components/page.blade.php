@props(['context'])
@include($context->layout->view(), ['context' => $context, 'slot' => $slot])
