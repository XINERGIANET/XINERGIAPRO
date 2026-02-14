<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkshopChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $categoryId = $this->resolveWorkshopParameterCategoryId();

        $this->seedWorkshopServices();
        $this->seedOsCatalogData($categoryId);
        $this->seedOsIntakeTemplate($categoryId);
        $this->seedGpActivationTemplate($categoryId);
        $this->seedPdiTemplate($categoryId);
        $this->seedMaintenanceTemplate($categoryId);
    }

    private function resolveWorkshopParameterCategoryId(): int
    {
        $existing = DB::table('parameter_categories')
            ->whereRaw('UPPER(description) = ?', ['TALLER'])
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('parameter_categories')->insertGetId([
            'description' => 'Taller',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedWorkshopServices(): void
    {
        $now = now();
        $branches = DB::table('branches')->select('id', 'company_id')->get();
        if ($branches->isEmpty()) {
            return;
        }

        $baseServices = [
            ['name' => 'Mantenimiento basico', 'type' => 'preventivo', 'base_price' => 50, 'estimated_minutes' => 45],
            ['name' => 'Mantenimiento intermedio', 'type' => 'preventivo', 'base_price' => 90, 'estimated_minutes' => 80],
            ['name' => 'Mantenimiento general', 'type' => 'preventivo', 'base_price' => 140, 'estimated_minutes' => 120],
            ['name' => 'Mantenimiento correctivo', 'type' => 'correctivo', 'base_price' => 110, 'estimated_minutes' => 100],
            ['name' => 'Limpieza inyectores', 'type' => 'correctivo', 'base_price' => 70, 'estimated_minutes' => 60],
            ['name' => 'Reparacion cabezal', 'type' => 'correctivo', 'base_price' => 180, 'estimated_minutes' => 180],
            ['name' => 'Escaneo de moto', 'type' => 'correctivo', 'base_price' => 35, 'estimated_minutes' => 25],
            ['name' => 'Cambio de discos', 'type' => 'correctivo', 'base_price' => 85, 'estimated_minutes' => 70],
            ['name' => 'Limpieza cuerpo de aceleracion', 'type' => 'correctivo', 'base_price' => 60, 'estimated_minutes' => 45],
            ['name' => 'Lavado', 'type' => 'preventivo', 'base_price' => 20, 'estimated_minutes' => 20],
            ['name' => 'Parchado neumatico', 'type' => 'correctivo', 'base_price' => 18, 'estimated_minutes' => 25],
        ];

        foreach ($branches as $branch) {
            foreach ($baseServices as $service) {
                $exists = DB::table('workshop_services')
                    ->where('company_id', (int) $branch->company_id)
                    ->where('branch_id', (int) $branch->id)
                    ->where('name', $service['name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('workshop_services')->insert([
                    'company_id' => (int) $branch->company_id,
                    'branch_id' => (int) $branch->id,
                    'name' => $service['name'],
                    'type' => $service['type'],
                    'base_price' => $service['base_price'],
                    'estimated_minutes' => $service['estimated_minutes'],
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedOsCatalogData(int $categoryId): void
    {
        $jobs = [
            'Mantenimiento preventivo basico',
            'Mantenimiento preventivo intermedio',
            'Mantenimiento preventivo general',
            'Mantenimiento correctivo',
            'Limpieza inyectores',
            'Reparacion cabezal',
            'Escaneo de moto',
            'Cambio de discos',
            'Limpieza cuerpo de aceleracion',
            'Lavado',
            'Parchado neumatico',
        ];

        $inventoryItems = [
            'ESPEJOS',
            'FARO_DELANTERO',
            'DIRECCIONALES',
            'TAPON_GASOLINA',
            'PEDALES',
            'CLAXON',
            'ASIENTOS',
            'LUZ_STOP_TRASERA',
            'CUBIERTAS_COMPLETAS',
            'TACOMETROS',
            'STEREO',
            'PARABRISAS',
            'TAPON_RADIADORES',
            'FILTRO_AIRE',
            'BATERIA',
            'LLAVES',
        ];

        DB::table('parameters')->updateOrInsert(
            ['description' => 'WS_OS_WORK_ITEMS', 'parameter_category_id' => $categoryId],
            [
                'value' => json_encode(['items' => $jobs]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('parameters')->updateOrInsert(
            ['description' => 'WS_OS_INTAKE_INVENTORY_ITEMS', 'parameter_category_id' => $categoryId],
            [
                'value' => json_encode(['items' => $inventoryItems]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function seedOsIntakeTemplate(int $categoryId): void
    {
        $items = [];
        $push = function (string $group, string $label, ?string $action = null, array $results = ['SI', 'NO']) use (&$items): void {
            $items[] = [
                'group' => $group,
                'label' => $label,
                'action' => $action,
                'results' => $results,
            ];
        };

        $workItems = [
            'Mantenimiento preventivo basico',
            'Mantenimiento preventivo intermedio',
            'Mantenimiento preventivo general',
            'Mantenimiento correctivo',
            'Limpieza inyectores',
            'Reparacion cabezal',
            'Escaneo de moto',
            'Cambio de discos',
            'Limpieza cuerpo de aceleracion',
            'Lavado',
            'Parchado neumatico',
        ];
        foreach ($workItems as $label) {
            $push('TRABAJO_A_REALIZAR', $label);
        }

        $inventoryItems = [
            'Espejos',
            'Faro delantero',
            'Direccionales',
            'Tapon de gasolina',
            'Pedales',
            'Claxon',
            'Asientos',
            'Luz de stop trasera',
            'Cubiertas completas',
            'Tacometros',
            'Stereo',
            'Parabrisas',
            'Tapon de radiadores',
            'Filtro de aire',
            'Bateria',
            'Llaves',
        ];
        foreach ($inventoryItems as $label) {
            $push('INVENTARIO', $label, null, ['PRESENTE', 'NO_PRESENTE']);
        }

        $damageSides = ['Lado derecho', 'Frente', 'Detras', 'Lado izquierdo'];
        foreach ($damageSides as $side) {
            $push('DANOS_PREEXISTENTES', $side, null, ['SIN_DANO', 'DANADO']);
        }

        $push('RECEPCION', 'Ingreso en grua');
        $push('RECEPCION', 'Observaciones generales registradas');
        $push('RECEPCION', 'Autorizacion del cliente');

        $this->seedTemplateItems($categoryId, 'OS_INTAKE', $items);
    }

    private function seedGpActivationTemplate(int $categoryId): void
    {
        $items = [
            ['group' => 'TABLERO_INSTRUMENTOS', 'label' => 'Velocimetro'],
            ['group' => 'TABLERO_INSTRUMENTOS', 'label' => 'Nivel de gasolina'],
            ['group' => 'TABLERO_INSTRUMENTOS', 'label' => 'Tacometro'],
            ['group' => 'TABLERO_INSTRUMENTOS', 'label' => 'Indicadores direccionales'],
            ['group' => 'TABLERO_INSTRUMENTOS', 'label' => 'Indicadores luz alta y baja'],
            ['group' => 'PEDALES_Y_MANIJAS', 'label' => 'Arranque'],
            ['group' => 'PEDALES_Y_MANIJAS', 'label' => 'Freno'],
            ['group' => 'PEDALES_Y_MANIJAS', 'label' => 'Cambios'],
            ['group' => 'PEDALES_Y_MANIJAS', 'label' => 'Embrague'],
            ['group' => 'PEDALES_Y_MANIJAS', 'label' => 'Freno delantero'],
            ['group' => 'LUCES', 'label' => 'Luz baja'],
            ['group' => 'LUCES', 'label' => 'Luz alta'],
            ['group' => 'LUCES', 'label' => 'Direccionales'],
            ['group' => 'LUCES', 'label' => 'Luz de placa'],
            ['group' => 'LUCES', 'label' => 'Luz de frenada'],
            ['group' => 'ESTADO_TECNICO', 'label' => 'No presenta fuga de combustible'],
            ['group' => 'ESTADO_TECNICO', 'label' => 'No presenta fuga de aceite'],
            ['group' => 'ESTADO_TECNICO', 'label' => 'Claxon'],
            ['group' => 'ESTADO_TECNICO', 'label' => 'Cable de embrague'],
            ['group' => 'ESTADO_TECNICO', 'label' => 'Cable de acelerador'],
            ['group' => 'ESTADO_TECNICO', 'label' => 'Baul o maletero'],
            ['group' => 'ESTADO_TECNICO', 'label' => 'Llantas'],
            ['group' => 'ESTADO_TECNICO', 'label' => 'Bateria'],
            ['group' => 'ESTADO_TECNICO', 'label' => 'Espejos derecho/izquierdo'],
            ['group' => 'ESTADO_TECNICO', 'label' => 'Juego de herramientas'],
        ];

        $items = array_map(fn ($item) => array_merge($item, [
            'action' => null,
            'results' => ['OK', 'FALTANTE', 'DANADO'],
        ]), $items);

        $this->seedTemplateItems($categoryId, 'GP_ACTIVATION', $items);
    }

    private function seedPdiTemplate(int $categoryId): void
    {
        $pdiItems = [
            ['Aceite corona', 'REVISAR'],
            ['Aceite caja', 'REVISAR'],
            ['Aceite de motor', 'REVISAR'],
            ['Tapon y varilla de aceite', 'REVISAR'],
            ['Ajuste de carroceria', 'AJUSTAR'],
            ['Ajuste de dodo regulador', 'AJUSTAR'],
            ['Ajuste de perno regulador de cadena', 'AJUSTAR'],
            ['Ajuste de soporte del motor', 'AJUSTAR'],
            ['Ajuste de tuerca de tubo de escape', 'AJUSTAR'],
            ['Ajuste de tuercas de parabrisas', 'AJUSTAR'],
            ['Ajuste general de perneria', 'REVISAR'],
            ['Alarma de retroceso', 'REVISAR'],
            ['Alineacion de llantas posteriores', 'REVISAR'],
            ['Alineamiento de carrete', 'REVISAR'],
            ['Asientos (piloto/pasajeros)', 'REVISAR'],
            ['Bateria y seguros', 'REVISAR'],
            ['Bateria', 'CARGAR'],
            ['Bujia', 'REVISAR'],
            ['Cabina', 'REVISAR'],
            ['Cambios', 'SINCRONIZAR'],
            ['Carburador', 'AJUSTAR'],
            ['Claxon', 'REVISAR'],
            ['Calcomanias', 'COLOCAR'],
            ['Columna de direccion (yugo/cana)', 'REVISAR'],
            ['Comando de luces (alta y baja)', 'REVISAR'],
            ['Contador de velocimetro', 'REVISAR'],
            ['Corona y cardan', 'REVISAR'],
            ['Culata torque', 'REVISAR'],
            ['Direccion', 'REVISAR'],
            ['Eje delantero y posterior', 'REVISAR'],
            ['Embrague', 'REGULAR'],
            ['Espejos', 'AJUSTAR'],
            ['Faros delantero y posterior', 'REVISAR'],
            ['Filtro de aire', 'REVISAR'],
            ['Freno de parqueo', 'REVISAR'],
            ['Freno delantero', 'REVISAR'],
            ['Frenos posterior', 'REVISAR'],
            ['Frenos - purgar y regular (K300)', 'REGULAR'],
            ['Fugas de aceite de motor (frio/caliente)', 'REVISAR'],
            ['Grifo de gasolina', 'REVISAR'],
            ['Herramientas', 'REVISAR'],
            ['Interruptores izq y der', 'REVISAR'],
            ['Juego libre de neumaticos (no frenado)', 'REVISAR'],
            ['Juego libre del mango del acelerador', 'REVISAR'],
            ['Limpia parabrisas (K300)', 'REVISAR'],
            ['Liquido de freno', 'REVISAR'],
            ['Llanta de repuesto', 'REVISAR'],
            ['Llantas - presion', 'REVISAR'],
            ['Llaves y duplicados', 'REVISAR'],
            ['Luces', 'REVISAR'],
            ['Manual de usuario', 'REVISAR'],
            ['Medidor de gasolina (boya)', 'REVISAR'],
            ['Micas delanteras', 'REVISAR'],
            ['Micas posteriores', 'REVISAR'],
            ['Motor ralenti', 'REVISAR'],
            ['Muelles', 'REVISAR'],
            ['Palieres', 'ENGRASAR'],
            ['Parabrisa', 'REVISAR'],
            ['Pintura', 'REVISAR'],
            ['Pinon de velocimetro', 'REVISAR'],
            ['Pisos de jebe', 'REVISAR'],
            ['Pisos pasajero y carga', 'REVISAR'],
            ['Prueba de pista', 'PROBAR'],
            ['Radiador', 'REVISAR'],
            ['Radio', 'PROBAR'],
            ['Retroceso', 'REVISAR'],
            ['Rouster', 'REVISAR'],
            ['Selector de cambios', 'ENGRASAR'],
            ['Sistema antidive', 'REVISAR'],
            ['Sistema de arrastre', 'REVISAR'],
            ['Sistema de carga (12 voltios)', 'REVISAR'],
            ['Suspension delantera y posterior', 'REVISAR'],
            ['Tapa de liquido de freno', 'REVISAR'],
            ['Tapa de tanque', 'REVISAR'],
            ['Triko / plumillas', 'REVISAR'],
            ['Valvulas', 'CALIBRAR'],
            ['Verificar e inspeccionar CDI', 'REVISAR'],
            ['Verificar indicadores de tablero', 'REVISAR'],
            ['Verificar switch de pedal de freno', 'REVISAR'],
            ['Vinil reflectivo', 'COLOCAR'],
        ];

        $items = [];
        foreach ($pdiItems as $row) {
            $items[] = [
                'group' => 'PDI',
                'label' => $row[0],
                'action' => $row[1],
                'results' => ['DONE', 'NOT_DONE'],
            ];
        }

        $this->seedTemplateItems($categoryId, 'PDI', $items);
    }

    private function seedMaintenanceTemplate(int $categoryId): void
    {
        $rows = [];
        $push = function (string $group, string $label) use (&$rows): void {
            $rows[] = [
                'group' => $group,
                'label' => $label,
                'action' => null,
                'results' => ['SI', 'NO'],
            ];
        };

        $push('MANTENIMIENTOS_CORRECTIVOS', 'Mantenimiento basico cada 1500 y 2000 kilometros');
        $push('MANTENIMIENTOS_CORRECTIVOS', 'Mantenimiento intermedio cada 2000 y 4000 kilometros');
        $push('MANTENIMIENTOS_CORRECTIVOS', 'Mantenimiento general cada 5000 kilometros');

        $basic = [
            'Medicion y cambio de aceite',
            'Calibracion de balancines',
            'Limpieza de filtro de aire',
            'Limpieza y calibracion de bujias',
            'Regulacion de frenos y embrague',
            'Regulacion de cadena de arrastre y lubricacion',
            'Lavado de unidad',
        ];
        foreach ($basic as $label) {
            $push('PREVENTIVO_BASICO_MANO_OBRA', $label);
        }

        $intermediate = [
            'Cambio de aceite',
            'Calibracion de balancines',
            'Limpieza de filtro de aire',
            'Limpieza y calibracion de bujias',
            'Limpieza regulacion lubricacion y calibracion de cadena de arrastre',
            'Limpieza y regulacion de carburador o cuerpo de velocidad',
            'Limpieza y lubricacion de cables de embrague y acelerador',
            'Limpieza de pastillas de frenos y zapatas',
            'Limpieza de motor',
            'Lavado de unidad',
        ];
        foreach ($intermediate as $label) {
            $push('PREVENTIVO_INTERMEDIO_MANO_OBRA', $label);
        }

        $general = [
            'Cambio de aceite',
            'Revision relleno y/o cambio de liquido de frenos',
            'Calibracion de balancines',
            'Limpieza y cambio de filtro de aire',
            'Limpieza calibracion o cambio de bujias',
            'Limpieza regulacion lubricacion y calibracion de cadena de arrastre',
            'Limpieza y regulacion de carburador o cuerpo de velocidad',
            'Limpieza lubricacion y/o cambio de cables de embrague y acelerador',
            'Limpieza y/o cambio de pastillas de frenos y zapatas',
            'Limpieza de motor',
            'Mantenimiento de barras relleno hidraulico',
            'Mantenimiento de rodajes de timon',
            'Lavado de unidad',
        ];
        foreach ($general as $label) {
            $push('PREVENTIVO_GENERAL_MANO_OBRA', $label);
        }

        $corrective = [
            'Calibracion de pesetas o buzos reparacion motor',
            'Lavado de motos',
            'Parchado de neumaticos',
            'Reparacion de cabezal',
            'Limpieza de inyectores',
            'Limpieza de cuerpo de aceleracion',
            'Escaneo de motos',
        ];
        foreach ($corrective as $label) {
            $push('CORRECTIVO_MANO_OBRA', $label);
        }

        $this->seedTemplateItems($categoryId, 'MAINTENANCE', $rows);
    }

    private function seedTemplateItems(int $categoryId, string $templateType, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $position = $index + 1;
            $key = "WS_{$templateType}_{$position}";

            DB::table('parameters')->updateOrInsert(
                ['description' => $key, 'parameter_category_id' => $categoryId],
                [
                    'value' => json_encode([
                        'template_type' => $templateType,
                        'group' => $item['group'],
                        'label' => $item['label'],
                        'action' => $item['action'],
                        'result_options' => $item['results'],
                        'order_num' => $position,
                    ]),
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
