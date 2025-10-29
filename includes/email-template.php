<?php
if (!function_exists('autobid_build_email')) {
    /**
     * Crea una plantilla HTML corporativa unificada para todos los correos de AutoBid Pro.
     *
     * @param string $title    Título principal del correo.
     * @param string $content  Contenido HTML interno (texto, botones, etc.).
     * @param string $footer   Texto opcional del pie (por defecto nombre del sitio y dominio).
     * @return string          HTML completo del correo.
     */
    function autobid_build_email($title, $content, $footer = '') {
        $site_name = get_bloginfo('name');
        $site_url  = home_url();
        $logo_url  = get_option('autobid_logo_url', get_site_icon_url()); // usa el favicon si no hay logo definido
        $main_color = '#007bff';
        $accent_color = '#25D366';
        $footer = $footer ?: "© " . date('Y') . " {$site_name}. Todos los derechos reservados.";

        return "
        <html>
        <body style='margin:0;padding:0;background:#f7f9fb;font-family:Arial,sans-serif;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background:#f7f9fb;padding:30px 0;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.1);overflow:hidden;'>
                            <tr>
                                <td style='background:{$main_color};padding:15px 0;text-align:center;'>
                                    " . ($logo_url ? "<img src='{$logo_url}' alt='{$site_name}' style='max-height:60px;'>" : "<h1 style='color:#fff;margin:0;font-size:22px;'>{$site_name}</h1>") . "
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:30px 40px;color:#2c3e50;'>
                                    <h2 style='color:{$main_color};margin-top:0;font-size:20px;'>{$title}</h2>
                                    <div style='font-size:15px;line-height:1.6;color:#444;'>{$content}</div>
                                </td>
                            </tr>
                            <tr>
                                <td style='background:#f1f3f6;padding:15px 40px;text-align:center;color:#888;font-size:13px;'>
                                    {$footer}<br>
                                    <a href='{$site_url}' style='color:{$main_color};text-decoration:none;'>{$site_url}</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>";
    }
}
