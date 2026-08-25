<?php
/**
 * Ruta: /app/Views/emails/layout.php
 * Plantilla base de los correos. HTML con estilos en línea para máxima
 * compatibilidad entre clientes de correo.
 *
 * @var array  $data
 * @var string $body   HTML del contenido
 * @var string $heading
 */

$business = $data['business'] ?? config('app.name');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($heading ?? $business) ?></title>
</head>
<body style="margin:0;padding:0;background:#F4F4F4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#181818">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F4F4;padding:24px 12px">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFDF5;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(24,24,24,.08)">

                <tr>
                    <td style="background:#0D0D0D;padding:26px 28px;text-align:center">
                        <div style="font-size:22px;font-weight:800;letter-spacing:-.5px;color:#FFFDF5">
                            FLAVA <span style="color:#FFC400">STUDIO</span>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:30px 28px">
                        <?= $body ?>
                    </td>
                </tr>

                <tr>
                    <td style="background:#181818;padding:22px 28px;text-align:center;color:rgba(255,253,245,.6);font-size:12px;line-height:1.7">
                        <?php if (!empty($data['address'])): ?>
                            <div><?= e($data['address']) ?></div>
                        <?php endif; ?>
                        <div><a href="<?= e(config('app.url')) ?>" style="color:#FFC400;text-decoration:none"><?= e(str_replace('https://', '', (string) config('app.url'))) ?></a></div>
                        <div style="margin-top:8px">Este correo es sobre tu reserva en <?= e($business) ?>.</div>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
