<script>
(function () {
    if (window.__workshopProductQuickCreateBound) {
        return;
    }
    window.__workshopProductQuickCreateBound = true;

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-workshop-product-quick-form]');
        if (!form) {
            return;
        }

        event.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');
        const originalHtml = submitButton ? submitButton.innerHTML : '';
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span>Guardando...</span>';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: new FormData(form),
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload?.status || !payload?.product) {
                const errors = payload?.errors ? Object.values(payload.errors).flat().join(' ') : '';
                throw new Error(errors || payload?.message || 'No se pudo registrar el producto.');
            }

            window.dispatchEvent(new CustomEvent('workshop-product-created', { detail: payload.product }));
            window.dispatchEvent(new CustomEvent('close-product-modal'));
            window.dispatchEvent(new CustomEvent('close-product-type-selector'));
            form.reset();
        } catch (error) {
            window.alert(error?.message || 'No se pudo registrar el producto.');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalHtml;
            }
        }
    });
})();
</script>
