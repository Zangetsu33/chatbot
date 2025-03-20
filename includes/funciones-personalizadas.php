<?php

if (!defined('ABSPATH')) {
    exit;
}

// ✅ FUNCIÓN PARA OBTENER RESPUESTA DEL CHATBOT (CON OPENAI MEJORADO)
function obtenerRespuesta($mensaje) {
    global $wpdb;

    // Detectar si el mensaje menciona anime, manga, etc.
    if (preg_match('/\banime\b|\bmanga\b|\bhentai\b|\bova\b|\bpelícula\b|\bserie\b/i', $mensaje)) {
        return obtener_items_especificos($mensaje);
    }

    // Buscar en la base de datos de intenciones
    $intencion = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM intents WHERE pregunta LIKE %s",
        "%" . $wpdb->esc_like($mensaje) . "%"
    ));

    if ($intencion) {
        $respuesta = $wpdb->get_var($wpdb->prepare(
            "SELECT respuesta FROM responses WHERE intent_id = %d ORDER BY RAND() LIMIT 1",
            $intencion->id
        ));
        return $respuesta ?: "No tengo una respuesta para eso.";
    }

    // Si no hay respuesta en la BD, usar OpenAI con contexto
    $usuario_id = get_current_user_id() ?: 0;
    return ObtenerRespuestaOpenAI($mensaje, $usuario_id);
}

// ✅ MEJOR BÚSQUEDA DE MANGAS, ANIMES Y PELÍCULAS EN WOOCOMMERCE
function obtener_items_especificos($consulta) {
    $args = [
        'post_type' => 'product',
        'posts_per_page' => 5,
        's' => $consulta,
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => ['manga', 'anime', 'hentai', 'ovas', 'peliculas', 'series'],
            ],
        ],
    ];
    
    $items = get_posts($args);

    if (empty($items)) {
        return "No encontré títulos relacionados con tu búsqueda.";
    }

    $respuesta = "Aquí tienes algunos títulos:\n";
    foreach ($items as $item) {
        $url = get_permalink($item->ID);
        $precio = get_post_meta($item->ID, '_price', true);
        $imagen = get_the_post_thumbnail_url($item->ID, 'thumbnail') ?: 'https://via.placeholder.com/50x75';

        $respuesta .= "<div style='margin-bottom: 10px; display: flex; align-items: center;'>
            <img src='$imagen' style='width: 50px; height: 75px; margin-right: 10px;'>
            <a href='$url' target='_blank'><strong>{$item->post_title}</strong></a> - Precio: $$precio
        </div>";
    }

    return $respuesta;
}

// ✅ EVITAR LA DOBLE DECLARACIÓN DE `guardarConversacion()`
if (!function_exists('guardarConversacion')) {
    function guardarConversacion($usuario_id, $mensaje, $respuesta) {
        global $wpdb;
        $tabla_historial = $wpdb->prefix . 'chatbot_historial';

        // Determinar la categoría del mensaje
        $categoria = analizarGustosUsuario($usuario_id);

        $wpdb->insert($tabla_historial, [
            'usuario_id' => $usuario_id,
            'mensaje' => $mensaje,
            'respuesta' => $respuesta,
            'categoria' => $categoria,
            'fecha' => current_time('mysql')
        ], ['%d', '%s', '%s', '%s', '%s']);
    }
}

// ✅ FUNCIÓN PARA TRADUCIR RESPUESTAS USANDO OPENAI
function traducir_respuesta($texto, $idioma_origen, $idioma_destino) {
    if ($idioma_origen === $idioma_destino) {
        return $texto;
    }

    $api_key = get_option('mangabot_openai_api_key', '');
    if (!$api_key) {
        return "Error: No se ha configurado la clave de API.";
    }

    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => "Traduce el siguiente texto de {$idioma_origen} a {$idioma_destino}."],
            ['role' => 'user', 'content' => $texto]
        ]
    ];

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'body' => json_encode($data),
        'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key],
        'method' => 'POST',
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        return 'Error en la conexión con OpenAI.';
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    return $result['choices'][0]['message']['content'] ?? 'No se obtuvo traducción.';
}

// ✅ MOSTRAR HISTORIAL DEL USUARIO
function mostrar_historial_chatbot() {
    if (!is_user_logged_in()) {
        return "<p>Debes iniciar sesión para ver tu historial de conversaciones.</p>";
    }

    global $wpdb;
    $usuario_id = get_current_user_id();
    $tabla_historial = $wpdb->prefix . 'chatbot_historial';
    $historial = $wpdb->get_results("SELECT * FROM $tabla_historial WHERE usuario_id = $usuario_id ORDER BY fecha DESC");

    if (!$historial) {
        return "<p>No hay historial de conversaciones.</p>";
    }

    $output = "<h3>Historial de conversaciones</h3><ul>";
    foreach ($historial as $chat) {
        $output .= "<li><strong>Tú:</strong> {$chat->mensaje}<br><strong>Mangabot:</strong> {$chat->respuesta}<br><small>{$chat->fecha}</small></li>";
    }
    $output .= "</ul>";

    return $output;
}
add_shortcode('historial_chatbot', 'mostrar_historial_chatbot');

// ✅ REINICIAR EL HISTORIAL DEL CHAT
function reiniciar_chatbot() {
    global $wpdb;
    $usuario_id = get_current_user_id();
    if (!$usuario_id) {
        wp_send_json_error(['error' => 'No estás autenticado.']);
        wp_die();
    }

    $wpdb->delete($wpdb->prefix . 'chatbot_historial', ['usuario_id' => $usuario_id]);
    wp_send_json_success(['message' => 'Chatbot reiniciado.']);
    wp_die();
}
add_action('wp_ajax_reiniciar_chatbot', 'reiniciar_chatbot');
add_action('wp_ajax_nopriv_reiniciar_chatbot', 'reiniciar_chatbot');

?>

<!-- ✅ Script para ocultar la burbuja cuando el chat está activo -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const chatContainer = document.getElementById("chatbot-container");
    const chatBubble = document.getElementById("chatbot-bubble");
    const closeChat = document.getElementById("close-chat");

    if (chatBubble && chatContainer && closeChat) {
        // 📌 Al hacer clic en la burbuja, ocultarla y mostrar el chat sobre ella
        chatBubble.addEventListener("click", function () {
            chatBubble.style.display = "none"; // Ocultar la burbuja
            chatContainer.classList.add("show"); // Mostrar el chat
        });

        // 📌 Al cerrar el chat, volver a mostrar la burbuja en su posición original
        closeChat.addEventListener("click", function () {
            chatBubble.style.display = "block"; // Mostrar la burbuja nuevamente
            chatContainer.classList.remove("show"); // Ocultar el chat
        });
    }
});
</script>

