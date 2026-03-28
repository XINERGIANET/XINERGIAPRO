<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Abriendo informe inicial</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 24px;">
    <p style="margin: 0 0 10px; font-size: 16px; color: #0f172a;">Abriendo informe inicial...</p>
    <p style="margin: 0; font-size: 14px; color: #475569;">
        Si no se abre automaticamente, haz clic
        <a href="{{ $reportUrl }}" target="_blank" rel="noopener">aqui</a>.
    </p>

    <script>
        (function () {
            const reportUrl = @js($reportUrl);
            const redirectUrl = @js($redirectUrl);
            window.open(reportUrl, '_blank', 'noopener');

            window.location.replace(redirectUrl);
        })();
    </script>
</body>
</html>
