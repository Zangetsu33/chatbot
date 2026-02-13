document.addEventListener("DOMContentLoaded", function () {
    console.log("✅ Script cargado correctamente");

    let avatarUrl = "<?php echo esc_url($avatar_url); ?>";

    // 📌 Crear la burbuja flotante del chatbot
    let chatBubble = document.createElement("div");
    chatBubble.id = "chatBubble";
    Object.assign(chatBubble.style, {
        position: "fixed",
        bottom: "20px",
        right: "20px",
        width: "60px",
        height: "60px",
        borderRadius: "50%",
        background: `url('${avatarUrl}') no-repeat center/cover`,
        cursor: "pointer",
        boxShadow: "0 4px 8px rgba(0,0,0,0.2)",
        zIndex: "999"
    });

    document.body.appendChild(chatBubble);

    // 📌 Crear el Chatbox (oculto por defecto)
    let chatBox = document.createElement("div");
    chatBox.id = "chatBox";
    Object.assign(chatBox.style, {
        position: "fixed",
        bottom: "80px",
        right: "20px",
        width: "320px",
        height: "450px",
        background: "#fff",
        border: "1px solid #ccc",
        boxShadow: "0 4px 8px rgba(0,0,0,0.2)",
        borderRadius: "10px",
        overflow: "hidden",
        display: "none",
        transition: "opacity 0.3s, transform 0.3s",
        transform: "scale(0)",
        zIndex: "1001",
        display: "flex",
        flexDirection: "column"
    });

    document.body.appendChild(chatBox);

    // 📌 Cabecera del Chatbox
    let chatHeader = document.createElement("div");
    Object.assign(chatHeader.style, {
        backgroundColor: "#0a4ea3",
        color: "#fff",
        padding: "10px",
        display: "flex",
        justifyContent: "space-between",
        alignItems: "center",
        fontWeight: "bold"
    });

    let chatTitle = document.createElement("span");
    chatTitle.innerText = "Mangabot";

    // 📌 Botón de Cerrar ❌
    let closeButton = document.createElement("button");
    closeButton.innerText = "×";
    Object.assign(closeButton.style, {
        background: "transparent",
        border: "none",
        color: "#fff",
        fontSize: "20px",
        cursor: "pointer"
    });
    closeButton.addEventListener("click", function () {
        cerrarChat();
    });

    chatHeader.appendChild(chatTitle);
    chatHeader.appendChild(closeButton);
    chatBox.appendChild(chatHeader);

    // 📌 Contenedor de Mensajes
    let messageContainer = document.createElement("div");
    Object.assign(messageContainer.style, {
        flex: "1",
        overflowY: "auto",
        padding: "10px",
        backgroundColor: "#f8f9fa"
    });

    chatBox.appendChild(messageContainer);

    // 📌 Contenedor de Preguntas Sugeridas
    let suggestionsContainer = document.createElement("div");
    Object.assign(suggestionsContainer.style, {
        display: "none",
        flexWrap: "wrap",
        padding: "10px"
    });

    chatBox.appendChild(suggestionsContainer);

    // 📌 Input y Botón de Enviar
    let inputContainer = document.createElement("div");
    Object.assign(inputContainer.style, {
        display: "flex",
        borderTop: "1px solid #ccc",
        padding: "10px"
    });

    let inputField = document.createElement("input");
    inputField.type = "text";
    inputField.placeholder = "Escribe aquí...";
    Object.assign(inputField.style, {
        flex: "1",
        padding: "5px",
        borderRadius: "5px",
        border: "1px solid #ccc"
    });

    let sendButton = document.createElement("button");
    sendButton.innerText = "Enviar";
    Object.assign(sendButton.style, {
        marginLeft: "5px",
        padding: "5px",
        borderRadius: "5px",
        cursor: "pointer",
        backgroundColor: "#0a4ea3",
        color: "#fff",
        border: "none"
    });

    inputContainer.appendChild(inputField);
    inputContainer.appendChild(sendButton);
    chatBox.appendChild(inputContainer);

    // 📌 Función para enviar mensaje
    function enviarMensaje(mensaje) {
        if (mensaje.trim() === "") return;

        appendMessage("Usuario", mensaje);
        mostrarSugerencias(mensaje);

        // Enviar mensaje a PHP vía AJAX
        fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=mangabot_chat&mensaje=" + encodeURIComponent(mensaje)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                appendMessage("Mangabot", data.data.respuesta);
            } else {
                appendMessage("Mangabot", "❌ Error en la respuesta.");
            }
        })
        .catch(() => appendMessage("Mangabot", "⚠ No se pudo conectar con el servidor."));
    }

    // 📌 Agregar mensajes al chat
    function appendMessage(sender, message) {
        let messageElement = document.createElement("div");
        Object.assign(messageElement.style, {
            background: sender === "Usuario" ? "#d1e7ff" : "#f1f1f1",
            padding: "8px",
            borderRadius: "5px",
            marginTop: "5px",
            maxWidth: "80%",
            wordWrap: "break-word"
        });

        messageElement.innerHTML = `<strong>${sender}:</strong> ${message}`;
        messageContainer.appendChild(messageElement);
        messageContainer.scrollTop = messageContainer.scrollHeight;
        inputField.value = "";
    }

    // 📌 Mostrar preguntas sugeridas
    function mostrarSugerencias(palabraClave) {
        let sugerencias = {
            "anime": ["¿Cuáles son los animes más populares?", "Recomiéndame un anime de acción", "¿Dónde ver anime?"],
            "manga": ["Recomiéndame un manga de romance", "¿Qué manga está en tendencia?", "¿Cuánto cuesta el manga One Piece?"],
            "hentai": ["Recomiéndame un hentai", "¿Cuáles son los hentais más vistos?", "¿Dónde comprar hentais?"],
            "película": ["Dime una película anime famosa", "¿Dónde ver películas anime?", "¿Cuánto cuesta la película de Demon Slayer?"]
        };

        suggestionsContainer.innerHTML = "";
        let coincidencias = Object.keys(sugerencias).filter(tema => palabraClave.toLowerCase().includes(tema));

        if (coincidencias.length > 0) {
            suggestionsContainer.style.display = "flex";
            coincidencias.forEach(tema => {
                sugerencias[tema].forEach(pregunta => {
                    let btn = document.createElement("button");
                    btn.innerText = pregunta;
                    Object.assign(btn.style, {
                        margin: "5px",
                        padding: "5px",
                        border: "none",
                        backgroundColor: "#0a4ea3",
                        color: "white",
                        borderRadius: "5px",
                        cursor: "pointer"
                    });

                    btn.addEventListener("click", function () {
                        enviarMensaje(pregunta);
                        suggestionsContainer.style.display = "none";
                    });

                    suggestionsContainer.appendChild(btn);
                });
            });
        } else {
            suggestionsContainer.style.display = "none";
        }
    }

    // 📌 Evento para abrir el chat
    chatBubble.addEventListener("click", function () {
        chatBubble.style.display = "none";
        chatBox.style.display = "flex";
        setTimeout(() => {
            chatBox.style.opacity = "1";
            chatBox.style.transform = "scale(1)";
        }, 10);
    });

    console.log("✅ Chatbot con preguntas sugeridas activado.");
});


