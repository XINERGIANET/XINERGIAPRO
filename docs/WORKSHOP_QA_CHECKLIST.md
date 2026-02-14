# QA Taller - Flujo Mínimo Real

## 1. Flujo punta a punta
1. Crear cita en `Agenda` (estado `pending`).
2. Convertir cita a OS y verificar que no permita convertir dos veces.
3. Registrar inspección + inventario + daños.
4. Guardar checklist PDI y mantenimiento.
5. Generar cotización (`awaiting_approval`) y aprobar.
6. Agregar repuestos, reservar y consumir stock.
7. Generar venta desde OS.
8. Registrar pago parcial y luego pago total.
9. Marcar OS como `finished` y luego `delivered`.
10. Verificar bloqueo de edición tras entrega.

## 2. Stock
1. Intentar consumir repuesto sin stock: debe bloquear.
2. Consumir repuesto con stock.
3. Devolver repuesto consumido y verificar reingreso.
4. Verificar kardex (ingreso/salida) por producto.

## 3. Caja
1. Registrar pago con caja/turno abierto.
2. Registrar pago parcial.
3. Registrar devolución.
4. Verificar estado de deuda (`pending/partial/paid`).

## 4. Exportes
1. Exportar compras mensual.
2. Exportar ventas mensual (Natural/Corporativo).
3. Exportar armados mensual.
4. Exportar kardex.
5. Exportar OS por rango.
6. Exportar productividad técnicos.

## 5. PDFs
1. PDF OS.
2. PDF Activación GP.
3. PDF PDI.
4. PDF Mantenimiento.
5. PDF Repuestos usados.
6. PDF Venta interna.

## 6. Seguridad y permisos
1. Probar perfil Recepción (crear/editar OS, no anular admin).
2. Probar perfil Técnico (diagnóstico, ejecución).
3. Probar perfil Almacenero (consumo/stock).
4. Probar perfil Cajero (pagos/devoluciones).
5. Probar perfil Admin (reabrir/anular/entregar forzado).

