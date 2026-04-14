<p>Estimado(a) cliente,</p>
<p>Adjuntamos la cotización <strong>{{ $order->quotation_correlative ?: ($order->movement?->number ?? ('OS '.$order->id)) }}</strong> generada desde nuestro sistema de taller.</p>
<p>Si tiene consultas, puede responder a este correo.</p>
<p>Saludos cordiales,<br>{{ config('app.name') }}</p>
