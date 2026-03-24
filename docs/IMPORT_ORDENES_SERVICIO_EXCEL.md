# Importación Excel – Órdenes de servicio (taller)

Ruta en la app: **Taller → Órdenes de servicio → Importar Excel** (`POST .../admin/taller/ordenes/import-excel`).

## Columnas esperadas (fila de encabezados)

| Obligatorio | Nombres reconocidos (contiene / igual) |
|-------------|----------------------------------------|
| Sí | **PLACA** o **PATENTE** |
| Sí | **OBSERVACIONES** (texto con trabajos separados por `+`) |

| Opcional | Ejemplos de encabezado |
|----------|-------------------------|
| Documento cliente | DOCUMENTO, DNI, RUC, NRO DOC… (debe coincidir con el titular del vehículo en la sucursal) |
| Fecha ingreso | FECHA INGRESO, FECHA ENTRADA… o cualquier columna con «fecha» |
| Kilometraje | KILOMETRAJE, KM… |

## OBSERVACIONES

Ejemplo:

`CAMBIO DE ACEITE 15W50 + FILTRO DE ACEITE + CAMBIO DE DISCOS DE EMBRAGUE`

Cada tramo separado por `+` genera una **línea de servicio en glosa** (sin catálogo), cantidad 1, precio 0.

## Comportamiento

- Se busca el **vehículo** por placa (sin espacios, mayúsculas) en la empresa de la sucursal actual.
- La OS se crea en estado **terminada**, se cargan las glosas y pasa a **entregada**.
- Quien importa debe poder **entregar** OS según el flujo del taller (perfil tipo recepción/cajero/jefe o admin), igual que al entregar manualmente.

## Archivo

- `.xlsx`, `.xls` o `.csv`, máx. ~10 MB.
- Para `.xlsx` en PHP debe estar habilitada la extensión **zip** (ZipArchive).
