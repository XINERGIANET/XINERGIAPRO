<!doctype html>
<html><head><meta charset="utf-8"><title>Checklist PDI</title></head><body>
<h2>Checklist PDI - OS {{ $order->movement?->number }}</h2>
<table border="1" cellspacing="0" cellpadding="4"><tr><th>#</th><th>Grupo</th><th>Item</th><th>Accion</th><th>Resultado</th><th>Obs.</th></tr>
@foreach($order->checklists->where('type','PDI') as $checklist)
@foreach($checklist->items as $item)
<tr><td>{{ $item->order_num }}</td><td>{{ $item->group }}</td><td>{{ $item->label }}</td><td>{{ $item->action }}</td><td>{{ $item->result }}</td><td>{{ $item->observation }}</td></tr>
@endforeach
@endforeach
</table>
</body></html>

