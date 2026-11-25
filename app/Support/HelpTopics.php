<?php

namespace App\Support;

/**
 * Ayuda de cada pantalla del panel: pasos cortos y en simple para quien la usa por primera vez.
 * Clave = nombre de la ruta (se prueba la exacta y luego el grupo, p. ej. admin.productos.*).
 */
class HelpTopics
{
    public const TOPICS = [
        'admin.dashboard' => ['Tu panel', [
            'Arriba tienes los <b>accesos rápidos</b>: toca lo que necesitas hacer (vender, ver stock, publicar…).',
            'Solo ves los botones de lo que tu cargo permite. Si te falta algo, pídeselo al administrador.',
            'En el menú de la izquierda (☰ en el celular) están todas las secciones.',
            'En <b>Mi actividad</b> ves todo lo que hiciste; nada se pierde.',
        ]],
        'admin.ventas.create' => ['Registrar una venta', [
            '<b>1.</b> Busca el modelo por código o nombre, o escanea la etiqueta 📷. Toca la <b>talla</b> que se lleva.',
            '<b>2.</b> Si te da su celular, se guarda como cliente (opcional, pero ayuda a venderle otra vez).',
            '<b>3.</b> Elige cómo pagó (efectivo, QR…). En efectivo escribe cuánto te dio y te calcula el cambio.',
            'Toca <b>✔ Registrar venta</b>. El stock se descuenta solo y entra a la caja del día.',
            '¿Se equivocó de talla después? En la venta usa <b>Cambio de talla</b>; no hace falta anular.',
        ]],
        'admin.ventas.index' => ['Ventas', [
            'Aquí están todas las ventas. Filtra por fecha, tienda o método de pago.',
            'Toca una venta para ver el detalle, imprimir, hacer un <b>cambio de talla</b> o anularla.',
            'Anular devuelve los pares al stock y lo anota en la caja.',
        ]],
        'admin.stock.index' => ['Consultar stock', [
            'En <b>Consulta rápida</b> escribe el código o nombre: verás todas las tallas y colores disponibles.',
            'Los números en verde están disponibles; los apartados ya no se pueden vender.',
            'Para cargar pares nuevos toca <b>➕ Ingresar calzado</b> (arriba a la derecha): sirve para modelos que ya existen y para modelos nuevos.',
        ]],
        'admin.stock.ingresar' => ['Ingresar calzado', [
            '<b>1.</b> Elige la tienda (si eres admin) y busca el modelo por código o nombre. Si nunca lo registraste, toca <b>➕ Modelo nuevo</b> y llena nombre, clase y precio.',
            '<b>2.</b> Marca los colores y las tallas que llegaron (puedes agregar colores o tallas que no estén en la lista).',
            '<b>3.</b> En la tabla escribe cuántos pares llegaron de cada color y talla. Debajo ves cuántos <i>hay</i> ahora: lo que escribas se <b>suma</b>.',
            'Toca <b>Guardar ingreso</b>. Queda en el kardex como entrada, con tu nombre. Después puedes imprimir etiquetas o publicarlo.',
            'Esto es lo mismo que hace el asistente, pero a mano: si el asistente no te entiende, usa esta pantalla.',
        ]],
        'admin.productos.index' => ['Productos', [
            'Toca <b>+ Nuevo producto</b> para crear un modelo: nombre, código, clase y precio.',
            'Después de crearlo, genera sus <b>tallas y colores</b> y carga el stock.',
            'Usa ✏️ <b>Editar</b> en las fotos para ponerles fondo blanco: se venden mejor.',
        ]],
        'admin.productos.create' => ['Nuevo producto', [
            'Llena nombre, código (el de la etiqueta), clase y precio. Lo demás es opcional.',
            'Agrega fotos y toca <b>✏️ Editar → 🪄 Fondo → Quitar fondo</b> para dejarlas en fondo blanco.',
            'Al guardar, te lleva a generar las tallas y colores del modelo.',
        ]],
        'admin.productos.edit' => ['Editar producto', [
            'Cambia lo que necesites y toca <b>Guardar</b>. Los cambios de precio quedan en el historial.',
            'Las fotos nuevas se pueden editar antes de subir (✏️ Editar).',
        ]],
        'admin.productos.variantes' => ['Tallas, colores y stock', [
            'Elige las tallas y colores que tiene el modelo y toca <b>Generar</b>.',
            'Escribe cuántos pares hay de cada talla en tu tienda y guarda.',
        ]],
        'admin.publicaciones.index' => ['Publicaciones', [
            'Toca <b>+ Nueva publicación</b> para publicar en la página de tu tienda.',
            'Puedes editar las que ya están. Para que deje de verse, desmarca <b>Visible en la página</b>.',
        ]],
        'admin.publicaciones.create' => ['Nueva publicación', [
            '<b>1.</b> Escribe un título y un texto corto. Puedes etiquetar un producto para que salga con su precio.',
            '<b>2.</b> Agrega fotos o videos. Toca <b>✏️ Editar</b>: fondo blanco o de color, recortar, brillo, textos.',
            'Para videos usa <b>✨ Crear video con el editor</b>: junta clips, recorta, pon texto y música (como CapCut).',
            '<b>3.</b> Elige el <b>color</b> de la publicación. A la derecha ves cómo va a quedar.',
            '<b>4.</b> Toca <b>✔ Publicar</b>. Si pones una fecha futura, sale sola ese día.',
        ]],
        'admin.publicaciones.edit' => ['Editar publicación', [
            'Cambia el texto, el color o agrega más fotos y videos. Toca <b>✔ Guardar cambios</b>.',
            'Para quitar una foto o video ya publicado toca <b>Quitar</b> sobre ella.',
        ]],
        'admin.caja' => ['Caja del día', [
            'Al empezar el día toca <b>Abrir caja</b> y escribe con cuánto efectivo empiezas.',
            'Las ventas en efectivo entran solas. Si sacas o metes plata (pasajes, cambio), regístralo como movimiento.',
            'Al cerrar, cuenta el efectivo: el sistema te dice si sobra o falta.',
        ]],
        'admin.apartados.index' => ['Apartados', [
            'Son calzados separados para un cliente, con o sin adelanto. Los pedidos de la página también llegan aquí.',
            'Cuando el cliente viene, toca <b>Entregar</b> y cobra el saldo. Si no viene, <b>Cancelar</b> devuelve el par al stock.',
        ]],
        'admin.apartados.create' => ['Apartar un calzado', [
            'Busca el modelo, toca la talla, escribe el celular del cliente y el adelanto (puede ser 0).',
            'El par queda reservado: nadie más lo puede vender hasta la fecha límite.',
        ]],
        'admin.traspasos.index' => ['Traspasos entre tiendas', [
            'Para mandar pares a otra tienda toca <b>+ Nuevo traspaso</b>.',
            'La tienda que recibe debe tocar <b>Recibir</b> cuando llegan; recién ahí suben a su stock.',
        ]],
        'admin.traspasos.create' => ['Nuevo traspaso', [
            'Elige a qué tienda va, agrega los pares (buscando o escaneando) y envía.',
        ]],
        'admin.inventario.index' => ['Inventario físico', [
            'Sirve para contar lo que hay de verdad en la tienda y corregir diferencias.',
            'Abre un conteo, escanea cada par y al final ciérralo: el sistema ajusta el stock y guarda el informe.',
        ]],
        'admin.compras.index' => ['Compras al proveedor', [
            'Cada vez que llega mercadería, regístrala aquí: suma el stock y guarda el costo.',
            'Las <b>curvas de tallas</b> te ahorran escribir talla por talla (ej. docena 36-41).',
        ]],
        'admin.compras.create' => ['Nueva compra', [
            'Elige el proveedor, agrega cada modelo con su color y aplica una curva de tallas o escribe las cantidades.',
            'Al guardar, el stock sube y el costo del modelo se actualiza.',
        ]],
        'admin.crm' => ['Seguimientos de clientes', [
            'Aquí están las tareas para volver a contactar clientes (llamar, avisar que llegó su talla…).',
            '<b>Esperan su talla</b>: clientes que pidieron aviso en la página. Escríbeles cuando llegue.',
        ]],
        'admin.clientes.index' => ['Clientes', [
            'Busca por nombre o celular. Toca un cliente para ver sus compras, consultas y lo que hizo en la página.',
        ]],
        'admin.etiquetas' => ['Etiquetas', [
            'Elige el modelo y cuántas etiquetas quieres; imprime en hoja A4 y pégalas en las cajas.',
            'Con la etiqueta puesta, vender es solo escanear.',
        ]],
        'admin.pronostico' => ['Pronóstico de ventas', [
            'Estima cuánto se venderá los próximos meses según las ventas pasadas y las temporadas (Navidad, lluvias, clases…).',
            'Usa la sugerencia de compra por talla para pedir al proveedor lo que más sale.',
        ]],
        'admin.auditoria' => ['Historial de actividad', [
            'Todo lo que hace cada persona queda anotado solo: ventas, publicaciones, cambios de precio, ingresos…',
            'Filtra por persona, por fecha o busca una palabra (por ejemplo el código de una venta).',
            'Debajo de cada acción ves qué datos cambiaron (antes → después).',
            'Las filas en rojo son intentos fallidos de entrar al panel.',
        ]],
        'admin.mi-actividad' => ['Mi actividad', [
            'Aquí ves todo lo que hiciste en el sistema, con fecha y hora.',
            'Si ves algo que no hiciste, cambia tu contraseña y avisa al administrador.',
        ]],
        'admin.usuarios.index' => ['Usuarios del equipo', [
            '<b>Administrador</b>: todo, incluido eliminar. <b>Supervisor</b>: casi todo en su tienda menos eliminar. <b>Vendedor</b>: vende, consulta stock, atiende clientes y publica.',
            'Toca <b>Actividad</b> para ver lo que hizo cada persona.',
        ]],
        'admin.colores.index' => ['Colores de la tienda', [
            'Elige los colores de la página de cada tienda. Los cambios se ven al instante.',
        ]],
        'admin.leads.index' => ['Consultas por WhatsApp', [
            'Cada vez que alguien toca "Consultar" en la página queda aquí. Márcalas como atendidas al responder.',
        ]],
        'admin.comentarios.index' => ['Comentarios', [
            'Responde a los clientes desde aquí. Si un comentario es ofensivo, <b>Ocúltalo</b>.',
        ]],
        'admin.asistente' => ['Asistente', [
            'Escribe o habla como si fuera una persona: "vendí un Chelsea 40 negro en efectivo", "¿hay 38 del CH-210?".',
            'Antes de guardar algo te pide confirmar.',
            'Si no te entiende, toca el botón <b>✋ Hacerlo a mano</b> que aparece debajo de su respuesta: abre la pantalla para hacerlo tú mismo.',
            'Para ingresar calzados a mano: menú <b>Stock → ➕ Ingresar calzado</b>.',
        ]],
        'admin.asistente.ensenar' => ['Enseñar al asistente', [
            '<b>Probar una frase</b>: escribe algo como lo diría el equipo y mira qué entiende.',
            '<b>Lo que no entendió</b>: elige qué quería hacer la persona y toca Enseñar. La próxima vez lo entiende.',
            '<b>Palabras</b>: colores en portugués (preto = Negro), marcas, clases y sinónimos (depósito = stock).',
            '<b>Frases de ejemplo</b>: 2 o 3 formas de pedir cada cosa bastan para que reconozca las parecidas.',
        ]],
        'admin.asistente.equipo' => ['Chats del equipo', [
            'Aquí ves lo que cada persona le pidió al asistente y lo que respondió (solo lectura).',
            'Sirve para ver dónde se confunde y enseñarle al equipo a pedirlo mejor, o usar la pantalla manual.',
            'Lo que alguien ocultó con «Nueva conversación» aparece más claro, pero sigue aquí.',
        ]],
    ];

    /** [título, pasos] de la pantalla actual, o null si no tiene ayuda. */
    public static function current(): ?array
    {
        $name = request()->route()?->getName();
        if (! $name) {
            return null;
        }

        return self::TOPICS[$name] ?? null;
    }
}
