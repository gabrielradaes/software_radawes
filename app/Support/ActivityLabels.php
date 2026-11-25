<?php

namespace App\Support;

/**
 * Cómo se lee cada acción en el historial ("Registró una venta", "Publicó"…).
 * Clave = nombre de la ruta, o su dirección cuando la ruta no tiene nombre.
 */
class ActivityLabels
{
    /** Acciones que no se anotan (automáticas o demasiado repetitivas). */
    public const SKIP = [
        'deploy', 'deploy.status', 'deploy.vendor', 'storage.local.upload',
        'admin.inventario.escanear',   // cada código escaneado; el cierre del conteo sí se anota
        'admin.asistente.mensaje',     // la conversación; lo que confirma sí se anota
        'admin.asistente.cancelar',
    ];

    public const LABELS = [
        // Sesión
        'admin/login' => 'Inició sesión en el panel',
        'admin.logout' => 'Cerró sesión',
        'admin/recuperar' => 'Pidió un código para recuperar su contraseña',
        'admin/recuperar/codigo' => 'Cambió su contraseña con el código',
        // Ventas y caja
        'admin.ventas.store' => 'Registró una venta',
        'admin.ventas.anular' => 'Anuló una venta',
        'admin.ventas.cambio' => 'Hizo un cambio de talla / devolución',
        'admin.caja.abrir' => 'Abrió la caja',
        'admin.caja.cerrar' => 'Cerró la caja',
        'admin.caja.movimiento' => 'Registró un movimiento de caja',
        'admin.apartados.store' => 'Apartó un calzado',
        'admin.apartados.retirar' => 'Entregó un apartado',
        'admin.apartados.cancelar' => 'Canceló un apartado',
        // Stock
        'admin.stock.move' => 'Movió stock',
        'admin.stock.ingresar.store' => 'Ingresó calzado al stock',
        'admin.asistente.ensenar.palabra' => 'Le enseñó una palabra al asistente',
        'admin.asistente.ensenar.frase' => 'Le enseñó una frase al asistente',
        'admin.asistente.ensenar.palabra.quitar' => 'Quitó una palabra del asistente',
        'admin.asistente.ensenar.frase.quitar' => 'Quitó una frase del asistente',
        'admin.compras.store' => 'Registró una compra al proveedor',
        'admin.proveedores.store' => 'Agregó un proveedor',
        'admin.proveedores.update' => 'Editó un proveedor',
        'admin.curvas.store' => 'Creó una curva de tallas',
        'admin.curvas.destroy' => 'Eliminó una curva de tallas',
        'admin.traspasos.store' => 'Envió un traspaso',
        'admin.traspasos.recibir' => 'Recibió un traspaso',
        'admin.traspasos.cancelar' => 'Canceló un traspaso',
        'admin.inventario.store' => 'Empezó un inventario físico',
        'admin.inventario.cerrar' => 'Cerró un inventario físico',
        'admin.inventario.cancelar' => 'Canceló un inventario físico',
        // Catálogo
        'admin.productos.store' => 'Creó un producto',
        'admin.productos.update' => 'Editó un producto',
        'admin.productos.destroy' => 'Eliminó un producto',
        'admin.productos.imagenes.store' => 'Subió fotos de un producto',
        'admin.imagenes.principal' => 'Cambió la foto principal de un producto',
        'admin.imagenes.destroy' => 'Quitó una foto de un producto',
        'admin.productos.variantes.generar' => 'Generó tallas y colores de un producto',
        'admin.productos.variantes.stock' => 'Cargó stock por talla',
        'admin.variantes.update' => 'Editó una variante',
        'admin.variantes.destroy' => 'Quitó una variante',
        'admin.categorias.store' => 'Creó una clase',
        'admin.categorias.update' => 'Editó una clase',
        'admin.categorias.destroy' => 'Eliminó una clase',
        'admin.colores.store' => 'Cambió los colores de la tienda',
        'theme.save' => 'Cambió los colores de la tienda',
        // Contenido
        'admin.publicaciones.store' => 'Publicó en la página',
        'admin.publicaciones.update' => 'Editó una publicación',
        'admin.publicaciones.destroy' => 'Eliminó una publicación',
        'admin.media.destroy' => 'Quitó una foto o video de una publicación',
        'admin.comentarios.toggle' => 'Aprobó u ocultó un comentario',
        'admin.comentarios.reply' => 'Respondió un comentario',
        'admin.comentarios.destroy' => 'Eliminó un comentario',
        // Clientes
        'admin.clientes.store' => 'Registró un cliente',
        'admin.clientes.update' => 'Editó un cliente',
        'admin.clientes.crm' => 'Cambió la etapa o etiquetas de un cliente',
        'admin.clientes.historial' => 'Anotó algo en el historial de un cliente',
        'admin.crm.tareas.store' => 'Agendó un seguimiento',
        'admin.crm.tareas.toggle' => 'Marcó un seguimiento',
        'admin.crm.tareas.destroy' => 'Eliminó un seguimiento',
        'admin.leads.update' => 'Atendió una consulta de WhatsApp',
        // Administración
        'admin.usuarios.store' => 'Creó un usuario',
        'admin.usuarios.update' => 'Editó un usuario',
        'admin.usuarios.destroy' => 'Eliminó un usuario',
        'admin.sucursales.store' => 'Creó una sucursal',
        'admin.sucursales.update' => 'Editó una sucursal',
        'admin.sucursales.destroy' => 'Eliminó o desactivó una sucursal',
        'admin.respaldos.store' => 'Hizo una copia de seguridad',
        'admin.asistente.confirmar' => 'Confirmó una orden del asistente',
        'admin.asistente.limpiar' => 'Borró su conversación con el asistente',
        // Clientes en la página
        '{branch}/registro' => 'Se registró en la página',
        '{branch}/ingresar' => 'Ingresó a su cuenta',
        '{branch}/verificar' => 'Verificó su Gmail',
        'customer.verify.resend' => 'Pidió otro código de verificación',
        '{branch}/recuperar' => 'Pidió recuperar su contraseña',
        '{branch}/recuperar/codigo' => 'Cambió su contraseña con el código',
        'customer.logout' => 'Salió de su cuenta',
        'customer.account.update' => 'Actualizó sus datos',
        'customer.password' => 'Cambió su contraseña',
        'products.reserve' => 'Separó un calzado desde la página',
        'products.restock' => 'Pidió aviso cuando llegue su talla',
        'comment.store' => 'Comentó',
        'like.toggle' => 'Dio o quitó me gusta',
    ];

    public static function for(?string $name, string $uri): ?string
    {
        if ($name && in_array($name, self::SKIP, true)) {
            return null;
        }

        return self::LABELS[$name ?? ''] ?? self::LABELS[$uri] ?? null;
    }
}
