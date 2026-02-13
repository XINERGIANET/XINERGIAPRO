@props(['financialData' => []])

<div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="px-5 pt-5 sm:px-6 sm:pt-6">
        <div class="flex justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Balance Financiero
                </h3>
                <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                    Ingresos vs Egresos del mes
                </p>
            </div>
            <x-common.dropdown-menu />
        </div>
    </div>

    <div class="px-5 pb-6 sm:px-6">
        <div class="relative flex justify-center mt-6">
            <div id="chartBalance" class="w-full max-w-[300px]"></div>
        </div>
    </div>
</div>
