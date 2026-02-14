# Deploy Taller - Checklist

## 1. Migraciones y seeders
1. Ejecutar:
```bash
php artisan migrate
php artisan db:seed --class=WorkshopModuleSeeder
php artisan db:seed --class=WorkshopMenuOptionSeeder
php artisan db:seed --class=WorkshopOperationsSeeder
php artisan db:seed --class=WorkshopChecklistSeeder
php artisan db:seed --class=WorkshopParameterSeeder
php artisan db:seed --class=WorkshopAssemblySeeder
```

## 2. Variables de entorno sugeridas
Revisar y ajustar por entorno:
- `APP_TIMEZONE`
- `FILESYSTEM_DISK`
- parámetros de taller en `parameters/branch_parameters`:
  - `WS_ALLOW_NEGATIVE_STOCK`
  - `WS_ALLOW_DELIVERY_WITH_DEBT`
  - `WS_REQUIRE_PDI_FOR_DELIVERY`
  - `WS_REQUIRED_CHECKLIST_TYPES`
  - `WS_DEFAULT_IGV`
  - `WS_CURRENCY`

## 3. Storage y permisos
1. Crear enlace público:
```bash
php artisan storage:link
```
2. Verificar escritura en:
- `storage/app/workshop_pdfs`
- `storage/logs`
- cache/session views

## 4. PDFs y exportes
1. Validar generación de PDFs de OS y checklists.
2. Validar exportes `.xlsx` (si hay `ZipArchive`).
3. Si no hay `ZipArchive`, validar fallback `.csv`.

## 5. Backup de base de datos (pre y post deploy)
1. Backup previo obligatorio.
2. Backup posterior tras smoke test exitoso.

## 6. Smoke test productivo
1. Cita -> OS -> aprobación -> consumo -> venta -> pago -> entrega.
2. Exportes compras/ventas/armados.
3. Permisos por perfil en sucursal.

