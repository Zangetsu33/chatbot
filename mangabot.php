<?php
/**
 * Plugin Name: Mangabot
 * Description: Un asistente virtual en WooCommerce con chatbot integrado.
 * Version: 1.2.0
 * Author: Tu zangetsu33    
 * Author URI: manganimentai.com
 * Text Domain: chatbot
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit; // Evitar acceso directo

// Incluir funciones adicionales
$file_path = plugin_dir_path(__FILE__) . 'includes/funciones-personalizadas.php';
if (file_exists($file_path)) {
    require_once $file_path;
}

// 🔹 Activación del plugin: Crea la tabla de historial
function activar_mangabot() {
    global $wpdb;
    $tabla_historial = $wpdb->prefix . 'chatbot_historial';
    $charset_collate = $wpdb->get_charset_collate();

    $sql_historial = "CREATE TABLE IF NOT EXISTS $tabla_historial (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        mensaje TEXT NOT NULL,
        respuesta TEXT NOT NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_historial);
}
register_activation_hook(__FILE__, 'activar_mangabot');

// 🔹 Procesar mensajes del chatbot
function procesar_mensaje() {
    if (!isset($_POST['mensaje'])) {
        wp_send_json_error('No se recibió ningún mensaje.');
        wp_die();
    }

    $mensaje = sanitize_text_field($_POST['mensaje']);
    $usuario_id = get_current_user_id() ?: 0;

    $respuesta = ObtenerRespuestaOpenAI($mensaje);
    guardarConversacion($usuario_id, $mensaje, $respuesta);

    wp_send_json_success(['respuesta' => $respuesta]);
    wp_die();
}
add_action('wp_ajax_procesar_mensaje', 'procesar_mensaje');
add_action('wp_ajax_nopriv_procesar_mensaje', 'procesar_mensaje');

function IncluirChatbot() {
    if (get_option('mangabot_activar', '0') == '1') {
        $avatar_url = get_option('mangabot_avatar', plugin_dir_url(__FILE__) . 'admin/img/asistente.jpg');
        $mensaje_bienvenida = esc_js(get_option('mangabot_mensaje_bienvenida', '¡Hola! ¿Cómo puedo ayudarte hoy?'));

        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                // 📌 Crear la burbuja del chatbot
                let chatBubble = document.createElement("div");
                chatBubble.id = "chatBubble";
                chatBubble.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    width: 60px;
                    height: 60px;
                    border-radius: 50%;
                    background: url('<?php echo esc_url($avatar_url); ?>') no-repeat center/cover;
                    cursor: pointer;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                    z-index: 998; /* Debajo del chat */
                    transition: opacity 0.3s ease-in-out;
                `;
                document.body.appendChild(chatBubble);

                // 📌 Contenedor del chat (encima de la burbuja)
                let chatContainer = document.createElement("div");
                chatContainer.id = "chatContainer";
                chatContainer.style.cssText = `
                    position: fixed;
                    bottom: 20px; /* Misma posición que la burbuja */
                    right: 20px;
                    width: 320px;
                    height: 400px;
                    background: #fff;
                    border-radius: 10px;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
                    display: none;
                    flex-direction: column;
                    overflow: hidden;
                    z-index: 999; /* Encima de la burbuja */
                `;

                // 📌 Encabezado del chat con mini-menú
                let chatHeader = document.createElement("div");
                chatHeader.style.cssText = `
                    background: #6A1B9A;
                    color: white;
                    padding: 10px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    position: relative;
                `;
                chatHeader.innerHTML = `
                    <span style="font-weight:bold;">Mangabot</span>
                    <div style="display:flex; align-items:center;">
                        <div id="chatSettings" style="cursor:pointer; font-size: 18px; margin-right: 10px;">⚙️</div>
                        <span id="chatClose" style="cursor:pointer;">×</span>
                    </div>
                `;

                // 📌 Mini-menú de configuración
                let settingsMenu = document.createElement("div");
                settingsMenu.id = "settingsMenu";
                settingsMenu.style.cssText = `
                    display: none;
                    position: absolute;
                    top: 45px;
                    right: 5px;
                    background: white;
                    border: 1px solid #ccc;
                    padding: 10px;
                    border-radius: 5px;
                    box-shadow: 0px 4px 8px rgba(0,0,0,0.2);
                    width: 150px;
                    text-align: left;
                    font-size: 14px;
                    transition: opacity 0.2s ease-in-out;
                `;
                settingsMenu.innerHTML = `
                    <label id="labelDarkMode" style="display: block; margin-bottom: 5px; color: black;">
                        <input type="checkbox" id="toggleDarkMode"> 🌙 Modo Oscuro
                    </label>
                    <label id="labelNotifications" style="display: block; color: black;">
                        <input type="checkbox" id="toggleNotifications"> 🔔 Notificaciones
                    </label>
                `;

                // 📌 Mensajes del chat
                let chatMessages = document.createElement("div");
                chatMessages.id = "chatMessages";
                chatMessages.style.cssText = `
                    flex: 1;
                    padding: 10px;
                    overflow-y: auto;
                    font-size: 14px;
                    border-bottom: 1px solid #ddd;
                `;

                // 📌 Input del chat
                let chatInputContainer = document.createElement("div");
                chatInputContainer.style.cssText = `
                    display: flex;
                    padding: 8px;
                    border-top: 1px solid #ddd;
                `;
                let chatInput = document.createElement("input");
                chatInput.id = "chatTextInput";
                chatInput.type = "text";
                chatInput.placeholder = "Escribe tu mensaje...";
                chatInput.style.cssText = "flex: 1; padding: 8px; border: none; outline: none;";
                let sendButton = document.createElement("button");
                sendButton.innerText = "➤";
                sendButton.style.cssText = "background: #6A1B9A; color: white; border: none; padding: 8px; cursor: pointer;";

                chatInputContainer.appendChild(chatInput);
                chatInputContainer.appendChild(sendButton);

                chatContainer.appendChild(chatHeader);
                chatContainer.appendChild(settingsMenu);
                chatContainer.appendChild(chatMessages);
                chatContainer.appendChild(chatInputContainer);
                document.body.appendChild(chatContainer);

                // 📌 Mostrar chat y ocultar burbuja
                chatBubble.addEventListener("click", function () {
                    chatContainer.style.display = "flex";
                    chatBubble.style.display = "none"; // Ocultar burbuja
                    chatMessages.innerHTML = `<div style="background:#EDE7F6;padding:8px;margin-bottom:8px;border-radius:10px;">${"<?php echo $mensaje_bienvenida; ?>"}</div>`;
                });

                // 📌 Cerrar chat y volver a mostrar la burbuja
                chatHeader.querySelector("#chatClose").addEventListener("click", function () {
                    chatContainer.style.display = "none";
                    chatBubble.style.display = "block"; // Mostrar burbuja de nuevo
                });

                // 📌 Mostrar/Ocultar menú de configuración
                chatHeader.querySelector("#chatSettings").addEventListener("click", function () {
                    settingsMenu.style.display = settingsMenu.style.display === "block" ? "none" : "block";
                });

                // 📌 Modo Oscuro
                let toggleDarkMode = document.getElementById("toggleDarkMode");
                toggleDarkMode.addEventListener("change", function () {
                    document.body.classList.toggle("modo-oscuro", toggleDarkMode.checked);
                    localStorage.setItem("modoOscuro", toggleDarkMode.checked ? "on" : "off");

                    let colorTexto = toggleDarkMode.checked ? "white" : "black";
                    document.getElementById("labelDarkMode").style.color = colorTexto;
                    document.getElementById("labelNotifications").style.color = colorTexto;
                });

                // 📌 Notificaciones
                let toggleNotifications = document.getElementById("toggleNotifications");
                toggleNotifications.addEventListener("change", function () {
                    if (toggleNotifications.checked) {
                        Notification.requestPermission().then(permission => {
                            if (permission !== "granted") {
                                alert("Debes permitir las notificaciones en el navegador.");
                                toggleNotifications.checked = false;
                            }
                        });
                    }
                    localStorage.setItem("notificaciones", toggleNotifications.checked ? "on" : "off");
                });

                // 📌 Cargar configuración guardada
                if (localStorage.getItem("modoOscuro") === "on") {
                    document.body.classList.add("modo-oscuro");
                    toggleDarkMode.checked = true;
                }
                if (localStorage.getItem("notificaciones") === "on") {
                    toggleNotifications.checked = true;
                }
            });
        </script>
        <?php
    }
}

add_action('wp_footer', 'IncluirChatbot');

// 🔹 Menú en el panel de administración
function CrearMenuMangabot() {
    add_menu_page(
        'Mangabot', 'Mangabot', 'manage_options', 'mangabot', 'MostrarMangabotMenu',
        plugin_dir_url(__FILE__) . 'admin/img/asistente.jpg'
    );
}
add_action('admin_menu', 'CrearMenuMangabot');

add_action('admin_head', function() {
    echo '<style>
        #toplevel_page_mangabot .wp-menu-image img { width: 20px !important; height: 20px !important; }
    </style>';
});

// 🔹 Página de configuración del plugin con pestañas (Configuración e Historial)
function MostrarMangabotMenu() {
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'configuracion';
    ?>
    <div class="wrap">
        <h1>⚙️ Panel de Mangabot</h1>

        <h2 class="nav-tab-wrapper">
            <a href="?page=mangabot&tab=configuracion" class="nav-tab <?php echo ($tab == 'configuracion') ? 'nav-tab-active' : ''; ?>">Configuración</a>
            <a href="?page=mangabot&tab=historial" class="nav-tab <?php echo ($tab == 'historial') ? 'nav-tab-active' : ''; ?>">Historial</a>
        </h2>

        <?php
        if ($tab == 'configuracion') {
            MostrarConfiguracionMangabot();
        } else {
            MostrarHistorialMangabot();
        }
        ?>
    </div>
    <?php
}

// 🔹 Mostrar Configuración de Mangabot
function MostrarConfiguracionMangabot() {
    ?>
    <form method="post" action="options.php">
        <?php
        settings_fields('mangabot_opciones');
        do_settings_sections('mangabot');
        submit_button();
        ?>
    </form>
    <?php
}

// 🔹 Mostrar Historial de Conversaciones
function MostrarHistorialMangabot() {
    global $wpdb;
    $tabla_historial = $wpdb->prefix . 'chatbot_historial';

    $por_pagina = 10; 
    $pagina_actual = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($pagina_actual - 1) * $por_pagina;

    $total = $wpdb->get_var("SELECT COUNT(*) FROM $tabla_historial");
    $total_paginas = ceil($total / $por_pagina);

    $historial = $wpdb->get_results("SELECT * FROM $tabla_historial ORDER BY fecha DESC LIMIT $por_pagina OFFSET $offset");

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Usuario</th><th>Mensaje</th><th>Respuesta</th><th>Fecha</th></tr></thead>';
    echo '<tbody>';
    foreach ($historial as $chat) {
        echo "<tr><td>{$chat->id}</td><td>{$chat->usuario_id}</td><td>{$chat->mensaje}</td><td>{$chat->respuesta}</td><td>{$chat->fecha}</td></tr>";
    }
    echo '</tbody></table>';

    echo '<div class="tablenav"><div class="tablenav-pages">';
    for ($i = 1; $i <= $total_paginas; $i++) {
        $active = ($i == $pagina_actual) ? 'page-numbers current' : 'page-numbers';
        echo "<a class='$active' href='?page=mangabot&tab=historial&paged=$i'>$i</a> ";
    }
    echo '</div></div>';
}




// 🔹 Exportar historial a CSV
add_action('admin_post_exportar_historial_mangabot', 'ExportarHistorialMangabot');

function ExportarHistorialMangabot() {
    global $wpdb;
    $tabla_historial = $wpdb->prefix . 'chatbot_historial';

    $historial = $wpdb->get_results("SELECT * FROM $tabla_historial ORDER BY fecha DESC");

    if (empty($historial)) {
        wp_die('No hay datos para exportar.');
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=historial_mangabot.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Usuario', 'Mensaje', 'Respuesta', 'Fecha']);

    foreach ($historial as $chat) {
        fputcsv($output, [$chat->id, $chat->usuario_id, $chat->mensaje, $chat->respuesta, $chat->fecha]);
    }

    fclose($output);
    exit;
}


// 🔹 Registrar opciones del plugin
function RegistrarOpcionesMangabot() {
    register_setting('mangabot_opciones', 'mangabot_activar');
    register_setting('mangabot_opciones', 'mangabot_avatar');
    register_setting('mangabot_opciones', 'mangabot_openai_api_key');
    register_setting('mangabot_opciones', 'mangabot_limpiar_historial');
    register_setting('mangabot_opciones', 'mangabot_mensaje_bienvenida');

    add_settings_section('mangabot_config', 'Configuraciones Básicas', null, 'mangabot');

    add_settings_field('mangabot_activar', 'Activar Mangabot', 'CampoCheckboxMangabot', 'mangabot', 'mangabot_config');
    add_settings_field('mangabot_avatar', 'Seleccionar Avatar', 'CampoAvatarMangabot', 'mangabot', 'mangabot_config');
    add_settings_field('mangabot_openai_api_key', 'Clave API de OpenAI', 'CampoApiKeyMangabot', 'mangabot', 'mangabot_config');
    add_settings_field('mangabot_limpiar_historial', 'Eliminar historial cada (días)', 'CampoLimpiarHistorial', 'mangabot', 'mangabot_config');
    add_settings_field('mangabot_mensaje_bienvenida', 'Mensaje de Bienvenida', 'CampoMensajeBienvenida', 'mangabot', 'mangabot_config');
}
add_action('admin_init', 'RegistrarOpcionesMangabot');

// 🔹 Campo: Checkbox para activar/desactivar el chatbot
function CampoCheckboxMangabot() {
    $valor = get_option('mangabot_activar', '0');
    echo '<input type="checkbox" name="mangabot_activar" value="1" ' . checked(1, $valor, false) . '>';
}

// 🔹 Campo: Selección de avatar
function CampoAvatarMangabot() {
    $avatar_url = get_option('mangabot_avatar', plugin_dir_url(__FILE__) . 'admin/img/asistente.jpg');
    echo '<input type="text" id="mangabot_avatar" name="mangabot_avatar" value="' . esc_url($avatar_url) . '" style="width:300px;" />';
    echo '<button type="button" class="button" id="cargar_avatar">Subir Imagen</button>';
    echo '<br><img id="preview_avatar" src="' . esc_url($avatar_url) . '" style="margin-top:10px;width:80px;height:80px;border-radius:50%;border:1px solid #ccc;" />';
    ?>
    <script>
        jQuery(document).ready(function($) {
            $('#cargar_avatar').click(function(e) {
                e.preventDefault();
                var imageFrame = wp.media({
                    title: 'Seleccionar o Subir Avatar',
                    button: { text: 'Usar esta imagen' },
                    multiple: false
                });
                imageFrame.on('select', function() {
                    var attachment = imageFrame.state().get('selection').first().toJSON();
                    $('#mangabot_avatar').val(attachment.url);
                    $('#preview_avatar').attr('src', attachment.url);
                });
                imageFrame.open();
            });
        });
    </script>
    <?php
}

// 🔹 Campo: Clave API de OpenAI
function CampoApiKeyMangabot() {
    $api_key = get_option('mangabot_openai_api_key', '');
    echo '<input type="password" id="mangabot_openai_api_key" name="mangabot_openai_api_key" value="' . esc_attr($api_key) . '" style="width:300px;" />';
}

// 🔹 Conexión con OpenAI con detección de mangas, animes, hentais, películas y OVAs
function ObtenerRespuestaOpenAI($mensaje) {
    $api_key = get_option('mangabot_openai_api_key', '');
    if (!$api_key) return 'Error: No se ha configurado la clave de API de OpenAI.';

    // 📌 Detectar consultas sobre categorías específicas
    if (preg_match('/(manga|anime|hentai|película|ova)/i', $mensaje, $coincidencia)) {
        $categoria = strtolower($coincidencia[1]); // Detectar categoría
        return ObtenerInformacionCategoria($categoria);
    }

    // 📌 Detectar estrenos, emisiones y finalizaciones
    if (preg_match('/(estrenos|emisiones|finalizaciones)/i', $mensaje, $coincidencia)) {
        return ObtenerEstadoEstrenos();
    }

    // 📌 Consulta normal a OpenAI (respuesta de prueba)
    return "Esta es una respuesta de prueba para: $mensaje";
} 

// 🔹 Obtener información sobre mangas, animes, hentais, películas o OVAs
function ObtenerInformacionCategoria($categoria) {
    $data = [
        'manga'    => "📖 Aquí tienes información sobre los mangas más populares actualmente:\n1️⃣ One Piece\n2️⃣ Chainsaw Man\n3️⃣ Jujutsu Kaisen",
        'anime'    => "🎬 Estos son los animes en tendencia:\n1️⃣ Attack on Titan\n2️⃣ Demon Slayer\n3️⃣ My Hero Academia",
        'hentai'   => "🔥 Hentai más vistos esta semana:\n1️⃣ Fella Pure\n2️⃣ Overflow\n3️⃣ Resort Boin",
        'película' => "🎥 Películas recomendadas:\n1️⃣ Your Name\n2️⃣ Spirited Away\n3️⃣ A Silent Voice",
        'ova'      => "📺 OVAs recientes:\n1️⃣ Code Geass: Akito the Exiled\n2️⃣ Hellsing Ultimate\n3️⃣ Violet Evergarden: Eternity and the Auto Memory Doll"
    ];

    return isset($data[$categoria]) ? $data[$categoria] : "No encontré información sobre $categoria.";
}

// 🔹 Obtener estado de estrenos, emisiones y finalizaciones
function ObtenerEstadoEstrenos() {
    return "📅 Aquí tienes información actualizada:\n\n" .
           "🔸 **Estrenos:** Solo Leveling, Mashle Temporada 2, Frieren\n" .
           "🔸 **Emisión actual:** One Piece, Jujutsu Kaisen, Tokyo Revengers\n" .
           "🔸 **Finalizados recientemente:** Chainsaw Man, Bleach TYBW, Re:Zero Temporada 2";
}

// 🔹 Campo: Mensaje de Bienvenida
function CampoMensajeBienvenida() {
    $mensaje = get_option('mangabot_mensaje_bienvenida', '¡Hola! ¿En qué puedo ayudarte?');
    echo '<input type="text" id="mangabot_mensaje_bienvenida" name="mangabot_mensaje_bienvenida" value="' . esc_attr($mensaje) . '" style="width:100%;" />';
}

function CampoLimpiarHistorial() {
    $dias = get_option('mangabot_limpiar_historial', 30);
    echo "<input type='number' name='mangabot_limpiar_historial' value='$dias' min='1' style='width:60px;' /> días";
}

function LimpiarHistorialAutomatico() {
    global $wpdb;
    $tabla_historial = $wpdb->prefix . 'chatbot_historial';

    $dias = get_option('mangabot_limpiar_historial', 30);
    $wpdb->query("DELETE FROM $tabla_historial WHERE fecha < NOW() - INTERVAL $dias DAY");
}
add_action('wp_scheduled_delete', 'LimpiarHistorialAutomatico');

function ObtenerInformacionDesdeAnilist($consulta) {
    $query = '
        query ($search: String) {
            Media(search: $search, type: ANIME) {
                title {
                    romaji
                    english
                }
                episodes
                status
                description
                coverImage {
                    large
                }
                siteUrl
            }
        }
    ';

    $variables = ['search' => $consulta];

    $response = wp_remote_post('https://graphql.anilist.co', [
        'body'    => json_encode(['query' => $query, 'variables' => $variables]),
        'headers' => ['Content-Type' => 'application/json']
    ]);

    if (is_wp_error($response)) {
        return "Error al obtener información de Anilist.";
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['data']['Media'])) {
        $anime = $body['data']['Media'];
        return "📺 **" . $anime['title']['romaji'] . " (" . $anime['title']['english'] . ")**\n\n" .
               "🔹 **Episodios:** " . $anime['episodes'] . "\n" .
               "🔹 **Estado:** " . ucfirst(strtolower($anime['status'])) . "\n\n" .
               "📜 **Descripción:** " . substr(strip_tags($anime['description']), 0, 300) . "...\n\n" .
               "🔗 [Ver más en Anilist](" . $anime['siteUrl'] . ")";
    }

    return "No encontré información sobre **$consulta** en Anilist.";
}

function ObtenerComandoChatbot($mensaje) {
    $comandos = [
        '/estrenos'   => "📅 **Estrenos recientes:**\n1️⃣ Solo Leveling\n2️⃣ Frieren\n3️⃣ Mashle\n",
        '/popular'    => "🔥 **Tendencias actuales:**\n1️⃣ Attack on Titan\n2️⃣ Jujutsu Kaisen\n3️⃣ Demon Slayer\n",
        '/recomienda' => "🎯 **Recomendación personalizada:** ¿Te gusta la acción? Prueba *Vinland Saga* o *Black Clover*"
    ];

    return isset($comandos[$mensaje]) ? $comandos[$mensaje] : null;
}

function AgregarEstilosModoOscuro() {
    ?>
    <style>
        .modo-oscuro {
            background-color: #121212 !important;
            color: #fff !important;
        }
        .modo-oscuro #chatContainer {
            background-color: #1E1E1E !important;
            border: 1px solid #333;
        }
        .modo-oscuro #chatHeader {
            background-color: #6A1B9A !important;
        }
    </style>
    <?php
}
add_action('wp_footer', 'AgregarEstilosModoOscuro');

function AgregarNotificaciones() {
    ?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (!("Notification" in window)) return;
            Notification.requestPermission();

            function mostrarNotificacion(mensaje) {
                if (Notification.permission === "granted") {
                    new Notification("Mangabot", { body: mensaje, icon: "<?php echo plugin_dir_url(__FILE__) . 'admin/img/asistente.jpg'; ?>" });
                }
            }

            document.getElementById("sendMessage").addEventListener("click", function () {
                setTimeout(() => mostrarNotificacion("Mangabot ha respondido a tu mensaje."), 2000);
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'AgregarNotificaciones');
