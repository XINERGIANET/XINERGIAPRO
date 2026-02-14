<!doctype html>
<html><head><meta charset="utf-8"><title>Activacion GP</title></head><body>
<h2>Activacion / Inspeccion GP MOTOS - OS {{ $order->movement?->number }}</h2>
<table border="1" cellspacing="0" cellpadding="4"><tr><th>Grupo</th><th>Item</th><th>Resultado</th><th>Obs.</th></tr>
@foreach($order->checklists->where('type','GP_ACTIVATION') as $checklist)
@foreach($checklist->items as $item)
<tr><td>{{ $item->group }}</td><td>{{ $item->label }}</td><td>{{ $item->result }}</td><td>{{ $item->observation }}</td></tr>
@endforeach
@endforeach
</table>
</body></html>

