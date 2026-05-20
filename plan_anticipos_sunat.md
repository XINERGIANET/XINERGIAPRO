# Plan de Implementación: Facturación con Pagos Anticipados (SUNAT)

Este documento detalla la arquitectura y lógica necesaria para implementar un flujo de anticipos que cumpla con los requerimientos técnicos del sistema y las normativas de la SUNAT.

## 1. Reglas de Negocio y SUNAT (Obligatorio)
Según la normativa de SUNAT (UBL 2.1), el manejo de anticipos no es simplemente "restar dinero". El flujo legal exige:
1. **El Anticipo genera un comprobante:** Cuando recibes el dinero por adelantado, **debes emitir una Boleta o Factura** por el monto del anticipo (con un ítem o glosa que diga "Anticipo" o similar).
2. **La Venta Final genera el comprobante total:** Al entregar el producto/servicio, se emite una Factura/Boleta por el **Monto Total** de la venta, detallando los productos reales.
3. **Referencia Cruzada:** El comprobante final debe referenciar el comprobante del anticipo (Serie y Número) y restar ese monto del Total a Pagar.

## 2. Modificaciones en la Base de Datos

En lugar de crear un simple campo nulo, es mejor usar una tabla intermedia que conecte el comprobante final con los comprobantes de anticipo, ya que una venta podría pagarse con más de un anticipo.

**Nueva Tabla:** `sale_advances` (o `movement_advances`)
*   `id`: Primary Key
*   `final_movement_id`: ID del Movimiento de la venta final.
*   `advance_movement_id`: ID del Movimiento del anticipo original (para saber qué boleta/factura referenciar en SUNAT).
*   `applied_amount`: Monto del anticipo que se está descontando (Decimal).
*   `created_at`, `updated_at`

*(Opcional)* Un campo `is_advance` (booleano) en la tabla `sales_movements` para identificar fácilmente si esa venta fue solo la emisión de un anticipo.

## 3. Lógica del Sistema (Backend / Controladores)

### Fase A: Emisión del Anticipo
1. Crear una vista/módulo donde se cobre un "Anticipo".
2. Se genera un `Movement` y `SalesMovement` como una venta normal, afectando la caja positivamente.
3. Se envía a Apisunat generando una Boleta/Factura normal cuyo detalle es "Pago Anticipado".
4. Se marca esta venta como un anticipo disponible para el cliente.

### Fase B: Venta Final y Aplicación del Anticipo
1. En el POS (`SalesController@processSale`), al seleccionar al cliente, el sistema busca si tiene "Anticipos Disponibles".
2. Si el cajero decide aplicar el anticipo, se envía en el Request los IDs de los anticipos a usar.
3. **Cálculo Financiero:** 
   * `Subtotal / IGV / Total`: Se calculan sobre el precio real de los productos (ej. Total = 100).
   * `Monto a Pagar (Cobro en Caja)`: Total Venta - Anticipo Aplicado (ej. 100 - 30 = 70).
4. Se guardan los registros en la tabla `sale_advances` vinculando la venta final con la(s) venta(s) del anticipo.

## 4. Cambios en la Facturación Electrónica (`ApisunatService.php`)

Para que la SUNAT valide la resta del anticipo en la factura final, se debe modificar la función `buildDocumentBody` para incluir los siguientes nodos UBL 2.1:

1. **`cac:AdditionalDocumentReference`**:
   Indica qué comprobantes previos se están descontando.
   ```xml
   <cac:AdditionalDocumentReference>
       <cbc:ID>F001-00000001</cbc:ID> <!-- Serie y número del anticipo -->
       <cbc:DocumentTypeCode listID="01">02</cbc:DocumentTypeCode> <!-- 02 = Anticipo Factura, 03 = Anticipo Boleta -->
       <cbc:DocumentStatusCode listID="Anticipo">1</cbc:DocumentStatusCode>
   </cac:AdditionalDocumentReference>
   ```

2. **`cac:PrepaidPayment`**:
   El monto exacto que se está descontando de ese documento.
   ```xml
   <cac:PrepaidPayment>
       <cbc:ID>F001-00000001</cbc:ID>
       <cbc:PaidAmount currencyID="PEN">30.00</cbc:PaidAmount>
   </cac:PrepaidPayment>
   ```

3. **Ajuste en `cac:LegalMonetaryTotal`**:
   Se debe agregar la etiqueta `cbc:PrepaidAmount` y recalcular el `cbc:PayableAmount` (Monto a pagar).
   ```xml
   <cac:LegalMonetaryTotal>
       <cbc:LineExtensionAmount currencyID="PEN">84.75</cbc:LineExtensionAmount> <!-- Subtotal -->
       <cbc:TaxInclusiveAmount currencyID="PEN">100.00</cbc:TaxInclusiveAmount> <!-- Total Venta -->
       <cbc:PrepaidAmount currencyID="PEN">30.00</cbc:PrepaidAmount>            <!-- Total Anticipos -->
       <cbc:PayableAmount currencyID="PEN">70.00</cbc:PayableAmount>            <!-- Total a Pagar real -->
   </cac:LegalMonetaryTotal>
   ```

## 5. Cambios en la Interfaz de Usuario (Frontend)

1. **En el POS (Ventas):** Cuando se busque a un cliente (por RUC o DNI), mostrar una alerta o etiqueta: *"Este cliente tiene S/ X.00 en anticipos a su favor"*.
2. **Modal de Cobro:** Añadir una sección "Aplicar Anticipo". Si el Total es S/ 100, y se aplican S/ 30 de anticipo, el "Faltante a cobrar" de contado/tarjeta bajará automáticamente a S/ 70.
3. **Ticket/PDF de Venta:** Modificar el diseño del ticket para que muestre el resumen claro:
   * Total Venta: S/ 100.00
   * Anticipos Aplicados: - S/ 30.00
   * Total Pagado Hoy: S/ 70.00
